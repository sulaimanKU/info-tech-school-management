<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AcademicCalendar
{
    public const HISTORY_LABEL = 'Preserved weekday policy';

    /** A weekday preference change applies prospectively, preserving historical denominators. */
    public function preservePastWeekdays(array $next): void
    {
        $old = json_decode(DB::table('school_settings')->where('key', 'school_weekdays')->value('value') ?? '[1,2,3,4,5,6]', true);
        $sortedOld = $old;
        $sortedNext = $next;
        sort($sortedOld);
        sort($sortedNext);
        if ($sortedOld === $sortedNext) {
            return;
        }
        $today = now()->toDateString();
        $submittedToday = DB::table('attendance_registers as r')->join('sections as s', 's.id', '=', 'r.section_id')->where('r.date', $today)->where('r.status', 'submitted')->pluck('s.academic_session_id')->all();
        foreach (DB::table('academic_sessions')->where('starts_on', '<=', $today)->orderBy('id')->lockForUpdate()->get() as $session) {
            $through = min($session->ends_on, in_array($session->id, $submittedToday, true) ? $today : now()->subDay()->toDateString());
            $existing = DB::table('school_days')->where('academic_session_id', $session->id)->pluck('date')->flip();
            $pending = [];
            for ($day = Carbon::parse($session->starts_on); $day->toDateString() <= $through; $day->addDay()) {
                $date = $day->toDateString();
                if ($existing->has($date)) {
                    continue;
                }
                $pending[] = ['id' => (string) Str::uuid(), 'academic_session_id' => $session->id, 'date' => $date, 'is_school_day' => in_array($day->dayOfWeekIso, $old, true), 'label' => self::HISTORY_LABEL, 'created_at' => now(), 'updated_at' => now()];
                if (count($pending) === 500) {
                    DB::table('school_days')->insertOrIgnore($pending);
                    $pending = [];
                }
            }
            if ($pending) {
                DB::table('school_days')->insertOrIgnore($pending);
            }
        }
    }

    public function isSchoolDay(string $session, string $date): bool
    {
        $override = DB::table('school_days')->where('academic_session_id', $session)->where('date', $date)->first();
        if ($override) {
            return (bool) $override->is_school_day;
        }
        $row = DB::table('school_settings')->where('key', 'school_weekdays')->first();
        $days = $row ? json_decode($row->value, true) : [1, 2, 3, 4, 5, 6];

        return in_array(Carbon::parse($date)->dayOfWeekIso, $days, true);
    }

    public function roster(string $section, string $date)
    {
        return DB::table('enrollments as e')->join('students as s', 's.id', '=', 'e.student_id')->where('e.section_id', $section)->where('e.starts_on', '<=', $date)->where(fn ($q) => $q->whereNull('e.ends_on')->orWhere('e.ends_on', '>=', $date))->orderBy('e.roll_number')->get(['s.id', 's.full_name', 's.registration_number', 'e.roll_number']);
    }
}
