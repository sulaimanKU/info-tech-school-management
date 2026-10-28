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
        $response = response()->json(['data' => $data, 'error' => null], $status);
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            // Query-builder TINYINT booleans must retain PostgreSQL's JSON boolean contract.
            $payload = $response->getData();
            self::booleanFields($payload);
            $response->setData($payload);
        }

        return $response;
    }

    private static function booleanFields(mixed &$value): void
    {
        if (! is_array($value) && ! $value instanceof \stdClass) {
            return;
        }
        foreach ($value as $key => &$item) {
            // Preserve user-defined JSON documents and their original scalar types.
            if (in_array($key, ['snapshot', 'mapping', 'report', 'context'], true)) {
                continue;
            }
            if (in_array($key, ['is_active', 'is_primary', 'is_class_teacher', 'is_school_day', 'whole_class', 'parent_visible', 'is_internal', 'requires_verification'], true)
                && in_array($item, [0, 1], true)) {
                $item = (bool) $item;
            } else {
                self::booleanFields($item);
            }
        }
        unset($item);
    }
}
