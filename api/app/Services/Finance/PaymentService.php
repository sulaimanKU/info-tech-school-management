<?php

namespace App\Services\Finance;

use App\Models\User;
use App\Support\Audit;
use App\Support\Domain;
use Illuminate\Support\Facades\DB;

final class PaymentService
{
    public function __construct(private JournalService $journal, private InvoiceBalance $balance) {}

    private function lockInvoices(array $ids): void
    {
        sort($ids);
        foreach ($ids as $id) {
            DB::table('fee_invoices')->where('id', $id)->lockForUpdate()->firstOrFail();
        }
    }

    public function record(User $user, array $v): string
    {
        return DB::transaction(function () use ($user, $v) {
            app(FinancialLock::class)->request('payment', $v['request_id']);
            $hash = hash('sha256', json_encode($v));
            $old = DB::table('payments')->where('request_id', $v['request_id'])->first();
            if ($old) {
                abort_unless(hash_equals($old->payload_hash, $hash), 409);

                return $old->id;
            }
            $method = DB::table('payment_methods')->where('id', $v['payment_method_id'])->where('is_active', true)->firstOrFail();
            abort_unless(DB::table('accounts')->where('id', $method->account_id)->where('type', 'asset')->where('is_active', true)->exists(), 422);
            if (! empty($v['reference'])) {
                app(FinancialLock::class)->request('payment-reference', $method->id.':'.$v['reference']);
            }
            if (! empty($v['reference']) && DB::table('payments')->where('payment_method_id', $method->id)->where('reference', $v['reference'])->exists()) {
                Domain::fail('reference', 'This reference has already been recorded for this payment method.');
            }
            if (array_sum(array_column($v['allocations'], 'amount_minor')) !== $v['amount_minor']) {
                Domain::fail('allocations', 'Allocate the exact payment amount.');
            }$this->lockInvoices(array_column($v['allocations'], 'fee_invoice_id'));
            $students = [];
            foreach ($v['allocations'] as $a) {
                $invoice = DB::table('fee_invoices')->find($a['fee_invoice_id']);
                $student = DB::table('students')->where('id', $invoice->student_id)->lockForUpdate()->firstOrFail();
                if ($student->status !== 'active') {
                    Domain::fail('allocations', 'Payments require an active student.');
                }
                $students[] = $invoice->student_id;
                if ($a['amount_minor'] > $this->balance->calculate($invoice)['balance_minor']) {
                    Domain::fail('allocations', 'Allocation exceeds the current invoice balance.');
                }
            }
            if ($v['proof_file_id'] ?? null) {
                $file = DB::table('private_files')->where('id', $v['proof_file_id'])->firstOrFail();
                abort_unless($file->purpose === 'payment_proof' && in_array($file->student_id, $students, true), 403);
            }
            $id = Domain::insert('payments', ['payment_method_id' => $method->id, 'paid_on' => $v['paid_on'], 'amount_minor' => $v['amount_minor'], 'reference' => $v['reference'] ?? null, 'notes' => $v['notes'] ?? null, 'proof_file_id' => $v['proof_file_id'] ?? null, 'status' => 'pending', 'request_id' => $v['request_id'], 'payload_hash' => $hash, 'recorded_by' => $user->id]);
            foreach ($v['allocations'] as $a) {
                Domain::insert('payment_allocations', ['payment_id' => $id, ...$a]);
            }
            if ($method->code === 'cash' || ! $method->requires_verification) {
                $this->verify($user, $id);
            }
            Audit::record('payment_recorded', $user->id, 'payments', $id);

            return $id;
        });
    }

    public function verify(User $user, string $payment): void
    {
        DB::transaction(function () use ($user, $payment) {
            $p = DB::table('payments')->where('id', $payment)->lockForUpdate()->firstOrFail();
            if ($p->status === 'verified') {
                return;
            }if ($p->status !== 'pending') {
                abort(409);
            }
            $allocations = DB::table('payment_allocations')->where('payment_id', $payment)->get();
            $this->lockInvoices($allocations->pluck('fee_invoice_id')->all());
            foreach ($allocations as $a) {
                $invoice = DB::table('fee_invoices')->find($a->fee_invoice_id);
                if ((int) $a->amount_minor > $this->balance->calculate($invoice)['balance_minor']) {
                    abort(409);
                }
            }
            $method = DB::table('payment_methods')->find($p->payment_method_id);
            $receipt = app(DocumentNumber::class)->next('RC', $p->paid_on);
            DB::table('payments')->where('id', $payment)->update(['status' => 'verified', 'receipt_number' => $receipt, 'verified_by' => $user->id, 'verified_at' => now(), 'updated_at' => now()]);
            $this->journal->post($user, 'payment:'.$payment, $p->paid_on, 'Verified fee payment', 'payment', $payment, [['account_id' => $method->account_id, 'debit_minor' => (int) $p->amount_minor], ['account_id' => $this->journal->account('receivables'), 'credit_minor' => (int) $p->amount_minor]]);
            Audit::record('payment_verified', $user->id, 'payments', $payment);
        });
    }

    public function refund(User $user, string $payment, array $v): string
    {
        return DB::transaction(function () use ($user, $payment, $v) {
            app(FinancialLock::class)->request('refund', $v['request_id']);
            $hash = hash('sha256', json_encode([$payment, $v]));
            $old = DB::table('payment_refunds')->where('request_id', $v['request_id'])->first();
            if ($old) {
                abort_unless(hash_equals($old->payload_hash, $hash), 409);

                return $old->id;
            }
            $p = DB::table('payments')->where('id', $payment)->lockForUpdate()->firstOrFail();
            abort_unless($p->status === 'verified', 409);
            if ($v['refunded_on'] < $p->paid_on) {
                Domain::fail('refunded_on', 'Refund precedes original payment.');
            }
            $sum = 0;
            $allocations = [];
            foreach ($v['allocations'] as $row) {
                $a = DB::table('payment_allocations')->where('id', $row['payment_allocation_id'])->where('payment_id', $payment)->firstOrFail();
                $refunded = (int) DB::table('refund_allocations')->where('payment_allocation_id', $a->id)->sum('amount_minor');
                if ($row['amount_minor'] > (int) $a->amount_minor - $refunded) {
                    Domain::fail('allocations', 'Refund exceeds the unrefunded allocation.');
                }$allocations[] = $a;
                $sum += $row['amount_minor'];
            }
            $this->lockInvoices(array_map(fn ($a) => $a->fee_invoice_id, $allocations));
            $id = Domain::insert('payment_refunds', ['payment_id' => $payment, 'number' => app(DocumentNumber::class)->next('RF', $v['refunded_on']), 'refunded_on' => $v['refunded_on'], 'amount_minor' => $sum, 'reason' => $v['reason'], 'request_id' => $v['request_id'], 'payload_hash' => $hash, 'approved_by' => $user->id]);
            foreach ($v['allocations'] as $a) {
                Domain::insert('refund_allocations', ['payment_refund_id' => $id, ...$a]);
            }$method = DB::table('payment_methods')->find($p->payment_method_id);
            $this->journal->post($user, 'refund:'.$id, $v['refunded_on'], 'Approved payment refund / reversal', 'payment_refund', $id, [['account_id' => $this->journal->account('receivables'), 'debit_minor' => $sum], ['account_id' => $method->account_id, 'credit_minor' => $sum]]);
            Audit::record('payment_refund_approved', $user->id, 'payment_refunds', $id);

            return $id;
        });
    }
}
