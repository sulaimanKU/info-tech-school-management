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

    public function validateAssignment(string $catalog, array $v, ?string $id = null): void
    {
        $profile = DB::table('staff_profiles')->where('id', $v['staff_profile_id'])->lockForUpdate()->firstOrFail();
        $u = User::findOrFail($profile->user_id);
        if (($catalog === 'teacher-assignments' && $id === null) || ($v['is_active'] ?? true)) {
            abort_unless($profile->is_active && $u->is_active && $u->roles()->where('code', $catalog === 'teacher-assignments' ? 'teacher' : 'coordinator')->exists(), 422);
        }
        $section = DB::table('sections')->where('id', $v['section_id'])->lockForUpdate()->firstOrFail();
        if ($catalog === 'teacher-assignments') {
            if (empty($v['subject_id']) && empty($v['is_class_teacher'])) {
                Domain::fail('subject_id', 'Select a subject or a class teacher assignment.');
            }
            if (! empty($v['subject_id']) && ! DB::table('class_subject_assignments')->where('class_id', $section->class_id)->where('academic_session_id', $section->academic_session_id)->where('subject_id', $v['subject_id'])->exists()) {
                Domain::fail('subject_id', 'Subject must belong to the selected class/session.');
            }
        }
        $session = DB::table('academic_sessions')->find($section->academic_session_id);
        $from = $v['starts_on'] ?? $session->starts_on;
        $through = $v['ends_on'] ?? $session->ends_on;
        if ($from < $session->starts_on || $through > $session->ends_on || $from > $through) {
            Domain::fail('starts_on', 'Assignment dates must fall inside the section academic session.');
        }
        if (($v['is_active'] ?? true)) {
            // Enabled-row uniqueness is deliberately independent of dates in both
            // MariaDB's generated indexes and PostgreSQL's partial indexes.
            // Retain an old row by deactivating it before reusing its combination.
            $q = DB::table($catalog === 'teacher-assignments' ? 'teacher_assignments' : 'coordinator_assignments')->where('is_active', true)->where('section_id', $section->id)->when($id, fn ($q) => $q->where('id', '!=', $id));
            if ($catalog === 'coordinator-assignments') {
                $q->where('staff_profile_id', $profile->id);
            } else {
                $q->where(fn ($q) => $q->where(fn ($q) => $q->where('staff_profile_id', $profile->id)->where('subject_id', $v['subject_id'] ?? null))->when($v['is_class_teacher'], fn ($q) => $q->orWhere('is_class_teacher', true)));
            }
            if ($q->lockForUpdate()->first(['id'])) {
                Domain::fail('assignment', 'An active matching assignment already exists.');
            }
        }
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
