<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class Domain
{
    public static function insert(string $table, array $values): string
    {
        $id = (string) Str::uuid();
        DB::table($table)->insert(['id' => $id, ...$values, 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    public static function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    public static function response(mixed $data, int $status = 200)
    {
        return response()->json(['data' => $data, 'error' => null], $status);
    }
}
