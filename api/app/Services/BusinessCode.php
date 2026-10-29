<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/** Stable business codes, independent of roles and internal UUIDs. */
final class BusinessCode
{
    public function next(string $prefix): string
    {
        return DB::transaction(function () use ($prefix) {
            $key = 'business:'.$prefix;
            DB::table('document_sequences')->insertOrIgnore(['key' => $key, 'next_number' => 1]);
            $row = DB::table('document_sequences')->where('key', $key)->lockForUpdate()->first();
            DB::table('document_sequences')->where('key', $key)->increment('next_number');

            return sprintf('%s-%06d', $prefix, $row->next_number);
        });
    }
}
