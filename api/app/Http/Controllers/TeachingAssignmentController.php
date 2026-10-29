<?php

namespace App\Http\Controllers;

use App\Services\AcademicFilters;
use App\Services\TeachingAssignments;
use App\Support\Domain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeachingAssignmentController extends Controller
{
    public function index(Request $r, TeachingAssignments $assignments)
    {
        $assignments->requireManager($r->user());
        $v = app(AcademicFilters::class)->values($r, ['is_active' => 'nullable|boolean', 'on_date' => 'nullable|date', 'status' => 'nullable|in:active,inactive']);
        $q = $assignments->query($r->user());
        foreach (['class_id' => 'sec.class_id', 'academic_session_id' => 'sec.academic_session_id', 'section_id' => 'ta.section_id', 'subject_id' => 'ta.subject_id', 'is_active' => 'ta.is_active'] as $key => $column) {
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
