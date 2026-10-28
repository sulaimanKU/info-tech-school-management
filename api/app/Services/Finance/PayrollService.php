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

    public function pay(User $user, string $id, array $v): void
    {
        DB::transaction(function () use ($user, $id, $v) {
            $p = DB::table('payroll_periods')->where('id', $id)->lockForUpdate()->firstOrFail();
            if ($p->status === 'paid') {
                abort_unless($p->payment_method_id === $v['payment_method_id'] && $p->paid_on === $v['paid_on'] && $p->reference === ($v['reference'] ?? null), 409);

                return;
            }abort_unless($p->status === 'approved', 403);
            if ($v['paid_on'] < substr($p->approved_at, 0, 10)) {
                Domain::fail('paid_on', 'Disbursement precedes approval.');
            }$method = DB::table('payment_methods')->where('id', $v['payment_method_id'])->where('is_active', true)->firstOrFail();
            DB::table('payroll_periods')->where('id', $id)->update(['status' => 'paid', 'payment_method_id' => $method->id, 'paid_on' => $v['paid_on'], 'reference' => $v['reference'] ?? null, 'updated_at' => now()]);
            $journal = app(JournalService::class);
            $journal->post($user, 'payroll-disbursement:'.$id, $v['paid_on'], 'Payroll disbursement', 'payroll', $id, [['account_id' => $journal->account('payroll_payable'), 'debit_minor' => (int) $p->total_minor], ['account_id' => $method->account_id, 'credit_minor' => (int) $p->total_minor]]);
            Audit::record('payroll_paid', $user->id, 'payroll_periods', $id);
        });
    }
}
