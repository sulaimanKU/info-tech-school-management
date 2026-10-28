<?php

namespace App\Services\Finance;

use App\Models\User;
use App\Support\Audit;
use App\Support\Domain;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class PayrollService
{
    public function generate(User $user, string $period): string
    {
        return DB::transaction(function () use ($user, $period) {
            app(FinancialLock::class)->request('payroll', $period);
            if (DB::table('payroll_periods')->where('period', $period)->exists()) {
                abort(409);
            }$end = Carbon::parse($period.'-01')->endOfMonth()->toDateString();
            $items = [];
            $total = 0;
            foreach (DB::table('staff_profiles')->where('is_active', true)->whereIn('user_id', DB::table('users')->where('is_active', true)->select('id'))->where('joined_on', '<=', $end)->orderBy('id')->lockForUpdate()->get() as $staff) {
                $salary = DB::table('staff_compensation')->where('staff_profile_id', $staff->id)->where('effective_on', '<=', $end)->orderByDesc('effective_on')->first();
                if (! $salary) {
                    Domain::fail('compensation', 'Configure approved compensation for every eligible active staff member.');
                }
                $adjustments = (int) DB::table('payroll_adjustments')->where('staff_profile_id', $staff->id)->where('period', $period)->sum('amount_minor');
                $base = (int) $salary->base_salary_minor;
                $net = $base + $adjustments;
                if ($net < 0) {
                    Domain::fail('payroll', 'Deductions exceed base salary.');
                }$total += $net;
                $items[] = ['staff_profile_id' => $staff->id, 'base_salary_minor' => $base, 'adjustment_minor' => $adjustments, 'net_salary_minor' => $net];
            }
            if (! $items || $total <= 0) {
                Domain::fail('payroll', 'Configure approved staff compensation before generating payroll.');
            }
            $id = Domain::insert('payroll_periods', ['period' => $period, 'total_minor' => $total, 'status' => 'draft', 'created_by' => $user->id]);
            foreach ($items as $item) {
                Domain::insert('payroll_items', ['payroll_period_id' => $id, ...$item]);
            }Audit::record('payroll_generated', $user->id, 'payroll_periods', $id, ['count' => count($items)]);

            return $id;
        });
    }

    public function approve(User $user, string $id): void
    {
        DB::transaction(function () use ($user, $id) {
            $p = DB::table('payroll_periods')->where('id', $id)->lockForUpdate()->firstOrFail();
            abort_unless($p->status === 'draft', 409);
            DB::table('payroll_periods')->where('id', $id)->update(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now(), 'updated_at' => now()]);
            $journal = app(JournalService::class);
            $journal->post($user, 'payroll-accrual:'.$id, Carbon::parse($p->period.'-01')->endOfMonth()->toDateString(), 'Approved payroll accrual', 'payroll', $id, [['account_id' => $journal->account('salary_expense'), 'debit_minor' => (int) $p->total_minor], ['account_id' => $journal->account('payroll_payable'), 'credit_minor' => (int) $p->total_minor]]);
            Audit::record('payroll_approved', $user->id, 'payroll_periods', $id);
        });
    }

    public function details(object $period): array
    {
        $payments = DB::table('payroll_payments as p')->join('payment_methods as m', 'm.id', '=', 'p.payment_method_id')->join('users as u', 'u.id', '=', 'p.recorded_by')->where('p.payroll_period_id', $period->id)->orderByDesc('p.paid_on')->orderByDesc('p.created_at')->get(['p.*', 'm.name as method', 'u.name as recorded_by_name']);
        $legacyPaid = $period->status === 'paid' && $payments->isEmpty();
        $paid = $legacyPaid ? (int) $period->total_minor : (int) $payments->sum('amount_minor');
        $allocated = DB::table('payroll_payment_allocations as a')->join('payroll_payments as p', 'p.id', '=', 'a.payroll_payment_id')->where('p.payroll_period_id', $period->id)->groupBy('a.payroll_item_id')->selectRaw('a.payroll_item_id, SUM(a.amount_minor) as amount')->pluck('amount', 'payroll_item_id');
        $items = DB::table('payroll_items as i')->join('staff_profiles as s', 's.id', '=', 'i.staff_profile_id')->join('users as u', 'u.id', '=', 's.user_id')->where('i.payroll_period_id', $period->id)->get(['i.*', 'u.name', 's.employee_number'])->map(function ($i) use ($allocated, $legacyPaid) {
            $paid = $legacyPaid ? (int) $i->net_salary_minor : (int) $allocated->get($i->id, 0);

            return [...(array) $i, 'paid_minor' => $paid, 'balance_minor' => (int) $i->net_salary_minor - $paid, 'payment_status' => $paid >= $i->net_salary_minor ? 'paid' : ($paid > 0 ? 'partially_paid' : 'unpaid')];
        });
        if ($legacyPaid) {
            $payments->push((object) ['id' => null, 'amount_minor' => (int) $period->total_minor, 'paid_on' => $period->paid_on, 'reference' => $period->reference, 'notes' => null, 'method' => DB::table('payment_methods')->where('id', $period->payment_method_id)->value('name'), 'recorded_by_name' => null, 'legacy' => true]);
        }

        return [...(array) $period, 'paid_minor' => $paid, 'balance_minor' => (int) $period->total_minor - $paid, 'payment_status' => $paid >= $period->total_minor ? 'paid' : ($paid > 0 ? 'partially_paid' : 'unpaid'), 'method' => DB::table('payment_methods')->where('id', $period->payment_method_id)->value('name'), 'payments' => $payments, 'items' => $items];
    }

    public function pay(User $user, string $id, array $v): void
    {
        DB::transaction(function () use ($user, $id, $v) {
            $request = $v['request_id'] ?? null;
            $hash = hash('sha256', json_encode([$id, $v]));
            if ($request) {
                app(FinancialLock::class)->request('payroll-payment', $request);
                $old = DB::table('payroll_payments')->where('request_id', $request)->first();
                if ($old) {
                    abort_unless(hash_equals($old->payload_hash, $hash), 409);

                    return;
                }
            }
            $p = DB::table('payroll_periods')->where('id', $id)->lockForUpdate()->firstOrFail();
            if ($p->status === 'paid' && ! $request) {
                $legacy = DB::table('payroll_payments')->where('payroll_period_id', $id)->whereNull('request_id')->first();
                if ($legacy) {
                    abort_unless(hash_equals($legacy->payload_hash, $hash), 409);

                    return;
                }
                abort_unless($p->payment_method_id === $v['payment_method_id'] && $p->paid_on === $v['paid_on'] && $p->reference === ($v['reference'] ?? null), 409);

                return;
            }
            abort_unless($p->status === 'approved', 403);
            if ($v['paid_on'] < substr($p->approved_at, 0, 10)) {
                Domain::fail('paid_on', 'Disbursement precedes approval.');
            }
            $method = DB::table('payment_methods')->where('id', $v['payment_method_id'])->where('is_active', true)->firstOrFail();
            abort_unless(DB::table('accounts')->where('id', $method->account_id)->where('is_active', true)->where('type', 'asset')->exists(), 422);
            $details = $this->details($p);
            $amount = $v['amount_minor'] ?? $details['balance_minor'];
            if ($amount <= 0 || $amount > $details['balance_minor']) {
                Domain::fail('amount_minor', 'Payment must be positive and cannot exceed outstanding payroll.');
            }
            $allocations = $v['allocations'] ?? [];
            if (! $allocations) {
                $remaining = $amount;
                foreach ($details['items']->sortBy('id') as $item) {
                    $part = min($remaining, $item['balance_minor']);
                    if ($part > 0) {
                        $allocations[] = ['payroll_item_id' => $item['id'], 'amount_minor' => $part];
                        $remaining -= $part;
                    }
                }
            }
            if (array_sum(array_column($allocations, 'amount_minor')) !== $amount) {
                Domain::fail('allocations', 'Allocate the exact salary payment amount.');
            }
            $seen = [];
            foreach ($allocations as $a) {
                $item = $details['items']->firstWhere('id', $a['payroll_item_id']);
                if (! $item || isset($seen[$a['payroll_item_id']]) || $a['amount_minor'] <= 0 || $a['amount_minor'] > $item['balance_minor']) {
                    Domain::fail('allocations', 'Salary allocation exceeds the employee balance or belongs to another period.');
                }
                $seen[$a['payroll_item_id']] = true;
            }
            $payment = Domain::insert('payroll_payments', ['payroll_period_id' => $id, 'payment_method_id' => $method->id, 'recorded_by' => $user->id, 'request_id' => $request, 'payload_hash' => $hash, 'amount_minor' => $amount, 'paid_on' => $v['paid_on'], 'reference' => $v['reference'] ?? null, 'notes' => $v['notes'] ?? null]);
            foreach ($allocations as $a) {
                Domain::insert('payroll_payment_allocations', ['payroll_payment_id' => $payment, ...$a]);
            }
            if ($amount === $details['balance_minor']) {
                DB::table('payroll_periods')->where('id', $id)->update(['status' => 'paid', 'payment_method_id' => $method->id, 'paid_on' => $v['paid_on'], 'reference' => $v['reference'] ?? null, 'updated_at' => now()]);
            }
            $journal = app(JournalService::class);
            $key = 'payroll-disbursement:'.($request ? $payment : $id);
            $journal->post($user, $key, $v['paid_on'], 'Manual payroll disbursement', 'payroll', $id, [['account_id' => $journal->account('payroll_payable'), 'debit_minor' => $amount], ['account_id' => $method->account_id, 'credit_minor' => $amount]]);
            Audit::record('payroll_paid', $user->id, 'payroll_periods', $id);
        });
    }
}
