<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class Audit
{
    public static function record(string $event, ?string $userId = null, ?string $type = null, ?string $id = null, array $context = []): void
    {
        // Context is deliberately restricted to structural metadata, never submitted values.
        $safe = array_intersect_key($context, array_flip(['changed_fields', 'state', 'version', 'count', 'reason_code']));
        DB::table('audit_logs')->insert(['id' => (string) Str::uuid(), 'user_id' => $userId, 'event' => $event, 'subject_type' => $type, 'subject_id' => $id, 'context' => json_encode($safe), 'created_at' => now()]);
    }
}
