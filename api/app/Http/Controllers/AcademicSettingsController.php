<?php

namespace App\Http\Controllers;

use App\Services\AcademicCalendar;
use App\Services\GradingRules;
use App\Services\SchoolAccess;
use App\Support\Audit;
use App\Support\Domain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AcademicSettingsController extends Controller
{
    public function show(Request $r, SchoolAccess $access)
    {
        $access->require($r->user(), 'school.manage');
        $v = $r->validate(['session_id' => 'nullable|uuid|exists:academic_sessions,id']);
        $settings = DB::table('school_settings')->whereIn('key', ['school_weekdays', 'class_position_enabled'])->get()->mapWithKeys(fn ($s) => [$s->key => json_decode($s->value, true)]);

        return Domain::response(['weekdays' => $settings['school_weekdays'] ?? [1, 2, 3, 4, 5, 6], 'class_position_enabled' => $settings['class_position_enabled'] ?? false, 'bands' => isset($v['session_id']) ? DB::table('grading_rules')->where('academic_session_id', $v['session_id'])->orderByDesc('minimum_percent')->get(['minimum_percent', 'grade']) : [], 'school_days' => isset($v['session_id']) ? DB::table('school_days')->where('academic_session_id', $v['session_id'])->where(fn ($q) => $q->whereNull('label')->orWhere('label', '!=', AcademicCalendar::HISTORY_LABEL))->orderBy('date')->get() : []]);
    }

    public function save(Request $r, SchoolAccess $access)
    {
        $access->require($r->user(), 'school.manage');
        $rules = ['weekdays' => 'required|array|min:1|max:7', 'weekdays.*' => 'required|integer|min:1|max:7|distinct', 'class_position_enabled' => 'required|boolean', 'academic_session_id' => 'required_with:bands|nullable|uuid|exists:academic_sessions,id'];
        if ($r->has('bands')) {
            $rules += GradingRules::rules();
        }
        $v = $r->validate($rules);
        $v['weekdays'] = array_map('intval', $v['weekdays']);
        if (isset($v['bands'])) {
            app(GradingRules::class)->validate($v['bands']);
        }
        DB::transaction(function () use ($r, $v) {
            app(AcademicCalendar::class)->preservePastWeekdays($v['weekdays']);
            foreach (['school_weekdays' => $v['weekdays'], 'class_position_enabled' => $v['class_position_enabled']] as $key => $value) {
                DB::table('school_settings')->updateOrInsert(['key' => $key], ['value' => json_encode($value), 'created_at' => now(), 'updated_at' => now()]);
            }
            if (isset($v['bands'])) {
                app(GradingRules::class)->replace($v['academic_session_id'], $v['bands']);
            }
            Audit::record('academic_settings_updated', $r->user()->id);
        });

        return Domain::response(['saved' => true]);
    }
}
