<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Services\AcademicFilters;
use App\Services\SchoolAccess;
use App\Services\SchoolSettings;
use App\Services\TeachingAssignments;
use App\Support\Audit;
use App\Support\Domain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CatalogController extends Controller
{
    private const TABLES = ['sessions' => 'academic_sessions', 'classes' => 'classes', 'sections' => 'sections', 'subjects' => 'subjects', 'class-subjects' => 'class_subject_assignments', 'staff' => 'staff_profiles', 'teacher-assignments' => 'teacher_assignments', 'coordinator-assignments' => 'coordinator_assignments', 'fee-categories' => 'fee_categories'];

    public function __construct(private SchoolAccess $access) {}

    public function index(Request $r, string $catalog)
    {
        abort_if($this->access->isParent($r->user()) && ! in_array($catalog, ['sessions', 'classes', 'sections', 'subjects', 'class-subjects']), 403);
        $v = app(AcademicFilters::class)->values($r);
        $table = self::TABLES[$catalog] ?? abort(404);
        if (in_array($catalog, ['subjects', 'class-subjects'])) {
            $this->access->academic($r->user());
        }
        $sections = app(AcademicFilters::class)->sections($r, $v);
        $q = DB::table($table);
        if ($table === 'sections') {
            $q = $sections;
        }
        if ($table === 'subjects') {
            $q = $this->access->subjects($r->user(), $v['section_id'] ?? null);
            if (isset($v['class_id']) || isset($v['academic_session_id'])) {
                $q->whereIn('subjects.id', DB::table('class_subject_assignments')->when($v['class_id'] ?? null, fn ($q, $id) => $q->where('class_id', $id))->when($v['academic_session_id'] ?? null, fn ($q, $id) => $q->where('academic_session_id', $id))->select('subject_id'));
            }
        }
        if (in_array($table, ['classes', 'academic_sessions']) && ! $r->user()->hasPermission('school.manage')) {
            $q->whereIn('id', (clone $sections)->select('sections.'.($table === 'classes' ? 'class_id' : 'academic_session_id')));
        }
        if ($table === 'class_subject_assignments') {
            $q->whereIn('subject_id', $this->access->subjects($r->user(), $v['section_id'] ?? null)->select('subjects.id'));
            $q->whereExists(fn ($s) => $s->selectRaw('1')->fromSub((clone $sections)->select('sections.class_id', 'sections.academic_session_id'), 'sc')->whereColumn('sc.class_id', 'class_subject_assignments.class_id')->whereColumn('sc.academic_session_id', 'class_subject_assignments.academic_session_id'));
        }
        if ($table === 'staff_profiles' && ! $r->user()->hasPermission('staff.manage')) {
            $q->where('user_id', $r->user()->id);
        }
        if (in_array($table, ['teacher_assignments', 'coordinator_assignments']) && ! $r->user()->hasPermission('school.manage')) {
            $q->whereIn('staff_profile_id', DB::table('staff_profiles')->where('user_id', $r->user()->id)->select('id'));
        }
        if ($table === 'fee_categories') {
            $this->access->require($r->user(), 'finance.read');
        }
        foreach (['class_id', 'academic_session_id'] as $key) {
            if (isset($v[$key]) && in_array($table, ['class_subject_assignments', 'sections'])) {
                $q->where($table.'.'.$key, $v[$key]);
            }
        }

        if ($table === 'staff_profiles') {
            $q->join('users as staff_user', 'staff_user.id', '=', 'staff_profiles.user_id')->select('staff_profiles.*', 'staff_user.name', 'staff_user.account_code');
        }

        return Domain::response(['items' => $q->limit(1000)->get(), ...($catalog === 'sessions' ? ['current_academic_session_id' => app(SchoolSettings::class)->currentSession()] : [])]);
    }

    public function filterOptions(Request $r)
    {
        abort_if($this->access->isParent($r->user()), 403);
        $this->access->students($r->user());
        $filters = app(AcademicFilters::class);
        $v = $filters->values($r);
        $all = $this->access->sections($r->user());
        $sections = $filters->sections($r, $v);
        $sessions = DB::table('academic_sessions')->whereIn('id', (clone $all)->select('sections.academic_session_id'));
        $classes = DB::table('classes')->whereIn('id', (clone $all)->when($v['academic_session_id'] ?? null, fn ($q, $id) => $q->where('sections.academic_session_id', $id))->select('sections.class_id'));

        $subjects = $this->access->subjects($r->user(), $v['section_id'] ?? null);
        if (isset($v['class_id']) || isset($v['academic_session_id'])) {
            $subjects->whereIn('subjects.id', DB::table('class_subject_assignments')->when($v['class_id'] ?? null, fn ($q, $id) => $q->where('class_id', $id))->when($v['academic_session_id'] ?? null, fn ($q, $id) => $q->where('academic_session_id', $id))->select('subject_id'));
        }

        return Domain::response(['current_academic_session_id' => app(SchoolSettings::class)->currentSession(), 'sessions' => $sessions->orderByDesc('starts_on')->get(['id', 'name']), 'classes' => $classes->orderBy('sort_order')->get(['id', 'name']), 'sections' => $sections->orderBy('name')->get(['id', 'name', 'class_id', 'academic_session_id']), 'subjects' => ($r->user()->hasPermission('academics.read') || $r->user()->hasPermission('academics.write') || $r->user()->hasPermission('academics.review')) ? $subjects->orderBy('name')->orderBy('code')->get(['id', 'name', 'code']) : []]);
    }

    private function rules(string $catalog): array
    {
        return match ($catalog) {
            'sessions' => ['name' => 'required|string|max:80', 'starts_on' => 'required|date', 'ends_on' => 'required|date|after_or_equal:starts_on'],
            'classes' => ['name' => 'required|string|max:80', 'sort_order' => 'required|integer|min:0|max:100'],
            'sections' => ['name' => 'required|string|max:80', 'class_id' => 'required|uuid|exists:classes,id', 'academic_session_id' => 'required|uuid|exists:academic_sessions,id'],
            'subjects' => ['name' => 'required|string|max:100', 'code' => 'required|alpha_dash|max:30'],
            'class-subjects' => ['class_id' => 'required|uuid|exists:classes,id', 'subject_id' => 'required|uuid|exists:subjects,id', 'academic_session_id' => 'required|uuid|exists:academic_sessions,id'],
            'staff' => ['user_id' => 'required|uuid|exists:users,id', 'employee_number' => 'required|string|max:40', 'joined_on' => 'required|date', 'designation' => 'required|string|max:100', 'is_active' => 'sometimes|boolean'],
            'teacher-assignments' => ['staff_profile_id' => 'required|uuid|exists:staff_profiles,id', 'section_id' => 'required|uuid|exists:sections,id', 'subject_id' => 'nullable|uuid|exists:subjects,id', 'is_class_teacher' => 'required|boolean', 'is_active' => 'sometimes|boolean', 'starts_on' => 'nullable|date', 'ends_on' => 'nullable|date|after_or_equal:starts_on'],
            'coordinator-assignments' => ['staff_profile_id' => 'required|uuid|exists:staff_profiles,id', 'section_id' => 'required|uuid|exists:sections,id', 'is_active' => 'sometimes|boolean', 'starts_on' => 'nullable|date', 'ends_on' => 'nullable|date|after_or_equal:starts_on'],
            'fee-categories' => ['name' => 'required|string|max:80'], default => abort(404),
        };
    }

    public function store(Request $r, string $catalog)
    {
        if ($catalog === 'teacher-assignments') {
            app(TeachingAssignments::class)->requireManager($r->user());
        } else {
            $this->access->require($r->user(), $catalog === 'staff' ? 'staff.manage' : 'school.manage');
        }
        $v = $r->validate($this->rules($catalog));
        if ($catalog === 'teacher-assignments') {
            app(TeachingAssignments::class)->authorizeSection($r->user(), $v['section_id']);
        }
        if ($catalog === 'staff') {
            abort_unless(User::findOrFail($v['user_id'])->isStaff(), 422);
        }
        $id = DB::transaction(function () use ($r, $catalog, $v) {
            if ($catalog === 'teacher-assignments') {
                app(TeachingAssignments::class)->authorizeSection($r->user(), $v['section_id']);
            }
            if (in_array($catalog, ['teacher-assignments', 'coordinator-assignments'])) {
                app(TeachingAssignments::class)->validateAssignment($catalog, $v);
            }
            $id = Domain::insert(self::TABLES[$catalog], $v);
            Audit::record('catalog_created', $r->user()->id, self::TABLES[$catalog], $id);

            return $id;
        }, 3);

        return Domain::response(['id' => $id], 201);
    }

    public function update(Request $r, string $catalog, string $id)
    {
        if ($catalog === 'teacher-assignments') {
            app(TeachingAssignments::class)->requireManager($r->user());
        } else {
            $this->access->require($r->user(), $catalog === 'staff' ? 'staff.manage' : 'school.manage');
        }
        abort_unless(in_array($catalog, ['sessions', 'classes', 'sections', 'subjects', 'staff', 'fee-categories', 'teacher-assignments', 'coordinator-assignments']), 405);
        $table = self::TABLES[$catalog];
        DB::transaction(function () use ($r, $catalog, $id, $table) {
            $old = DB::table($table)->where('id', $id)->lockForUpdate()->firstOrFail();
            if ($catalog === 'teacher-assignments') {
                app(TeachingAssignments::class)->authorizeSection($r->user(), $old->section_id);
            }
            $allowed = array_keys($this->rules($catalog));
            $input = $r->only($allowed);
            if ($catalog === 'staff' && array_key_exists('user_id', $input) && $input['user_id'] !== $old->user_id) {
                Domain::fail('user_id', 'A staff profile cannot be reassigned to another account.');
            }
            $v = validator([...(array) $old, ...$input], $this->rules($catalog))->validate();
            if (in_array($catalog, ['teacher-assignments', 'coordinator-assignments'])) {
                if ($catalog === 'teacher-assignments') {
                    app(TeachingAssignments::class)->authorizeSection($r->user(), $v['section_id']);
                }
                app(TeachingAssignments::class)->validateAssignment($catalog, $v, $id);
            }
            $v = array_intersect_key($v, $input);
            if ($catalog === 'sessions' && ((isset($v['starts_on']) && $v['starts_on'] !== $old->starts_on) || (isset($v['ends_on']) && $v['ends_on'] !== $old->ends_on)) && DB::table('enrollments')->where('academic_session_id', $id)->exists()) {
                Domain::fail('dates', 'Session dates cannot change after enrollment. Create the next session explicitly.');
            }
            if ($catalog === 'sections' && ((isset($v['class_id']) && $v['class_id'] !== $old->class_id) || (isset($v['academic_session_id']) && $v['academic_session_id'] !== $old->academic_session_id)) && DB::table('enrollments')->where('section_id', $id)->exists()) {
                Domain::fail('section', 'Enrolled sections cannot be moved to a different class or session.');
            }
            if ($catalog === 'staff' && isset($v['joined_on']) && $v['joined_on'] !== $old->joined_on && DB::table('payroll_items')->where('staff_profile_id', $id)->exists()) {
                Domain::fail('joined_on', 'Joining date is locked after payroll generation.');
            }
            DB::table($table)->where('id', $id)->update([...$v, 'updated_at' => now()]);
            Audit::record('catalog_updated', $r->user()->id, $table, $id, ['changed_fields' => array_keys($v)]);
        }, 3);

        return Domain::response(['saved' => true]);
    }

    public function deactivate(Request $r, string $catalog, string $id)
    {
        if ($catalog === 'teacher-assignments') {
            app(TeachingAssignments::class)->requireManager($r->user());
        } else {
            $this->access->require($r->user(), $catalog === 'staff' ? 'staff.manage' : 'school.manage');
        }
        abort_unless(in_array($catalog, ['staff', 'teacher-assignments', 'coordinator-assignments']), 405);
        DB::transaction(function () use ($r, $catalog, $id) {
            $q = DB::table(self::TABLES[$catalog])->where('id', $id);
            $old = $q->lockForUpdate()->firstOrFail();
            if ($catalog === 'teacher-assignments') {
                app(TeachingAssignments::class)->authorizeSection($r->user(), $old->section_id);
            }
            $q->update(['is_active' => false, 'updated_at' => now()]);
            Audit::record('catalog_deactivated', $r->user()->id, self::TABLES[$catalog], $id);
        }, 3);

        return Domain::response(['deactivated' => true]);
    }

    public function createStaff(Request $r)
    {
        $this->access->require($r->user(), 'staff.manage');
        $r->merge(['email' => strtolower(trim((string) $r->input('email')))]);
        $v = $r->validate(['name' => 'required|string|max:200', 'email' => 'required|email|max:254|unique:users,email', 'password' => 'required|string|min:4|max:1024', 'role' => 'required|in:super_admin,school_administrator,coordinator,accountant_clerk,teacher', 'is_active' => 'sometimes|boolean', 'password_confirmation' => 'sometimes|required|same:password']);
        if (in_array($v['role'], ['super_admin', 'school_administrator']) && ! $r->user()->roles()->where('code', 'super_admin')->exists()) {
            abort(403);
        }
        $id = DB::transaction(function () use ($r, $v) {
            $u = User::create(['name' => $v['name'], 'email' => strtolower($v['email']), 'password' => $v['password'], 'is_active' => $v['is_active'] ?? true]);
            $u->roles()->attach(Role::where('code', $v['role'])->firstOrFail());
            Audit::record('staff_account_created', $r->user()->id, 'users', $u->id);

            return $u->id;
        });

        return Domain::response(['id' => $id], 201);
    }
}
