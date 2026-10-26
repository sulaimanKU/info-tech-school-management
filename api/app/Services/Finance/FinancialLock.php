<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;

final class FinancialLock
{
    public function request(string $kind, string $id): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::select('SELECT pg_advisory_xact_lock(?)', [hexdec(substr(hash('sha256', $kind.':'.$id), 0, 14))]);
        }
    }
}
