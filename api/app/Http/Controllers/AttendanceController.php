<?php

namespace App\Http\Controllers;

use App\Services\AcademicCalendar;
use App\Services\AcademicFilters;
use App\Services\AcademicSummary;
use App\Services\SchoolAccess;
use App\Support\Audit;
use App\Support\Domain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    public function __construct(private SchoolAccess $access, private AcademicCalendar $calendar) {}

    public function register(Request $r)
    {
        $this->access->academic($r->user());
        $v = app(AcademicFilters::class)->values($r, ['section_id' => 'required|uuid|exists:sections,id', 'date' => 'required|date|before_or_equal:today']);
        $this->access->section($r->user(), $v['section_id'], null, $v['date']);
        $session = DB::table('sections as s')->join('academic_sessions as y', 'y.id', '=', 's.academic_session_id')->where('s.id', $v['section_id'])->first(['s.academic_session_id', 'y.starts_on', 'y.ends_on']);
        if ($v['date'] < $session->starts_on || $v['date'] > $session->ends_on) {
            Domain::fail('date', 'Attendance date must fall inside the academic session.');
        }
        $register = DB::table('attendance_registers')->where('section_id', $v['section_id'])->where('date', $v['date'])->first();

        return Domain::response(['is_school_day' => $this->calendar->isSchoolDay($session->academic_session_id, $v['date']), 'register' => $register, 'students' => $this->calendar->roster($v['section_id'], $v['date']), 'records' => $register ? DB::table('attendance_records')->where('attendance_register_id', $register->id)->get(['student_id', 'status']) : []]);
    }

    public function save(Request $r)
    {
        $this->access->require($r->user(), 'academics.write');
        $v = $r->validate(['section_id' => 'required|uuid|exists:sections,id', 'date' => 'required|date|before_or_equal:today', 'status' => 'required|in:draft,submitted', 'version' => 'required|integer|min:0', 'request_id' => 'required|uuid', 'records' => 'required|array|max:200', 'records.*.student_id' => 'required|uuid|distinct|exists:students,id', 'records.*.status' => 'required|in:present,absent,late,excused,school_holiday']);
        $this->access->section($r->user(), $v['section_id'], null, $v['date']);
        $result = DB::transaction(function () use ($r, $v) {
            $section = DB::table('sections')->where('id', $v['section_id'])->lockForUpdate()->firstOrFail();
            $session = DB::table('academic_sessions')->where('id', $section->academic_session_id)->lockForUpdate()->firstOrFail();
            if ($v['date'] < $session->starts_on || $v['date'] > $session->ends_on) {
                Domain::fail('date', 'Date is outside the session.');
            }
            $roster = $this->calendar->roster($section->id, $v['date'])->pluck('id')->all();
            $ids = array_column($v['records'], 'student_id');
            if (array_diff($ids, $roster) || ($v['status'] === 'submitted' && array_diff($roster, $ids))) {
                Domain::fail('records', 'Use the complete authorized roster when submitting.');
            }
            $day = $this->calendar->isSchoolDay($section->academic_session_id, $v['date']);
            foreach ($v['records'] as $row) {
                if (($row['status'] === 'school_holiday') === $day) {
                    Domain::fail('records', 'Holiday status must match the configured school calendar.');
                }
            }
            $old = DB::table('attendance_registers')->where('section_id', $section->id)->where('date', $v['date'])->lockForUpdate()->first();
            $hash = hash('sha256', json_encode([$v['status'], $v['records']]));
            if ($old && $old->last_request_id === $v['request_id']) {
                abort_unless(hash_equals($old->payload_hash, $hash), 409);

                return ['id' => $old->id, 'version' => $old->version, 'status' => $old->status];
            }
            if (($old?->version ?? 0) !== $v['version']) {
                abort(409);
            }
            if ($old?->status === 'submitted') {
                $this->access->require($r->user(), 'attendance.correct');
                if ($v['status'] !== 'submitted') {
                    Domain::fail('status', 'Submitted attendance cannot become a draft.');
                }
            }
            $data = ['section_id' => $section->id, 'date' => $v['date'], 'status' => $v['status'], 'version' => ($old?->version ?? 0) + 1, 'last_request_id' => $v['request_id'], 'payload_hash' => $hash, 'updated_by' => $r->user()->id];
            if ($old) {
                $id = $old->id;
                DB::table('attendance_registers')->where('id', $id)->update([...$data, 'updated_at' => now()]);
                DB::table('attendance_records')->where('attendance_register_id', $id)->delete();
            } else {
                $id = Domain::insert('attendance_registers', [...$data, 'created_by' => $r->user()->id]);
            }
            foreach ($v['records'] as $row) {
                Domain::insert('attendance_records', ['attendance_register_id' => $id, 'student_id' => $row['student_id'], 'date' => $v['date'], 'status' => $row['status']]);
            }
            Domain::insert('attendance_revisions', ['attendance_register_id' => $id, 'version' => $data['version'], 'snapshot' => json_encode(['status' => $v['status'], 'records' => $v['records']]), 'created_by' => $r->user()->id]);
            Audit::record('attendance_'.$v['status'], $r->user()->id, 'attendance_registers', $id, ['version' => $data['version'], 'count' => count($ids)]);

            return ['id' => $id, 'version' => $data['version'], 'status' => $data['status']];
        });

        return Domain::response($result);
    }

    public function history(Request $r, string $student)
    {
        $this->access->academic($r->user());
        $this->access->student($r->user(), $student);
        $v = $r->validate(['month' => 'required|date_format:Y-m', 'status' => 'nullable|in:present,absent,late,excused,school_holiday', 'from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from']);

        $result = app(AcademicSummary::class)->attendance($student, $v['month'], $r->user());
        // Month statistics keep their documented denominator; filters narrow the displayed records.
        $result['items'] = $result['items']->filter(fn ($row) => (! isset($v['status']) || $row->status === $v['status']) && (! isset($v['from']) || $row->date >= $v['from']) && (! isset($v['to']) || $row->date <= $v['to']))->values();

        return Domain::response($result);
    }

    public function calendar(Request $r)
    {
        $this->access->require($r->user(), 'school.manage');
        $v = $r->validate(['academic_session_id' => 'required|uuid|exists:academic_sessions,id', 'date' => 'required|date', 'is_school_day' => 'required|boolean', 'label' => 'nullable|string|max:200']);
        $id = DB::transaction(function () use ($r, $v) {
            $session = DB::table('academic_sessions')->where('id', $v['academic_session_id'])->lockForUpdate()->firstOrFail();
            if ($v['date'] < $session->starts_on || $v['date'] > $session->ends_on) {
                Domain::fail('date', 'Calendar date must fall inside the academic session.');
            }
            $hasSubmitted = DB::table('attendance_registers as r')->join('sections as s', 's.id', '=', 'r.section_id')->where('s.academic_session_id', $session->id)->where('r.date', $v['date'])->where('r.status', 'submitted')->exists();
            if ($hasSubmitted && $this->calendar->isSchoolDay($session->id, $v['date']) !== (bool) $v['is_school_day']) {
                Domain::fail('is_school_day', 'The school-day status is locked after attendance submission.');
            }
            $old = DB::table('school_days')->where('academic_session_id', $v['academic_session_id'])->where('date', $v['date'])->first();
            if ($old) {
                DB::table('school_days')->where('id', $old->id)->update([...$v, 'updated_at' => now()]);
                $id = $old->id;
            } else {
                $id = Domain::insert('school_days', $v);
            }Audit::record('calendar_updated', $r->user()->id, 'school_days', $id);

            return $id;
        });

        return Domain::response(['id' => $id]);
    }

    public function revisions(Request $r, string $register)
    {
        $this->access->academic($r->user());
        $row = DB::table('attendance_registers')->where('id', $register)->firstOrFail();
        $this->access->section($r->user(), $row->section_id);

        return Domain::response(['items' => DB::table('attendance_revisions')->where('attendance_register_id', $register)->orderBy('version')->get()]);
    }
}
