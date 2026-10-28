<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;

final class FinancialLock
{
    public function request(string $kind, string $id): void
    {
        if (DB::transactionLevel() < 1) {
            throw new \LogicException('A transaction is required for a database lock.');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::select('SELECT pg_advisory_xact_lock(?)', [hexdec(substr(hash('sha256', $kind.':'.$id), 0, 14))]);
        } else {
            // The unique insert also serializes the first use of a key; locks release on commit/rollback.
            $key = hash('sha256', $kind.':'.$id);
            DB::table('transaction_locks')->insertOrIgnore(['key' => $key]);
            DB::table('transaction_locks')->where('key', $key)->lockForUpdate()->firstOrFail();
        }
    }
}
