<?php

namespace App\Console\Commands;

use App\Services\TeachingAssignments;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Read-only aggregate diagnostics; never emits names, IDs or credentials. */
class DiagnoseTeacherSelection extends Command
{
    protected $signature = 'school:diagnose-teacher-selection {--json : Output aggregate counts as JSON}';

    protected $description = 'Read-only counts explaining teacher selector eligibility and missing employment links';

    public function handle(TeachingAssignments $assignments): int
    {
        $staff = DB::table('users as u')->whereExists(fn ($q) => $q->selectRaw('1')->from('user_role_assignments as ur')->join('roles as r', 'r.id', '=', 'ur.role_id')->whereColumn('ur.user_id', 'u.id')->where('r.code', '!=', 'parent_guardian'));
        $teachers = DB::table('users as u')->whereExists(fn ($q) => $q->selectRaw('1')->from('user_role_assignments as ur')->join('roles as r', 'r.id', '=', 'ur.role_id')->whereColumn('ur.user_id', 'u.id')->where('r.code', 'teacher'));
        $linked = (clone $teachers)->leftJoin('staff_profiles as p', 'p.user_id', '=', 'u.id');
        $counts = [
            'active_staff_accounts' => (clone $staff)->where('u.is_active', true)->count(),
            'teacher_role_accounts' => (clone $teachers)->count(),
            'active_teacher_accounts' => (clone $teachers)->where('u.is_active', true)->count(),
            'teacher_accounts_with_staff_profiles' => (clone $linked)->whereNotNull('p.id')->count(),
            'eligible_teachers' => $assignments->selectableTeachers()->count(),
            'eligible_teachers_missing_staff_profile' => $assignments->selectableTeachers()->whereNull('p.id')->count(),
            'excluded_inactive_teacher_accounts' => (clone $teachers)->where('u.is_active', false)->count(),
            'excluded_active_teachers_with_inactive_profile' => (clone $linked)->where('u.is_active', true)->where('p.is_active', false)->count(),
            'excluded_active_staff_without_teacher_role' => (clone $staff)->where('u.is_active', true)->whereNotIn('u.id', (clone $teachers)->select('u.id'))->count(),
        ];
        if ($this->option('json')) {
            $this->line(json_encode($counts, JSON_THROW_ON_ERROR));
        } else {
            $this->table(['Measure', 'Count'], collect($counts)->map(fn ($value, $key) => [$key, $value])->values()->all());
            $this->line('Read only: active Teacher-role accounts are selectable unless their employment profile is disabled. Missing employment links require authorized setup before assignment writes.');
        }

        return self::SUCCESS;
    }
}
