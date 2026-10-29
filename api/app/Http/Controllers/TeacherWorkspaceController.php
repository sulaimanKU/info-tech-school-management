<?php

namespace App\Http\Controllers;

use App\Services\AcademicCalendar;
use App\Services\SchoolAccess;
use App\Services\SchoolSettings;
use App\Services\TeachingAssignments;
use App\Support\Domain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeacherWorkspaceController extends Controller
{
    public function show(Request $r, SchoolAccess $access, TeachingAssignments $assignments)
    {
        $access->academic($r->user());
        abort_unless($r->user()->roles()->where('code', 'teacher')->exists(), 403);
        $v = $r->validate(['academic_session_id' => 'nullable|uuid|exists:academic_sessions,id']);
        $today = now()->toDateString();
        $rows = $assignments->query($r->user())->where('p.user_id', $r->user()->id)->where('p.is_active', true)->where('u.is_active', true)
            ->where('p.joined_on', '<=', $today)->where('ta.is_active', true)
            ->where('y.starts_on', '<=', $today)->where('y.ends_on', '>=', $today)
            ->where(fn ($q) => $q->whereNull('ta.starts_on')->orWhere('ta.starts_on', '<=', $today))
            ->where(fn ($q) => $q->whereNull('ta.ends_on')->orWhere('ta.ends_on', '>=', $today))
            ->when($v['academic_session_id'] ?? null, fn ($q, $id) => $q->where('sec.academic_session_id', $id))
            ->orderBy('c.sort_order')->orderBy('sec.name')->get(['ta.id', 'ta.section_id', 'ta.subject_id', 'ta.is_class_teacher', 'ta.starts_on', 'ta.ends_on', 'sec.class_id', 'sec.name as section_name', 'sec.academic_session_id', 'c.name as class_name', 'y.name as session_name', 'y.starts_on as session_starts_on', 'y.ends_on as session_ends_on', 'sub.name as subject_name']);
        $ids = $rows->pluck('section_id')->unique();
        $counts = DB::table('enrollments')->whereIn('section_id', $ids)->where('starts_on', '<=', $today)->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $today))->selectRaw('section_id, count(distinct student_id) as total')->groupBy('section_id')->pluck('total', 'section_id');
        $registers = DB::table('attendance_registers')->whereIn('section_id', $ids)->where('date', $today)->get()->keyBy('section_id');
        $days = $rows->pluck('academic_session_id')->unique()->mapWithKeys(fn ($id) => [$id => app(AcademicCalendar::class)->isSchoolDay($id, $today)]);
        $sections = $rows->groupBy('section_id')->map(function ($a, $section) use ($today, $counts, $registers, $days) {
            $first = $a->first();
            $inSession = $first->session_starts_on <= $today && $first->session_ends_on >= $today;
            $count = $inSession ? (int) $counts->get($section, 0) : 0;
            $schoolDay = $inSession && $days[$first->academic_session_id];

            return ['section_id' => $section, 'class_id' => $first->class_id, 'academic_session_id' => $first->academic_session_id, 'class_name' => $first->class_name, 'section_name' => $first->section_name, 'session_name' => $first->session_name, 'student_count' => $count, 'is_school_day' => $schoolDay,
                'attendance_status' => $registers->get($section)?->status ?? ($schoolDay && $count ? 'pending' : 'not_applicable'),
                'subjects' => $a->whereNotNull('subject_id')->unique('subject_id')->map(fn ($row) => ['id' => $row->subject_id, 'name' => $row->subject_name])->values(), 'is_class_teacher' => $a->contains(fn ($row) => (bool) $row->is_class_teacher)];
        })->values();
        $academic = DB::table('assessments as a')->where('a.teacher_user_id', $r->user()->id)->whereIn('a.section_id', $ids);
        $access->academicRecords($academic, $r->user(), 'a.section_id', 'a.subject_id', 'a.date');
        $exams = DB::table('exam_schedules as e')->join('subjects as s', 's.id', '=', 'e.subject_id')->whereIn('e.section_id', $ids)->where('e.status', 'published')->whereBetween('e.date', [$today, now()->addDays(30)->toDateString()]);
        $access->academicRecords($exams, $r->user(), 'e.section_id', 'e.subject_id', 'e.date');

        return Domain::response(['date' => $today, 'current_academic_session_id' => app(SchoolSettings::class)->currentSession(), 'schedule_available' => false,
            'sections' => $sections, 'assignment_count' => $rows->count(), 'student_count' => $sections->sum('student_count'),
            'pending_attendance_sections' => $sections->where('is_school_day', true)->whereIn('attendance_status', ['pending', 'draft'])->count(),
            'pending_assessments' => (clone $academic)->where('a.status', 'draft')->count(),
            'assessments' => $academic->join('subjects as s', 's.id', '=', 'a.subject_id')->whereNotIn('a.status', ['published'])->orderBy('a.date')->limit(10)->get(['a.id', 'a.title', 'a.date', 'a.status', 's.name as subject_name']),
            'upcoming_exams' => $exams->orderBy('e.date')->orderBy('e.starts_at')->limit(10)->get(['e.id', 'e.title', 'e.date', 'e.starts_at', 'e.ends_at', 'e.section_id', 's.name as subject_name']),
            'recent_diary' => app(DiaryController::class)->visible($r)->where('d.teacher_user_id', $r->user()->id)->orderByDesc('d.date')->limit(5)->get(['d.id', 'd.topic', 'd.date', 'd.status']),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function students(Request $r, SchoolAccess $access, string $section)
    {
        $access->academic($r->user());
        abort_unless($r->user()->roles()->where('code', 'teacher')->exists(), 403);
        $access->section($r->user(), $section);
        $v = $r->validate(['search' => 'nullable|string|max:100', 'page' => 'nullable|integer|min:1']);
        $today = now()->toDateString();
        $q = DB::table('enrollments as e')->join('students as s', 's.id', '=', 'e.student_id')->join('academic_sessions as y', 'y.id', '=', 'e.academic_session_id')
            ->where('e.section_id', $section)->where('e.starts_on', '<=', $today)->where(fn ($q) => $q->whereNull('e.ends_on')->orWhere('e.ends_on', '>=', $today))->where('y.starts_on', '<=', $today)->where('y.ends_on', '>=', $today);
        if (! empty($v['search'])) {
            $q->where(fn ($q) => $q->where('s.full_name', 'like', '%'.$v['search'].'%')->orWhere('s.registration_number', 'like', '%'.$v['search'].'%')->orWhere('e.roll_number', $v['search']));
        }

        return Domain::response($q->orderBy('e.roll_number')->paginate(30, ['s.id', 's.full_name', 's.registration_number', 's.photo_file_id', 'e.roll_number']));
    }

    public function roster(Request $r, SchoolAccess $access, AcademicCalendar $calendar)
    {
        $access->academic($r->user());
        $v = $r->validate(['section_id' => 'required|uuid|exists:sections,id', 'date' => 'required|date']);
        $access->section($r->user(), $v['section_id'], null, $v['date']);
        $session = DB::table('sections as s')->join('academic_sessions as y', 'y.id', '=', 's.academic_session_id')->where('s.id', $v['section_id'])->first(['y.starts_on', 'y.ends_on']);
        if ($v['date'] < $session->starts_on || $v['date'] > $session->ends_on) {
            Domain::fail('date', 'Choose a date inside the academic session.');
        }

        return Domain::response(['students' => $calendar->roster($v['section_id'], $v['date'])])->header('Cache-Control', 'private, no-store');
    }
}
