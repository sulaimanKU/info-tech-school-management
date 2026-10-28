<?php

namespace App\Services\Finance;

use App\Models\User;
use App\Support\Audit;
use App\Support\Domain;
use Illuminate\Support\Facades\DB;

final class JournalService
{
    public function account(string $code): string
    {
        return DB::table('accounts')->where('code', $code)->where('is_active', true)->firstOrFail()->id;
    }

    public function post(User $user, string $key, string $date, string $description, string $type, string $reference, array $lines, ?string $reversal = null): string
    {
        return DB::transaction(function () use ($user, $key, $date, $description, $type, $reference, $lines, $reversal) {
            $lines = array_values(array_filter($lines, fn ($l) => ($l['debit_minor'] ?? 0) > 0 || ($l['credit_minor'] ?? 0) > 0));
            $debit = 0;
            $credit = 0;
            foreach ($lines as $line) {
                $d = $line['debit_minor'] ?? 0;
                $c = $line['credit_minor'] ?? 0;
                if (! is_int($d) || ! is_int($c) || $d < 0 || $c < 0 || ($d > 0) == ($c > 0)) {
                    Domain::fail('journal', 'Journal lines must have one positive integer debit or credit.');
                }$debit += $d;
                $credit += $c;
            }
            if (count($lines) < 2 || $debit !== $credit) {
                Domain::fail('journal', 'Journal must balance.');
            }
            $hash = hash('sha256', json_encode([$date, $type, $reference, $lines, $reversal]));
            app(FinancialLock::class)->request('journal', $key);
            $old = DB::table('journal_entries')->where('posting_key', $key)->first();
            if ($old) {
                abort_unless(hash_equals($old->payload_hash, $hash), 409);

                return $old->id;
            }
            $id = Domain::insert('journal_entries', ['number' => app(DocumentNumber::class)->next('JE', $date), 'posting_key' => $key, 'payload_hash' => $hash, 'date' => $date, 'description' => $description, 'reference_type' => $type, 'reference_id' => $reference, 'reversal_of' => $reversal, 'posted_by' => $user->id]);
            foreach ($lines as $line) {
                Domain::insert('journal_lines', ['journal_entry_id' => $id, 'account_id' => $line['account_id'], 'debit_minor' => $line['debit_minor'] ?? 0, 'credit_minor' => $line['credit_minor'] ?? 0, 'currency' => 'PKR']);
            }
            Audit::record('journal_posted', $user->id, 'journal_entries', $id, ['count' => count($lines)]);

            return $id;
        });
    }

    public function reverse(User $user, string $id, string $date, string $reason): string
    {
        return DB::transaction(function () use ($user, $id, $date, $reason) {
            $old = DB::table('journal_entries')->where('id', $id)->lockForUpdate()->firstOrFail();
            $lines = DB::table('journal_lines')->where('journal_entry_id', $id)->get()->map(fn ($l) => ['account_id' => $l->account_id, 'debit_minor' => (int) $l->credit_minor, 'credit_minor' => (int) $l->debit_minor])->all();

            return $this->post($user, 'reversal:'.$id, $date, $reason, 'reversal', $id, $lines, $id);
        });
    }
}
