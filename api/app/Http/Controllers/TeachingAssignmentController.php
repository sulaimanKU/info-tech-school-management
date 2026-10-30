<?php

namespace App\Http\Controllers;

use App\Services\AcademicFilters;
use App\Services\TeachingAssignments;
use App\Support\Audit;
use App\Support\Domain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeachingAssignmentController extends Controller
{
    public function index(Request $r, TeachingAssignments $assignments)
    {
        $assignments->requireManager($r->user());
        $v = app(AcademicFilters::class)->values($r, ['staff_profile_id' => 'nullable|uuid|exists:staff_profiles,id', 'is_active' => 'nullable|boolean', 'on_date' => 'nullable|date', 'status' => 'nullable|in:active,inactive']);
        $q = $assignments->query($r->user());
        foreach (['staff_profile_id' => 'ta.staff_profile_id', 'class_id' => 'sec.class_id', 'academic_session_id' => 'sec.academic_session_id', 'section_id' => 'ta.section_id', 'subject_id' => 'ta.subject_id', 'is_active' => 'ta.is_active'] as $key => $column) {
            if (isset($v[$key])) {
                $q->where($column, $v[$key]);
            }
        }
        if (isset($v['status'])) {
            $q->where('ta.is_active', $v['status'] === 'active');
        }
        if (isset($v['on_date'])) {
            $q->where('p.is_active', true)->where('u.is_active', true)->whereExists(fn ($q) => $q->selectRaw('1')->from('user_role_assignments as ur')->join('roles as r', 'r.id', '=', 'ur.role_id')->whereColumn('ur.user_id', 'u.id')->where('r.code', 'teacher'))->where('p.joined_on', '<=', $v['on_date'])->where(fn ($q) => $q->whereNull('ta.starts_on')->orWhere('ta.starts_on', '<=', $v['on_date']))->where(fn ($q) => $q->whereNull('ta.ends_on')->orWhere('ta.ends_on', '>=', $v['on_date']));
        }
        if (! empty($v['search'])) {
            $q->where(fn ($q) => $q->where('u.name', 'like', '%'.$v['search'].'%')->orWhere('u.account_code', 'like', '%'.$v['search'].'%')->orWhere('p.employee_number', 'like', '%'.$v['search'].'%'));
        }

        return Domain::response($q->orderBy('c.sort_order')->orderBy('sec.name')->orderBy('u.name')->paginate(30, ['ta.*', 'u.id as teacher_user_id', 'u.name as teacher_name', 'u.account_code', 'p.employee_number', 'c.name as class_name', 'sec.class_id', 'sec.academic_session_id', 'sec.name as section_name', 'y.name as session_name', 'sub.name as subject_name']));
    }

    public function store(Request $r, TeachingAssignments $assignments)
    {
        $assignments->requireManager($r->user());
        $v = $r->validate([
            'staff_profile_id' => 'required|uuid|exists:staff_profiles,id',
            'academic_session_id' => 'required|uuid|exists:academic_sessions,id',
            'assignments' => 'required|array|list|min:1|max:50',
            'assignments.*' => 'required|array:section_id,subject_id,is_class_teacher,starts_on,ends_on',
            'assignments.*.section_id' => 'required|uuid|exists:sections,id',
            'assignments.*.subject_id' => 'nullable|uuid|exists:subjects,id',
            'assignments.*.is_class_teacher' => 'required|boolean',
            'assignments.*.starts_on' => 'nullable|date_format:Y-m-d',
            'assignments.*.ends_on' => 'nullable|date_format:Y-m-d|after_or_equal:assignments.*.starts_on',
        ]);
        $ids = DB::transaction(function () use ($r, $assignments, $v) {
            // The same profile/section locks and validation protect single and bulk writes.
            DB::table('staff_profiles')->where('id', $v['staff_profile_id'])->lockForUpdate()->firstOrFail();
            $sections = collect($v['assignments'])->pluck('section_id')->unique()->sort()->values();
            foreach ($sections as $section) {
                $assignments->authorizeSection($r->user(), $section);
                $row = DB::table('sections')->where('id', $section)->lockForUpdate()->firstOrFail();
                if ($row->academic_session_id !== $v['academic_session_id']) {
                    Domain::fail('academic_session_id', 'Every assignment must belong to the selected academic session.');
                }
            }
            $ids = [];
            foreach ($v['assignments'] as $index => $row) {
                $row = ['staff_profile_id' => $v['staff_profile_id'], 'is_active' => true, ...$row];
                try {
                    $assignments->validateAssignment('teacher-assignments', $row);
                } catch (ValidationException $e) {
                    throw ValidationException::withMessages(collect($e->errors())->mapWithKeys(fn ($messages, $field) => ["assignments.$index.$field" => $messages])->all());
                }
                $id = Domain::insert('teacher_assignments', $row);
                Audit::record('catalog_created', $r->user()->id, 'teacher_assignments', $id);
                $ids[] = $id;
            }

            return $ids;
        }, 3);

        return Domain::response(['ids' => $ids, 'count' => count($ids)], 201);
    }

    public function teachers(Request $r, TeachingAssignments $assignments)
    {
        $assignments->requireManager($r->user());
        $v = $r->validate(['search' => 'nullable|string|max:100', 'page' => 'nullable|integer|min:1']);
        $q = DB::table('staff_profiles as p')->join('users as u', 'u.id', '=', 'p.user_id')->where('p.is_active', true)->where('u.is_active', true)
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('user_role_assignments as ur')->join('roles as r', 'r.id', '=', 'ur.role_id')->whereColumn('ur.user_id', 'u.id')->where('r.code', 'teacher'));
        if (! empty($v['search'])) {
            $q->where(fn ($q) => $q->where('u.name', 'like', '%'.$v['search'].'%')->orWhere('p.employee_number', 'like', '%'.$v['search'].'%')->orWhere('u.account_code', 'like', '%'.$v['search'].'%'));
        }

        return Domain::response($q->orderBy('u.name')->paginate(30, ['p.id', 'p.user_id', 'p.employee_number', 'u.account_code', 'u.name']));
    }
}
