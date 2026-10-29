<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class SchoolAccess
{
    public function isParent(User $u): bool
    {
        return $u->roles()->where('code', 'parent_guardian')->exists() && ! $u->roles()->where('code', '!=', 'parent_guardian')->exists();
    }

    public function academic(User $u): void
    {
        abort_unless($this->isParent($u) || $u->hasPermission('academics.read') || $u->hasPermission('academics.write') || $u->hasPermission('academics.review'), 403);
    }

    public function students(User $u)
    {
        $q = DB::table('students');
        abort_unless($u->is_active, 403);
        if ($this->isParent($u)) {
            return $q->whereIn('students.id', DB::table('parent_student_links as l')->join('parent_guardians as g', 'g.id', '=', 'l.parent_guardian_id')->where('g.user_id', $u->id)->whereNotNull('g.mobile_verified_at')->where('l.is_active', true)->whereNotNull('l.verified_at')->select('l.student_id'));
        }
        abort_unless($u->hasPermission('students.read') || $u->hasPermission('students.read.all') || $u->hasPermission('students.manage') || $u->hasPermission('academics.write'), 403);
        if ($u->hasPermission('students.read.all')) {
            return $q;
        }

        return $q->whereIn('students.id', DB::table('enrollments')->where('starts_on', '<=', now()->toDateString())->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', now()->toDateString()))->whereIn('section_id', $this->sections($u)->select('sections.id'))->select('student_id'));
    }

    public function student(User $u, string $id)
    {
        return $this->students($u)->where('students.id', $id)->firstOrFail();
    }

    public function sections(User $u, ?string $date = null)
    {
        $date = $date ?? now()->toDateString();
        $q = DB::table('sections');
        if (! $u->is_active) {
            return $q->whereRaw('1=0');
        }
        if ($this->isParent($u)) {
            return $q->whereIn('sections.id', DB::table('enrollments')->whereIn('student_id', $this->students($u)->select('students.id'))->where('starts_on', '<=', $date)->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $date))->select('section_id'));
        }
        if ($u->hasPermission('students.read.all')) {
            return $q;
        }
        $assignments = function (string $table) use ($u, $date) {
            return DB::table($table.' as a')->join('staff_profiles as p', 'p.id', '=', 'a.staff_profile_id')->where('p.user_id', $u->id)->where('p.is_active', true)->where('p.joined_on', '<=', $date)->where('a.is_active', true)->where(fn ($q) => $q->whereNull('a.starts_on')->orWhere('a.starts_on', '<=', $date))->where(fn ($q) => $q->whereNull('a.ends_on')->orWhere('a.ends_on', '>=', $date))->select('a.section_id');
        };

        return $q->where(function ($q) use ($u, $assignments) {
            $q->whereRaw('1=0');
            if ($u->roles()->where('code', 'teacher')->exists()) {
                $q->orWhereIn('sections.id', $assignments('teacher_assignments'));
            }
            if ($u->roles()->where('code', 'coordinator')->exists()) {
                $q->orWhereIn('sections.id', $assignments('coordinator_assignments'));
            }
        });
    }

    public function section(User $u, string $id, ?string $subject = null, ?string $date = null): void
    {
        abort_if($this->isParent($u), 403);
        abort_unless($this->sections($u)->where('sections.id', $id)->exists(), 403);
        if ($date) {
            abort_unless($this->sections($u, $date)->where('sections.id', $id)->exists(), 403);
        }
        if ($subject) {
            $section = DB::table('sections')->find($id);
            abort_unless(DB::table('class_subject_assignments')->where('class_id', $section->class_id)->where('academic_session_id', $section->academic_session_id)->where('subject_id', $subject)->exists(), 403);
            if (! $u->hasPermission('students.read.all') && ! $this->coordinated($u, $id)) {
                $at = $date ?? now()->toDateString();
                abort_unless(DB::table('teacher_assignments as a')->join('staff_profiles as p', 'p.id', '=', 'a.staff_profile_id')->where('p.user_id', $u->id)->where('p.is_active', true)->where('a.is_active', true)->where('a.section_id', $id)->where('a.subject_id', $subject)->where(fn ($q) => $q->whereNull('a.starts_on')->orWhere('a.starts_on', '<=', now()->toDateString()))->where(fn ($q) => $q->whereNull('a.ends_on')->orWhere('a.ends_on', '>=', now()->toDateString()))->where(fn ($q) => $q->whereNull('a.starts_on')->orWhere('a.starts_on', '<=', $at))->where(fn ($q) => $q->whereNull('a.ends_on')->orWhere('a.ends_on', '>=', $at))->exists(), 403);
            }
        }
    }

    public function coordinated(User $u, string $section): bool
    {
        if (! $u->roles()->where('code', 'coordinator')->exists()) {
            return false;
        }

        return DB::table('coordinator_assignments as a')->join('staff_profiles as p', 'p.id', '=', 'a.staff_profile_id')->where('p.user_id', $u->id)->where('p.is_active', true)->where('a.is_active', true)->where('a.section_id', $section)->where(fn ($q) => $q->whereNull('a.starts_on')->orWhere('a.starts_on', '<=', now()->toDateString()))->where(fn ($q) => $q->whereNull('a.ends_on')->orWhere('a.ends_on', '>=', now()->toDateString()))->exists();
    }

    public function subjects(User $u, ?string $section = null)
    {
        $sections = $this->sections($u);
        if ($section) {
            $sections->where('sections.id', $section);
        }
        $q = DB::table('subjects')->whereIn('subjects.id', DB::table('class_subject_assignments as cs')->joinSub($sections->select('sections.class_id', 'sections.academic_session_id'), 'sc', fn ($j) => $j->on('sc.class_id', '=', 'cs.class_id')->on('sc.academic_session_id', '=', 'cs.academic_session_id'))->select('cs.subject_id'));
        if ($u->hasPermission('school.manage') && $section === null) {
            return DB::table('subjects');
        }
        if (! $this->isParent($u) && ! $u->hasPermission('students.read.all') && ! $u->roles()->where('code', 'coordinator')->exists()) {
            $q->whereIn('subjects.id', DB::table('teacher_assignments as a')->join('staff_profiles as p', 'p.id', '=', 'a.staff_profile_id')->where('p.user_id', $u->id)->where('p.is_active', true)->where('a.is_active', true)->whereIn('a.section_id', $this->sections($u)->select('sections.id'))->when($section, fn ($q) => $q->where('a.section_id', $section))->where(fn ($q) => $q->whereNull('a.starts_on')->orWhere('a.starts_on', '<=', now()->toDateString()))->where(fn ($q) => $q->whereNull('a.ends_on')->orWhere('a.ends_on', '>=', now()->toDateString()))->select('a.subject_id'));
        }

        return $q;
    }

    /** Lists must honor the same current and record-date assignments as object reads. */
    public function academicRecords($query, User $u, string $section, ?string $subject, string $date)
    {
        if ($this->isParent($u) || $u->hasPermission('students.read.all')) {
            return $query;
        }

        return $query->where(function ($scope) use ($u, $section, $subject, $date) {
            $scope->whereRaw('1=0');
            foreach (['teacher' => 'teacher_assignments', 'coordinator' => 'coordinator_assignments'] as $role => $table) {
                if (! $u->roles()->where('code', $role)->exists()) {
                    continue;
                }
                $scope->orWhereExists(function ($q) use ($u, $section, $subject, $date, $table, $role) {
                    $q->selectRaw('1')->from($table.' as dated_a')->join('staff_profiles as dated_p', 'dated_p.id', '=', 'dated_a.staff_profile_id')->where('dated_p.user_id', $u->id)->where('dated_p.is_active', true)->where('dated_a.is_active', true)->whereColumn('dated_a.section_id', $section)->whereColumn('dated_p.joined_on', '<=', $date);
                    if ($role === 'teacher' && $subject !== null) {
                        $q->whereColumn('dated_a.subject_id', $subject);
                    }
                    $q->where(fn ($q) => $q->whereNull('dated_a.starts_on')->orWhereColumn('dated_a.starts_on', '<=', $date))->where(fn ($q) => $q->whereNull('dated_a.ends_on')->orWhereColumn('dated_a.ends_on', '>=', $date))->where(fn ($q) => $q->whereNull('dated_a.starts_on')->orWhere('dated_a.starts_on', '<=', now()->toDateString()))->where(fn ($q) => $q->whereNull('dated_a.ends_on')->orWhere('dated_a.ends_on', '>=', now()->toDateString()));
                });
            }
        });
    }

    public function require(User $u, string $permission): void
    {
        abort_unless($u->hasPermission($permission), 403);
    }
}
