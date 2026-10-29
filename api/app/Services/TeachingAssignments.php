<?php

namespace App\Services;

use App\Models\User;
use App\Support\Domain;
use Illuminate\Support\Facades\DB;

final class TeachingAssignments
{
    public function requireManager(User $user): void
    {
        abort_unless($user->hasPermission('school.manage') || $user->hasPermission('assignments.manage'), 403);
    }

    public function authorizeSection(User $user, string $section): void
    {
        $this->requireManager($user);
        app(SchoolAccess::class)->section($user, $section);
    }

    public function query(User $user)
    {
        return DB::table('teacher_assignments as ta')->join('staff_profiles as p', 'p.id', '=', 'ta.staff_profile_id')
            ->join('users as u', 'u.id', '=', 'p.user_id')->join('sections as sec', 'sec.id', '=', 'ta.section_id')
            ->join('classes as c', 'c.id', '=', 'sec.class_id')->join('academic_sessions as y', 'y.id', '=', 'sec.academic_session_id')
            ->leftJoin('subjects as sub', 'sub.id', '=', 'ta.subject_id')
            ->whereIn('ta.section_id', app(SchoolAccess::class)->sections($user)->select('sections.id'));
    }

    public function requireAssignedTeacher(string $user, string $section, string $subject, string $date): void
    {
        $eligible = DB::table('teacher_assignments as a')->join('staff_profiles as p', 'p.id', '=', 'a.staff_profile_id')
            ->join('users as u', 'u.id', '=', 'p.user_id')->where('p.user_id', $user)->where('p.is_active', true)->where('u.is_active', true)
            ->where('p.joined_on', '<=', $date)->where('a.is_active', true)->where('a.section_id', $section)->where('a.subject_id', $subject)
            ->where(fn ($q) => $q->whereNull('a.starts_on')->orWhere('a.starts_on', '<=', $date))
            ->where(fn ($q) => $q->whereNull('a.ends_on')->orWhere('a.ends_on', '>=', $date))
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('user_role_assignments as ur')->join('roles as role', 'role.id', '=', 'ur.role_id')->whereColumn('ur.user_id', 'u.id')->where('role.code', 'teacher'))->exists();
        if (! $eligible) {
            Domain::fail('teacher_user_id', 'Select an active teacher assigned to this section and subject on the assessment date.');
        }
    }
}
