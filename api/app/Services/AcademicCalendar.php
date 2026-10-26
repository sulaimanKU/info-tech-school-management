<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class AcademicCalendar
{
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
