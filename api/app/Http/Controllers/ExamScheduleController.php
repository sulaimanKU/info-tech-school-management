<?php

namespace App\Http\Controllers;

use App\Services\SchoolAccess;
use App\Support\Audit;
use App\Support\Domain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExamScheduleController extends Controller
{
    public function __construct(private SchoolAccess $access) {}

    public function index(Request $r)
    {
        $this->access->academic($r->user());
        $q = DB::table('exam_schedules as e')->join('subjects as s', 's.id', '=', 'e.subject_id')->join('sections as sec', 'sec.id', '=', 'e.section_id')->join('classes as c', 'c.id', '=', 'sec.class_id');
        if ($this->access->isParent($r->user())) {
            $q->where('e.status', 'published')->whereIn('e.section_id', DB::table('enrollments')->whereIn('student_id', $this->access->students($r->user())->select('students.id'))->where('status', 'active')->select('section_id'));
        } else {
            $q->whereIn('e.section_id', $this->access->sections($r->user())->select('sections.id'));
        }if ($student = $r->query('student_id')) {
            $this->access->student($r->user(), $student);
            $q->whereIn('e.section_id', DB::table('enrollments')->where('student_id', $student)->select('section_id'));
        }

        $this->access->academicRecords($q, $r->user(), 'e.section_id', 'e.subject_id', 'e.date');

        return Domain::response(['items' => $q->orderBy('e.date')->orderBy('e.starts_at')->get(['e.*', 's.name as subject_name', 'sec.name as section_name', 'c.name as class_name'])]);
    }

    public function save(Request $r, ?string $exam = null)
    {
        $this->access->require($r->user(), 'academics.review');
        $v = $r->validate(['section_id' => 'required|uuid|exists:sections,id', 'subject_id' => 'required|uuid|exists:subjects,id', 'title' => 'required|string|max:200', 'date' => 'required|date', 'starts_at' => 'required|date_format:H:i', 'ends_at' => 'required|date_format:H:i|after:starts_at', 'room' => 'nullable|string|max:100', 'status' => 'required|in:draft,published,cancelled', 'version' => 'required|integer|min:0']);
        $this->access->section($r->user(), $v['section_id'], $v['subject_id'], $v['date']);

        return DB::transaction(function () use ($r, $v, $exam) {
            DB::table('sections')->where('id', $v['section_id'])->lockForUpdate()->firstOrFail();
            $section = DB::table('sections as s')->join('academic_sessions as a', 'a.id', '=', 's.academic_session_id')->where('s.id', $v['section_id'])->first(['a.starts_on', 'a.ends_on', 's.class_id', 's.academic_session_id']);
            if ($v['date'] < $section->starts_on || $v['date'] > $section->ends_on) {
                Domain::fail('date', 'Exam date must fall inside the session.');
            }if (! DB::table('class_subject_assignments')->where('class_id', $section->class_id)->where('academic_session_id', $section->academic_session_id)->where('subject_id', $v['subject_id'])->exists()) {
                Domain::fail('subject_id', 'Assign the subject to this class/session first.');
            }$old = $exam ? DB::table('exam_schedules')->where('id', $exam)->lockForUpdate()->firstOrFail() : null;
            abort_unless(($old?->version ?? 0) === $v['version'], 409);
            if ($old) {
                $this->access->section($r->user(), $old->section_id);
                if ($old->section_id !== $v['section_id']) {
                    Domain::fail('section_id', 'Create a new schedule to change section.');
                }
            }if ($v['status'] !== 'cancelled' && DB::table('exam_schedules')->where('section_id', $v['section_id'])->where('date', $v['date'])->where('status', '!=', 'cancelled')->when($exam, fn ($q) => $q->where('id', '!=', $exam))->where('starts_at', '<', $v['ends_at'])->where('ends_at', '>', $v['starts_at'])->exists()) {
                Domain::fail('starts_at', 'Exam time overlaps another schedule.');
            }$data = [...$v, 'version' => $v['version'] + 1];
            if ($old) {
                $id = $old->id;
                DB::table('exam_schedules')->where('id', $id)->update([...$data, 'updated_at' => now()]);
            } else {
                $id = Domain::insert('exam_schedules', [...$data, 'created_by' => $r->user()->id]);
            }Audit::record('exam_schedule_'.$v['status'], $r->user()->id, 'exam_schedules', $id, ['version' => $data['version']]);

            return Domain::response(['id' => $id, 'version' => $data['version']]);
        });
    }
}
