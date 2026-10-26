<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class NotificationService
{
    public function user(string $user, string $kind, string $targetType, string $targetId, string $event, ?string $student = null): void
    {
        DB::table('school_notifications')->insertOrIgnore(['id' => (string) Str::uuid(), 'user_id' => $user, 'kind' => $kind, 'target_type' => $targetType, 'target_id' => $targetId, 'event_key' => $event, 'student_id' => $student, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function parents(string $student, string $kind, string $type, string $id, string $event): void
    {
        $users = DB::table('parent_student_links as l')->join('parent_guardians as g', 'g.id', '=', 'l.parent_guardian_id')->join('users as u', 'u.id', '=', 'g.user_id')->where('l.student_id', $student)->where('l.is_active', true)->whereNotNull('l.verified_at')->whereNotNull('g.mobile_verified_at')->where('u.is_active', true)->distinct()->pluck('u.id');
        foreach ($users as $user) {
            $this->user($user, $kind, $type, $id, $event, $student);
        }
    }
}
