<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/** Presentation only; callers must authorize the account before reading it. */
final class StaffProfileData
{
    public function assignments(?object $profile): array
    {
        if (! $profile) {
            return [];
        }
        $rows = [];
        foreach (['teacher_assignments' => 'teacher', 'coordinator_assignments' => 'coordinator'] as $table => $kind) {
            $query = DB::table($table.' as a')->join('sections as s', 's.id', '=', 'a.section_id')
                ->join('classes as c', 'c.id', '=', 's.class_id')->join('academic_sessions as y', 'y.id', '=', 's.academic_session_id')
                ->where('a.staff_profile_id', $profile->id);
            if ($kind === 'teacher') {
                $query->leftJoin('subjects as sub', 'sub.id', '=', 'a.subject_id');
            }
            foreach ($query->orderByDesc('y.starts_on')->orderBy('c.sort_order')->orderBy('s.name')
                ->get(['a.*', 's.name as section_name', 's.class_id', 's.academic_session_id', 'c.name as class_name', 'y.name as session_name', ...($kind === 'teacher' ? ['sub.name as subject_name'] : [])]) as $row) {
                $rows[] = [...(array) $row, 'kind' => $kind, 'subject_name' => $row->subject_name ?? null];
            }
        }

        return $rows;
    }
}
