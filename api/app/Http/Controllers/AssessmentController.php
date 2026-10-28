<?php

namespace App\Http\Controllers;

use App\Services\AcademicCalendar;
use App\Services\AcademicFilters;
use App\Services\NotificationService;
use App\Services\ParentAcademics;
use App\Services\SchoolAccess;
use App\Support\Audit;
use App\Support\Domain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssessmentController extends Controller
{
    public function __construct(private SchoolAccess $access, private AcademicCalendar $calendar) {}

    public function index(Request $r)
    {
        $this->access->academic($r->user());
        abort_if($this->access->isParent($r->user()), 403);
        $filters = app(AcademicFilters::class);
        $v = $filters->values($r, ['status' => 'nullable|in:draft,submitted,approved,published', 'type' => 'nullable|in:monthly_test,unit_test,midterm,final,oral,assignment,practical']);
        $q = DB::table('assessments')->whereIn('section_id', $this->access->sections($r->user())->select('sections.id'));
        if (! $r->user()->hasPermission('academics.review')) {
            $q->where('teacher_user_id', $r->user()->id);
        }if ($section = $r->query('section_id')) {
            $q->where('section_id', $section);
        }

        $this->access->academicRecords($q, $r->user(), 'assessments.section_id', 'assessments.subject_id', 'assessments.date');
        $filters->records($q, $r, 'assessments.section_id', 'assessments.date', $v);
        if (! empty($v['type'])) {
            $q->where('assessments.type', $v['type']);
        }
        if (! empty($v['subject_id'])) {
            $q->where('assessments.subject_id', $v['subject_id']);
        }
        if (! empty($v['status'])) {
            $q->where('assessments.status', $v['status']);
        }
        if (! empty($v['search'])) {
            $q->where('assessments.title', 'like', '%'.$v['search'].'%');
        }

        return Domain::response($q->orderByDesc('date')->paginate(30));
    }

    public function show(Request $r, string $assessment)
    {
        $this->access->academic($r->user());
        $a = DB::table('assessments')->where('id', $assessment)->firstOrFail();
        $this->access->section($r->user(), $a->section_id, $a->subject_id, $a->date);
        $data = (array) $a;
        $data['results'] = DB::table('assessment_results')->where('assessment_id', $assessment)->get();
        $data['students'] = $this->calendar->roster($a->section_id, $a->date);

        return Domain::response($data);
    }

    public function store(Request $r)
    {
        $this->access->require($r->user(), 'academics.write');
        $v = $r->validate(['section_id' => 'required|uuid|exists:sections,id', 'subject_id' => 'required|uuid|exists:subjects,id', 'type' => 'required|in:monthly_test,unit_test,midterm,final,oral,assignment,practical', 'title' => 'required|string|max:200', 'date' => 'required|date', 'maximum_marks' => 'required|numeric|gt:0|max:100000']);
        $this->access->section($r->user(), $v['section_id'], $v['subject_id'], $v['date']);
        $section = DB::table('sections')->where('id', $v['section_id'])->firstOrFail();
        if (! DB::table('grading_rules')->where('academic_session_id', $section->academic_session_id)->where('minimum_percent', 0)->exists()) {
            Domain::fail('grading_rules', 'Configure session grading criteria including a zero-percent band.');
        }
        $id = DB::transaction(function () use ($r, $v, $section) {
            $id = Domain::insert('assessments', [...$v, 'academic_session_id' => $section->academic_session_id, 'teacher_user_id' => $r->user()->id]);
            Audit::record('assessment_created', $r->user()->id, 'assessments', $id);

            return $id;
        });

        return Domain::response(['id' => $id], 201);
    }

    public function marks(Request $r, string $assessment)
    {
        $this->access->require($r->user(), 'academics.write');
        $v = $r->validate(['version' => 'required|integer|min:1', 'status' => 'required|in:draft,submitted', 'results' => 'required|array|max:200', 'results.*.student_id' => 'required|uuid|distinct|exists:students,id', 'results.*.obtained_marks' => 'required|numeric|min:0', 'results.*.remarks' => 'nullable|string|max:2000']);
        $version = DB::transaction(function () use ($r, $v, $assessment) {
            $a = DB::table('assessments')->where('id', $assessment)->lockForUpdate()->firstOrFail();
            $this->access->section($r->user(), $a->section_id, $a->subject_id, $a->date);
            abort_unless($a->teacher_user_id === $r->user()->id || $r->user()->hasPermission('academics.review'), 403);
            $this->access->section($r->user(), $a->section_id, $a->subject_id, $a->date);
            abort_unless($a->version === $v['version'], 409);
            if ($a->status !== 'draft') {
                Domain::fail('status', 'Return the assessment to draft through review before editing.');
            }
            $roster = $this->calendar->roster($a->section_id, $a->date)->pluck('id')->all();
            $ids = array_column($v['results'], 'student_id');
            if (array_diff($ids, $roster) || ($v['status'] === 'submitted' && array_diff($roster, $ids))) {
                Domain::fail('results', 'Submit marks for the complete authorized roster.');
            }
            $rules = DB::table('grading_rules')->where('academic_session_id', $a->academic_session_id)->orderByDesc('minimum_percent')->get();
            DB::table('assessment_results')->where('assessment_id', $assessment)->delete();
            foreach ($v['results'] as $result) {
                if ($result['obtained_marks'] > $a->maximum_marks) {
                    Domain::fail('obtained_marks', 'Marks exceed maximum.');
                }$percent = round(100 * $result['obtained_marks'] / $a->maximum_marks, 2);
                $grade = $rules->first(fn ($rule) => $percent >= $rule->minimum_percent)?->grade;
                if (! $grade) {
                    Domain::fail('grading_rules', 'Configure grading criteria.');
                }Domain::insert('assessment_results', ['assessment_id' => $assessment, 'student_id' => $result['student_id'], 'maximum_marks' => $a->maximum_marks, 'obtained_marks' => $result['obtained_marks'], 'percentage' => $percent, 'grade' => $grade, 'remarks' => $result['remarks'] ?? null]);
            }
            DB::table('assessments')->where('id', $assessment)->update(['status' => $v['status'], 'version' => $a->version + 1, 'updated_at' => now()]);
            Domain::insert('assessment_revisions', ['assessment_id' => $assessment, 'version' => $a->version + 1, 'snapshot' => json_encode($v), 'created_by' => $r->user()->id]);
            Audit::record('marks_'.$v['status'], $r->user()->id, 'assessments', $assessment, ['version' => $a->version + 1]);

            return $a->version + 1;
        });

        return Domain::response(['id' => $assessment, 'version' => $version]);
    }

    public function review(Request $r, string $assessment)
    {
        $this->access->require($r->user(), 'academics.review');
        $v = $r->validate(['action' => 'required|in:approve,publish,return', 'version' => 'required|integer|min:1']);
        $version = DB::transaction(function () use ($r, $v, $assessment) {
            $a = DB::table('assessments')->where('id', $assessment)->lockForUpdate()->firstOrFail();
            $this->access->section($r->user(), $a->section_id, $a->subject_id, $a->date);
            abort_unless($a->version === $v['version'], 409);
            if ($v['action'] === 'approve') {
                abort_unless($a->status === 'submitted', 409);
                abort_if($a->teacher_user_id === $r->user()->id, 403);
                $status = 'approved';
            } elseif ($v['action'] === 'publish') {
                abort_unless($a->status === 'approved', 409);
                $status = 'published';
            } else {
                abort_unless(in_array($a->status, ['submitted', 'approved']), 409);
                $status = 'draft';
            }
            $data = ['status' => $status, 'version' => $a->version + 1, 'updated_at' => now()];
            if ($status === 'approved') {
                $data['approved_by'] = $r->user()->id;
            }if ($status === 'published') {
                $data['published_at'] = now();
            }
            DB::table('assessments')->where('id', $assessment)->update($data);
            Domain::insert('assessment_revisions', ['assessment_id' => $assessment, 'version' => $a->version + 1, 'snapshot' => json_encode(['action' => $v['action'], 'status' => $status]), 'created_by' => $r->user()->id]);
            Audit::record('assessment_'.$status, $r->user()->id, 'assessments', $assessment, ['version' => $a->version + 1]);
            if ($status === 'published') {
                foreach (DB::table('assessment_results')->where('assessment_id', $assessment)->pluck('student_id') as $student) {
                    app(NotificationService::class)->parents($student, 'result', 'assessment', $assessment, 'assessment:'.$assessment.':'.$student);
                }
            }

            return $a->version + 1;
        });

        return Domain::response(['version' => $version]);
    }

    public function student(Request $r, string $student)
    {
        $this->access->academic($r->user());
        $this->access->student($r->user(), $student);
        $q = DB::table('assessment_results as r')->join('assessments as a', 'a.id', '=', 'r.assessment_id')->join('subjects as s', 's.id', '=', 'a.subject_id')->where('r.student_id', $student)->where('a.status', 'published');
        if (! $this->access->isParent($r->user()) && ! $r->user()->hasPermission('academics.review')) {
            $q->whereExists(fn ($a) => $a->selectRaw('1')->from('teacher_assignments as ta')->join('staff_profiles as tp', 'tp.id', '=', 'ta.staff_profile_id')->where('tp.user_id', $r->user()->id)->where('tp.is_active', true)->where('ta.is_active', true)->where(fn ($q) => $q->whereNull('ta.starts_on')->orWhere('ta.starts_on', '<=', now()->toDateString()))->where(fn ($q) => $q->whereNull('ta.ends_on')->orWhere('ta.ends_on', '>=', now()->toDateString()))->whereColumn('ta.section_id', 'a.section_id')->whereColumn('ta.subject_id', 'a.subject_id'));
        }
        $filters = app(AcademicFilters::class);
        $v = $filters->values($r);
        $this->access->academicRecords($q, $r->user(), 'a.section_id', 'a.subject_id', 'a.date');
        if (isset($v['subject_id'])) {
            $q->where('a.subject_id', $v['subject_id']);
        }
        $filters->records($q, $r, 'a.section_id', 'a.date', $v);
        $type = $r->validate(['type' => 'nullable|in:monthly_test,unit_test,midterm,final,oral,assignment,practical']);
        if (isset($type['type'])) {
            $q->where('a.type', $type['type']);
        }
        $items = $q->join('users as teacher', 'teacher.id', '=', 'a.teacher_user_id')->join('academic_sessions as session', 'session.id', '=', 'a.academic_session_id')->join('sections as section', 'section.id', '=', 'a.section_id')->join('classes as class', 'class.id', '=', 'section.class_id')->orderByDesc('a.date')->orderByDesc('a.published_at')->orderBy('a.id')->get(['r.*', 'a.title', 'a.type', 'a.date', 's.name as subject_name', 'a.academic_session_id', 'a.subject_id', 'a.section_id', 'a.published_at', 'teacher.name as teacher_name', 'session.name as session_name', 'class.name as class_name', 'section.name as section_name']);
        if ($this->access->isParent($r->user())) {
            return Domain::response(app(ParentAcademics::class)->present($r, $items))->header('Cache-Control', 'private, no-store');
        }
        $positions = DB::table('school_settings')->where('key', 'class_position_enabled')->value('value');
        if ($positions && json_decode($positions, true) === true) {
            foreach ($items as $item) {
                $item->class_position = 1 + DB::table('assessment_results')->where('assessment_id', $item->assessment_id)->where('obtained_marks', '>', $item->obtained_marks)->count();
            }
        }
        $max = $items->sum('maximum_marks');
        $obtained = $items->sum('obtained_marks');

        return Domain::response(['items' => $items, 'overall_percentage' => $max ? round(100 * $obtained / $max, 2) : null, 'subjects' => $items->groupBy('subject_name')->map(fn ($rows) => ['obtained' => $rows->sum('obtained_marks'), 'maximum' => $rows->sum('maximum_marks'), 'percentage' => $rows->sum('maximum_marks') ? round(100 * $rows->sum('obtained_marks') / $rows->sum('maximum_marks'), 2) : null])]);
    }

    public function grading(Request $r)
    {
        $this->access->require($r->user(), 'school.manage');
        $v = $r->validate(['academic_session_id' => 'required|uuid|exists:academic_sessions,id', 'bands' => 'required|array|min:1|max:20', 'bands.*.minimum_percent' => 'required|numeric|min:0|max:100|distinct', 'bands.*.grade' => 'required|string|max:20']);
        if (! collect($v['bands'])->contains(fn ($b) => (float) $b['minimum_percent'] === 0.0)) {
            Domain::fail('bands', 'Include a zero-percent band.');
        }DB::transaction(function () use ($r, $v) {
            DB::table('academic_sessions')->where('id', $v['academic_session_id'])->lockForUpdate()->firstOrFail();
            DB::table('grading_rules')->where('academic_session_id', $v['academic_session_id'])->delete();
            foreach ($v['bands'] as $b) {
                Domain::insert('grading_rules', ['academic_session_id' => $v['academic_session_id'], ...$b]);
            }Audit::record('grading_rules_updated', $r->user()->id, 'academic_sessions', $v['academic_session_id']);
        });

        return Domain::response(['saved' => true]);
    }
}
