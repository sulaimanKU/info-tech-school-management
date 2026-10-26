<?php

namespace App\Services\Finance;

use App\Models\User;
use App\Support\Audit;
use App\Support\Domain;
use Illuminate\Support\Facades\DB;

final class ExpenseService
{
    public function approve(User $user, string $id): void
    {
        DB::transaction(function () use ($user, $id) {
            $e = DB::table('expenses')->where('id', $id)->lockForUpdate()->firstOrFail();
            abort_unless($e->status === 'requested', 409);
            DB::table('expenses')->where('id', $id)->update(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now(), 'updated_at' => now()]);
            Audit::record('expense_approved', $user->id, 'expenses', $id);
        });
    }

    public function pay(User $user, string $id, array $v): void
    {
        DB::transaction(function () use ($user, $id, $v) {
            $e = DB::table('expenses')->where('id', $id)->lockForUpdate()->firstOrFail();
            if ($e->status === 'paid') {
                abort_unless($e->payment_method_id === $v['payment_method_id'] && $e->paid_on === $v['paid_on'] && $e->reference === ($v['reference'] ?? null), 409);

                return;
            }
            $threshold = min(50000, (int) json_decode(DB::table('school_settings')->where('key', 'expense_approval_threshold_minor')->value('value') ?? '50000', true));
            if ((int) $e->amount_minor > $threshold && ($e->status !== 'approved' || ! $e->approved_at)) {
                abort(403);
            }
            if ($e->status === 'denied') {
                abort(409);
            }if ($e->approved_at && $v['paid_on'] < substr($e->approved_at, 0, 10)) {
                Domain::fail('paid_on', 'Payment date precedes approval.');
            }
            $method = DB::table('payment_methods')->where('id', $v['payment_method_id'])->where('is_active', true)->firstOrFail();
            if ($method->requires_verification && empty($v['reference'])) {
                Domain::fail('reference', 'Enter the verified bank reference.');
            }
            DB::table('expenses')->where('id', $id)->update(['status' => 'paid', 'payment_method_id' => $method->id, 'paid_by' => $user->id, 'paid_on' => $v['paid_on'], 'reference' => $v['reference'] ?? null, 'updated_at' => now()]);
            $journal = app(JournalService::class);
            $journal->post($user, 'expense:'.$id, $v['paid_on'], $e->description, 'expense', $id, [['account_id' => $journal->account('operating_expense'), 'debit_minor' => (int) $e->amount_minor], ['account_id' => $method->account_id, 'credit_minor' => (int) $e->amount_minor]]);
            Audit::record('expense_paid', $user->id, 'expenses', $id);
        });
    }
}
