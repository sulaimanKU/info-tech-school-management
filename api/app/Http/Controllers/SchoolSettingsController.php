<?php

namespace App\Http\Controllers;

use App\Services\SchoolAccess;
use App\Services\SchoolSettings;
use App\Support\Audit;
use App\Support\Domain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SchoolSettingsController extends Controller
{
    public function information(SchoolSettings $settings)
    {
        return Domain::response($settings->information())->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $r, SchoolAccess $access, SchoolSettings $settings)
    {
        $access->require($r->user(), 'settings.read');

        return Domain::response($settings->information() + [
            'integration_status' => [
                'sms_driver' => in_array(config('school.sms_driver'), ['local', 'spext', 'disabled'], true) ? config('school.sms_driver') : 'disabled',
                'sms_configured' => config('school.sms_driver') === 'spext' && filled(config('school.spext.api_url')) && filled(config('school.spext.api_key')),
                'push_driver' => config('school.push_driver') === 'fcm' ? 'fcm' : 'disabled',
                'configuration_managed_on_server' => true,
            ],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function save(Request $r, SchoolAccess $access, SchoolSettings $settings)
    {
        $access->require($r->user(), 'school.manage');
        foreach (array_diff(array_keys($r->all()), SchoolSettings::PROFILE) as $key) {
            Domain::fail($key, 'This setting is managed separately and cannot be changed here.');
        }
        $v = $r->validate([
            'school_name' => 'sometimes|required|string|max:200', 'campus' => 'sometimes|required|string|max:120',
            'location' => 'sometimes|required|string|max:200', 'address' => 'sometimes|nullable|string|max:1000',
            'phone' => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^\+?[0-9 ()\-]{4,30}$/'],
            'email' => 'sometimes|nullable|email|max:254', 'website' => 'sometimes|nullable|url:http,https|max:500',
            'current_academic_session_id' => 'sometimes|nullable|uuid|exists:academic_sessions,id',
        ]);
        DB::transaction(function () use ($r, $v) {
            foreach ($v as $key => $value) {
                DB::table('school_settings')->updateOrInsert(['key' => $key], ['value' => json_encode($value), 'updated_at' => now()]);
            }
            Audit::record('school_profile_updated', $r->user()->id, null, null, ['changed_fields' => array_keys($v)]);
        });

        return Domain::response(['saved' => true, 'settings' => $settings->information()]);
    }
}
