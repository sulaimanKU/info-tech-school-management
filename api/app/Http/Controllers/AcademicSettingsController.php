<?php

namespace App\Http\Controllers;

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

        return Domain::response(['weekdays' => $settings['school_weekdays'] ?? [1, 2, 3, 4, 5, 6], 'class_position_enabled' => $settings['class_position_enabled'] ?? false, 'bands' => isset($v['session_id']) ? DB::table('grading_rules')->where('academic_session_id', $v['session_id'])->orderByDesc('minimum_percent')->get(['minimum_percent', 'grade']) : [], 'school_days' => isset($v['session_id']) ? DB::table('school_days')->where('academic_session_id', $v['session_id'])->orderBy('date')->get() : []]);
    }

    public function save(Request $r, SchoolAccess $access)
    {
        $access->require($r->user(), 'school.manage');
        $v = $r->validate(['weekdays' => 'required|array|min:1|max:7', 'weekdays.*' => 'required|integer|min:1|max:7|distinct', 'class_position_enabled' => 'required|boolean']);
        DB::transaction(function () use ($r, $v) {
            foreach (['school_weekdays' => $v['weekdays'], 'class_position_enabled' => $v['class_position_enabled']] as $key => $value) {
                DB::table('school_settings')->updateOrInsert(['key' => $key], ['value' => json_encode($value), 'created_at' => now(), 'updated_at' => now()]);
            }Audit::record('academic_settings_updated', $r->user()->id);
        });

        return Domain::response(['saved' => true]);
    }
}
