<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class AcademicSummary
{
    public function __construct(private AcademicCalendar $calendar) {}

    public function attendanceMany(array $ids, string $month): array
    {
        if (! $ids) {
            return [];
        }
        $start = Carbon::parse($month.'-01');
        $end = $start->copy()->endOfMonth()->min(now())->toDateString();
        $records = DB::table('attendance_records as a')->join('attendance_registers as r', 'r.id', '=', 'a.attendance_register_id')->whereIn('a.student_id', $ids)->where('r.status', 'submitted')->whereBetween('a.date', [$start->toDateString(), $end])->orderBy('a.date')->get(['a.student_id', 'a.date', 'a.status'])->groupBy('student_id');
        $enrollments = DB::table('enrollments')->whereIn('student_id', $ids)->orderByDesc('starts_on')->get()->groupBy('student_id');
        $sessions = $enrollments->flatten(1)->pluck('academic_session_id')->unique();
        $overrides = DB::table('school_days')->whereIn('academic_session_id', $sessions)->whereBetween('date', [$start->toDateString(), $end])->get()->keyBy(fn ($d) => $d->academic_session_id.'/'.$d->date);
        $weekdays = json_decode(DB::table('school_settings')->where('key', 'school_weekdays')->value('value') ?? '[1,2,3,4,5,6]', true);
        $result = [];
        foreach ($ids as $id) {
            $rows = $records->get($id, collect());
            $schoolDays = 0;
            $holidays = [];
            for ($day = $start->copy(); $day->toDateString() <= $end; $day->addDay()) {
                $date = $day->toDateString();
                $e = $enrollments->get($id, collect())->first(fn ($e) => $e->starts_on <= $date && (! $e->ends_on || $e->ends_on >= $date));
                if (! $e) {
                    continue;
                }$override = $overrides->get($e->academic_session_id.'/'.$date);
                $isSchool = $override ? (bool) $override->is_school_day : in_array($day->dayOfWeekIso, $weekdays, true);
                if ($isSchool) {
                    $schoolDays++;
                } else {
                    $holidays[] = ['date' => $date, 'label' => $override?->label];
                }
            }
            $counts = [];
            foreach (['present', 'absent', 'late', 'excused', 'school_holiday'] as $status) {
                $counts[$status] = $rows->where('status', $status)->count();
            }
            $recorded = $rows->where('status', '!=', 'school_holiday')->count();
            $result[$id] = ['items' => $rows->map(fn ($row) => (object) ['date' => $row->date, 'status' => $row->status])->values(), 'school_days' => $schoolDays, 'recorded_days' => $recorded, 'missing_days' => max(0, $schoolDays - $recorded), 'attendance_percentage' => $schoolDays && $recorded ? round(100 * ($counts['present'] + $counts['late']) / $schoolDays, 2) : null, 'provisional' => $recorded < $schoolDays, 'counts' => $counts, 'holidays' => $holidays];
        }

        return $result;
    }

    public function attendance(string $student, string $month, ?User $user = null): array
    {
        if (! $user || app(SchoolAccess::class)->isParent($user) || $user->hasPermission('students.read.all')) {
            return $this->attendanceMany([$student], $month)[$student];
        }
        $start = Carbon::parse($month.'-01');
        $end = $start->copy()->endOfMonth()->min(now())->toDateString();
        $records = DB::table('attendance_records as a')->join('attendance_registers as r', 'r.id', '=', 'a.attendance_register_id')->where('a.student_id', $student)->where('r.status', 'submitted')->whereBetween('a.date', [$start->toDateString(), $end])->orderBy('a.date')->get(['a.date', 'a.status', 'r.section_id']);
        $enrollments = DB::table('enrollments')->where('student_id', $student)->get();
        $access = app(SchoolAccess::class);
        $scoped = $user && ! $access->isParent($user) && ! $user->hasPermission('students.read.all');
        if ($scoped) {
            $records = $records->filter(fn ($row) => $access->sections($user, $row->date)->where('sections.id', $row->section_id)->exists());
        }
        $records = $records->map(fn ($r) => (object) ['date' => $r->date, 'status' => $r->status]);
        $schoolDays = 0;
        for ($day = $start->copy(); $day->toDateString() <= $end; $day->addDay()) {
            $date = $day->toDateString();
            $e = $enrollments->first(fn ($e) => $e->starts_on <= $date && (! $e->ends_on || $e->ends_on >= $date));
            if ($e && (! $scoped || $access->sections($user, $date)->where('sections.id', $e->section_id)->exists()) && $this->calendar->isSchoolDay($e->academic_session_id, $date)) {
                $schoolDays++;
            }
        }
        $attended = $records->whereIn('status', ['present', 'late'])->count();
        $recorded = $records->where('status', '!=', 'school_holiday')->count();

        return ['items' => $records, 'school_days' => $schoolDays, 'recorded_days' => $recorded, 'missing_days' => max(0, $schoolDays - $recorded), 'attendance_percentage' => $schoolDays && $recorded ? round(100 * $attended / $schoolDays, 2) : null, 'provisional' => $recorded < $schoolDays];
    }
}
