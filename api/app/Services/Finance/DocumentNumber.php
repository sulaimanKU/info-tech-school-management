<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;

final class DocumentNumber
{
    public function next(string $prefix, string $date): string
    {
        $year = substr($date, 0, 4);
        $key = $prefix.':'.$year;
        DB::table('document_sequences')->insertOrIgnore(['key' => $key, 'next_number' => 1]);
        $row = DB::table('document_sequences')->where('key', $key)->lockForUpdate()->first();
        DB::table('document_sequences')->where('key', $key)->increment('next_number');

        return sprintf('INF-SG-%s-%s-%06d', $prefix, $year, $row->next_number);
    }
}
