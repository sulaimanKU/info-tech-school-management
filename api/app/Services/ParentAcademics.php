<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ParentAcademics
{
    public function present(Request $request, Collection $items): array
    {
        $v = $request->validate(['exam_key' => ['nullable', 'regex:/^(latest|[a-f0-9]{64})$/']]);
        $rules = DB::table('grading_rules')->whereIn('academic_session_id', $items->pluck('academic_session_id')->unique())->orderByDesc('minimum_percent')->get()->groupBy('academic_session_id');
        foreach ($items as $row) {
            $row->exam_key = hash('sha256', json_encode([$row->academic_session_id, $row->section_id, $row->type, $row->title, $row->date]));
        }
        $groups = $items->groupBy('exam_key');
        $periods = $groups->map(function ($rows, $key) use ($rules) {
            $a = $rows->first();
            $max = (float) $rows->sum('maximum_marks');
            $obtained = (float) $rows->sum('obtained_marks');
            $percent = $max ? round(100 * $obtained / $max, 2) : null;

            return ['key' => $key, 'title' => $a->title, 'type' => $a->type, 'date' => $a->date, 'academic_session_id' => $a->academic_session_id, 'section_id' => $a->section_id, 'session_name' => $a->session_name, 'class_name' => $a->class_name, 'section_name' => $a->section_name, 'subjects' => $rows->pluck('subject_id')->unique()->count(), 'obtained_marks' => $obtained, 'maximum_marks' => $max, 'percentage' => $percent, 'grade' => $percent === null ? null : ($rules->get($a->academic_session_id, collect())->first(fn ($band) => $percent >= $band->minimum_percent)?->grade), 'published_at' => $rows->max('published_at')];
        })->values();
        $key = ($v['exam_key'] ?? null) === 'latest' ? $periods->first()['key'] ?? null : ($v['exam_key'] ?? null);
        if ($key) {
            abort_unless($groups->has($key), 404);
        }
        $selected = $key ? $periods->firstWhere('key', $key) : null;
        $rows = $key ? $groups->get($key) : $items;
        // Historical comparisons require exactly the same subject set, maximum marks, type and session.
        $trend = collect();
        if ($selected) {
            $subjects = $groups->get($key)->map(fn ($r) => $r->subject_id.':'.(float) $r->maximum_marks)->sort()->values()->all();
            $trend = $periods->filter(function ($p) use ($selected, $groups, $subjects) {
                return $p['section_id'] === $selected['section_id'] && $p['date'] <= $selected['date'] && $p['academic_session_id'] === $selected['academic_session_id'] && $p['type'] === $selected['type'] && $p['maximum_marks'] === $selected['maximum_marks'] && $groups->get($p['key'])->map(fn ($r) => $r->subject_id.':'.(float) $r->maximum_marks)->sort()->values()->all() === $subjects;
            })->take(6)->reverse()->values();
        }
        $max = $rows->sum('maximum_marks');

        return ['items' => $rows->values(), 'periods' => $periods, 'selected_period' => $selected, 'overall_percentage' => $max ? round(100 * $rows->sum('obtained_marks') / $max, 2) : null, 'trend' => $trend->count() > 1 ? $trend : collect(), 'subjects' => $rows->groupBy('subject_name')->map(fn ($rows) => ['obtained' => $rows->sum('obtained_marks'), 'maximum' => $rows->sum('maximum_marks'), 'percentage' => $rows->sum('maximum_marks') ? round(100 * $rows->sum('obtained_marks') / $rows->sum('maximum_marks'), 2) : null])];
    }
}
