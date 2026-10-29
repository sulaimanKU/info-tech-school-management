<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\MobileTestCase;

class SettingsFunctionalTest extends MobileTestCase
{
    public function test_school_profile_round_trip_and_report_branding_use_saved_values(): void
    {
        $this->asUser($this->admin);
        $values = ['school_name' => 'Fictional Updated Campus', 'campus' => 'Fictional North', 'location' => 'Fictional City', 'address' => 'Fictional street', 'phone' => '+923000000001', 'email' => 'office@example.test', 'website' => 'https://example.test', 'current_academic_session_id' => $this->session];
        $this->putJson('/api/v1/school-settings', $values)->assertOk()->assertJsonPath('data.saved', true);
        $this->getJson('/api/v1/school-settings')->assertOk()->assertJsonFragment($values);
        $this->asUser($this->user('school_administrator'))->getJson('/api/v1/school-information')->assertOk()->assertJsonFragment($values);
        $this->get('/api/v1/reports/registration?format=csv')->assertOk()->assertSee('Fictional Updated Campus');
        $this->getJson('/api/v1/academic-filter-options')->assertOk()->assertJsonPath('data.current_academic_session_id', $this->session);
    }

    public function test_all_roles_receive_only_safe_school_information_and_only_managers_can_change_it(): void
    {
        DB::table('school_settings')->insert(['key' => 'provider_api_key', 'value' => json_encode('FAKE-SECRET-FOR-TEST-ONLY')]);
        foreach (['super_admin', 'school_administrator', 'coordinator', 'accountant_clerk', 'teacher', 'parent_guardian'] as $role) {
            $u = $this->user($role);
            $this->asUser($u)->getJson('/api/v1/school-information')->assertOk()->assertJsonMissingPath('data.provider_api_key')->assertDontSee('FAKE-SECRET-FOR-TEST-ONLY');
            $response = $this->putJson('/api/v1/school-settings', ['school_name' => 'Fictional '.$role]);
            in_array($role, ['super_admin', 'school_administrator']) ? $response->assertOk() : $response->assertForbidden();
            $response = $this->getJson('/api/v1/school-settings');
            in_array($role, ['super_admin', 'school_administrator']) ? $response->assertOk()->assertDontSee('FAKE-SECRET-FOR-TEST-ONLY') : $response->assertForbidden();
        }
    }

    public function test_profile_validation_never_saves_unknown_secret_currency_or_invalid_values(): void
    {
        $this->asUser($this->admin);
        foreach ([['school_name' => ''], ['email' => 'invalid'], ['website' => 'file:///etc/passwd'], ['phone' => 'not-a-phone'], ['currency' => 'USD'], ['SPEXT_API_KEY' => 'fake'], ['current_academic_session_id' => 'not-a-uuid']] as $input) {
            $this->putJson('/api/v1/school-settings', $input)->assertUnprocessable();
        }
        $this->getJson('/api/v1/school-information')->assertJsonPath('data.currency', 'PKR');
        $this->assertDatabaseMissing('school_settings', ['key' => 'SPEXT_API_KEY']);
    }

    public function test_integration_status_is_metadata_and_never_contains_credentials(): void
    {
        config(['school.sms_driver' => 'spext', 'school.spext.api_url' => 'https://sms.example.test/private-path', 'school.spext.api_key' => 'fake-provider-secret']);
        $this->asUser($this->admin)->getJson('/api/v1/school-settings')->assertOk()->assertJsonPath('data.integration_status.sms_configured', true)->assertDontSee('fake-provider-secret')->assertDontSee('private-path');
        $this->getJson('/api/v1/school-information')->assertOk()->assertJsonMissingPath('data.integration_status');
    }

    public function test_weekdays_positions_and_grades_save_atomically_and_reload(): void
    {
        $this->asUser($this->admin);
        $input = ['weekdays' => [1, 2, 3, 4, 5], 'class_position_enabled' => true, 'academic_session_id' => $this->session, 'bands' => [['minimum_percent' => 0, 'grade' => 'F'], ['minimum_percent' => 80, 'grade' => 'A']]];
        $this->putJson('/api/v1/academic-settings', $input)->assertOk();
        $this->getJson('/api/v1/academic-settings?session_id='.$this->session)->assertOk()->assertJsonPath('data.weekdays', [1, 2, 3, 4, 5])->assertJsonPath('data.class_position_enabled', true)->assertJsonCount(2, 'data.bands');
        $this->putJson('/api/v1/academic-settings', [...$input, 'weekdays' => [7], 'bands' => [['minimum_percent' => 50, 'grade' => 'C']]])->assertUnprocessable();
        $this->getJson('/api/v1/academic-settings?session_id='.$this->session)->assertJsonPath('data.weekdays', [1, 2, 3, 4, 5])->assertJsonCount(2, 'data.bands');
        $this->asUser($this->coordinator())->putJson('/api/v1/academic-settings', $input)->assertForbidden();
    }

    public function test_populated_session_and_section_allow_unchanged_relationship_values_when_renamed(): void
    {
        $this->student();
        $this->asUser($this->admin);
        $session = DB::table('academic_sessions')->find($this->session);
        $section = DB::table('sections')->find($this->section);
        $this->patchJson('/api/v1/catalog/sessions/'.$this->session, ['name' => 'Fictional renamed', 'starts_on' => $session->starts_on, 'ends_on' => $session->ends_on])->assertOk();
        $this->patchJson('/api/v1/catalog/sections/'.$this->section, ['name' => 'Fictional renamed section', 'class_id' => $section->class_id, 'academic_session_id' => $this->session])->assertOk();
        $this->patchJson('/api/v1/catalog/sessions/'.$this->session, ['starts_on' => '2026-02-01'])->assertUnprocessable();
        $this->assertDatabaseHas('enrollments', ['section_id' => $this->section, 'academic_session_id' => $this->session]);
    }

    public function test_calendar_rejects_outside_session_and_preserves_submitted_day_type(): void
    {
        $student = $this->student();
        $this->asUser($this->admin);
        $this->postJson('/api/v1/school-days', ['academic_session_id' => $this->session, 'date' => '2025-01-01', 'is_school_day' => false])->assertUnprocessable();
        $this->postJson('/api/v1/attendance/register', ['section_id' => $this->section, 'date' => '2026-10-01', 'status' => 'submitted', 'version' => 0, 'request_id' => (string) Str::uuid(), 'records' => [['student_id' => $student, 'status' => 'present']]])->assertOk();
        $this->postJson('/api/v1/school-days', ['academic_session_id' => $this->session, 'date' => '2026-10-01', 'is_school_day' => false])->assertUnprocessable();
        $this->assertDatabaseCount('attendance_records', 1);
    }
}
