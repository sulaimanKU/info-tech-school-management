<?php

namespace App\Http\Controllers;

use App\Services\SchoolAccess;
use App\Support\Audit;
use App\Support\Domain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CoordinatorController extends Controller
{
    public function index(Request $r, SchoolAccess $access)
    {
        $access->require($r->user(), 'academics.review');

        $sections = $access->sections($r->user())->select('sections.id');
        $q = $this->records($r, $access);
        $page = $q->orderByDesc('created_at')->paginate(30);

        return Domain::response(['items' => $page->items(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'submitted_assessments' => DB::table('assessments')->whereIn('section_id', clone $sections)->where('status', 'submitted')->count(), 'unreviewed_diaries' => DB::table('diary_entries')->whereIn('section_id', clone $sections)->where('status', 'published')->whereNull('reviewed_at')->count(), 'attendance_today' => DB::table('attendance_registers as a')->join('sections as s', 's.id', '=', 'a.section_id')->whereIn('a.section_id', clone $sections)->where('a.date', now()->toDateString())->get(['a.id', 'a.section_id', 's.name', 'a.status', 'a.version'])]);
    }

    private function records(Request $r, SchoolAccess $access)
    {
        $q = DB::table('coordination_records');
        if (! $r->user()->hasPermission('students.read.all')) {
            $q->where(fn ($q) => $q->whereNull('section_id')->orWhereIn('section_id', $access->sections($r->user())->select('sections.id')))
                ->where(fn ($q) => $q->whereNull('student_id')->orWhereIn('student_id', $access->students($r->user())->select('students.id')))
                ->where(fn ($q) => $q->whereNotNull('section_id')->orWhereNotNull('student_id'));
        }

        return $q;
    }

    public function save(Request $r, SchoolAccess $access, ?string $record = null)
    {
        $access->require($r->user(), 'academics.review');
        $v = $r->validate(['section_id' => 'nullable|uuid|exists:sections,id', 'student_id' => 'nullable|uuid|exists:students,id', 'assigned_to' => 'nullable|uuid|exists:users,id', 'kind' => 'required|in:syllabus,learning_gap,classroom,follow_up,issue,parent_communication', 'title' => 'required|string|max:200', 'notes' => 'required|string|max:5000', 'progress_percent' => 'nullable|integer|min:0|max:100', 'follow_up_on' => 'nullable|date', 'status' => 'required|in:open,in_progress,resolved']);
        if (! empty($v['section_id'])) {
            $access->section($r->user(), $v['section_id']);
        }
        if (! empty($v['student_id'])) {
            $access->student($r->user(), $v['student_id']);
        }
        if (! $r->user()->hasPermission('students.read.all') && empty($v['section_id']) && empty($v['student_id'])) {
            Domain::fail('section_id', 'Select an assigned section or student.');
        }
        if (! empty($v['section_id']) && ! empty($v['student_id'])) {
            abort_unless(DB::table('enrollments')->where('student_id', $v['student_id'])->where('section_id', $v['section_id'])->exists(), 422);
        }
        if ($record) {
            $this->records($r, $access)->where('id', $record)->firstOrFail();
        }
        $id = DB::transaction(function () use ($r, $record, $v) {
            if ($record) {
                DB::table('coordination_records')->where('id', $record)->firstOrFail();
                DB::table('coordination_records')->where('id', $record)->update([...$v, 'updated_at' => now()]);
                $id = $record;
            } else {
                $id = Domain::insert('coordination_records', [...$v, 'created_by' => $r->user()->id]);
            }Audit::record('coordination_'.$v['status'], $r->user()->id, 'coordination_records', $id);

            return $id;
        });

        return Domain::response(['id' => $id]);
    }
}
