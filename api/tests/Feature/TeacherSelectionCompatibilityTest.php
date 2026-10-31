<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\MobileTestCase;

class TeacherSelectionCompatibilityTest extends MobileTestCase
{
    private function batch(User $teacher, array $extra = []): array
    {
        return ['teacher_user_id' => $teacher->id, 'academic_session_id' => $this->session,
            'assignments' => [['section_id' => $this->section, 'subject_id' => $this->subject, 'is_class_teacher' => false]], ...$extra];
    }

    public function test_real_staff_account_creation_without_employment_is_selectable_without_get_side_effects(): void
    {
        $this->asUser($this->admin);
        $id = $this->postJson('/api/v1/staff-accounts', ['name' => 'Fictional legacy teacher', 'email' => 'legacy@example.test', 'password' => '1234', 'password_confirmation' => '1234', 'role' => 'teacher', 'is_active' => true])->assertCreated()->json('data.id');
        $audit = DB::table('audit_logs')->count();
        $this->getJson('/api/v1/teaching-assignments/teachers')->assertOk()->assertJsonPath('data.data.0.user_id', $id)
            ->assertJsonPath('data.data.0.id', null)->assertJsonPath('data.data.0.staff_profile_id', null)
            ->assertJsonPath('data.data.0.profile_link_required', true)->assertJsonPath('data.data.0.role', 'teacher')
            ->assertJsonPath('data.data.0.is_active', true)->assertJsonMissingPath('data.data.0.password')->assertJsonMissingPath('data.data.0.email');
        $this->assertDatabaseCount('staff_profiles', 0);
        $this->assertDatabaseCount('audit_logs', $audit);
        $this->getJson('/api/v1/teaching-assignments?teacher_user_id='.$id)->assertOk()->assertJsonCount(0, 'data.data');
    }

    public function test_existing_profile_with_empty_optional_details_remains_eligible_and_unchanged(): void
    {
        $teacher = $this->teacher();
        $profile = DB::table('staff_profiles')->where('user_id', $teacher->id)->first();
        DB::table('staff_profiles')->where('id', $profile->id)->update(['designation' => '']);
        $this->asUser($this->admin)->getJson('/api/v1/teaching-assignments/teachers')->assertOk()
            ->assertJsonPath('data.data.0.id', $profile->id)->assertJsonPath('data.data.0.staff_profile_id', $profile->id)
            ->assertJsonPath('data.data.0.profile_link_required', false)->assertJsonPath('data.data.0.account_code', $teacher->account_code);
        $this->postJson('/api/v1/teaching-assignments', $this->batch($teacher, ['assignments' => [['section_id' => $this->otherSection, 'subject_id' => $this->subject, 'is_class_teacher' => false]]]))->assertCreated();
        $this->assertDatabaseHas('staff_profiles', ['id' => $profile->id, 'joined_on' => $profile->joined_on, 'designation' => '', 'employee_number' => $profile->employee_number]);
        $this->assertDatabaseCount('staff_profiles', 1);
    }

    public function test_inactive_accounts_inactive_profiles_and_non_teachers_are_excluded(): void
    {
        $eligible = $this->user('teacher');
        $inactive = $this->user('teacher');
        $inactive->update(['is_active' => false]);
        $disabled = $this->teacher();
        DB::table('staff_profiles')->where('user_id', $disabled->id)->update(['is_active' => false]);
        $this->user('accountant_clerk');
        $this->user('parent_guardian');
        $this->asUser($this->admin)->getJson('/api/v1/teaching-assignments/teachers')->assertOk()->assertJsonCount(1, 'data.data')->assertJsonPath('data.data.0.user_id', $eligible->id);
        foreach ([$inactive, $disabled, $this->admin] as $user) {
            $this->postJson('/api/v1/teaching-assignments', $this->batch($user, ['employment' => ['joined_on' => '2026-01-01']]))->assertUnprocessable();
        }
        $this->assertDatabaseHas('staff_profiles', ['user_id' => $disabled->id, 'is_active' => false]);
    }

    public function test_selector_search_pagination_and_empty_envelope_use_the_existing_contract(): void
    {
        foreach (range(1, 31) as $n) {
            $this->user('teacher')->update(['name' => sprintf('Fictional Teacher %02d', $n)]);
        }
        $this->asUser($this->admin)->getJson('/api/v1/teaching-assignments/teachers')->assertOk()->assertJsonCount(30, 'data.data')->assertJsonPath('data.total', 31)->assertJsonPath('data.last_page', 2);
        $this->getJson('/api/v1/teaching-assignments/teachers?page=2')->assertJsonCount(1, 'data.data');
        $this->getJson('/api/v1/teaching-assignments/teachers?search=Teacher%2031')->assertJsonCount(1, 'data.data')->assertJsonPath('data.data.0.name', 'Fictional Teacher 31');
        $this->getJson('/api/v1/teaching-assignments/teachers?search=No-match')->assertOk()->assertJsonPath('data.total', 0)->assertJsonCount(0, 'data.data');
        $this->assertDatabaseCount('staff_profiles', 0);
    }

    public function test_management_roles_and_coordinator_section_scope_remain_enforced(): void
    {
        $teacher = $this->teacher();
        foreach ([$teacher, $this->user('accountant_clerk'), $this->user('parent_guardian')] as $user) {
            $this->asUser($user)->getJson('/api/v1/teaching-assignments/teachers')->assertForbidden();
            $this->postJson('/api/v1/teaching-assignments', $this->batch($teacher))->assertForbidden();
        }
        $coordinator = $this->coordinator();
        $this->asUser($coordinator)->getJson('/api/v1/teaching-assignments/teachers')->assertOk();
        $this->postJson('/api/v1/teaching-assignments', $this->batch($teacher, ['assignments' => [['section_id' => $this->otherSection, 'subject_id' => $this->subject, 'is_class_teacher' => false]]]))->assertForbidden();
        $unlinked = $this->user('teacher');
        $this->postJson('/api/v1/teaching-assignments', $this->batch($unlinked, ['employment' => ['joined_on' => '2026-01-01']]))->assertUnprocessable();
        $this->assertDatabaseMissing('staff_profiles', ['user_id' => $unlinked->id]);
        $admin = $this->user('school_administrator');
        $this->asUser($admin)->getJson('/api/v1/teaching-assignments/teachers')->assertOk();
        $this->postJson('/api/v1/teaching-assignments', $this->batch($teacher, ['assignments' => [['section_id' => $this->otherSection, 'subject_id' => $this->subject, 'is_class_teacher' => false]]]))->assertCreated();
    }

    public function test_missing_profile_requires_real_joining_date_and_links_atomically_without_changing_account(): void
    {
        $teacher = $this->user('teacher');
        $hash = $teacher->password;
        $this->asUser($this->admin);
        $this->postJson('/api/v1/teaching-assignments', $this->batch($teacher))->assertUnprocessable();
        $this->assertDatabaseCount('staff_profiles', 0);
        $this->postJson('/api/v1/teaching-assignments', $this->batch($teacher, ['employment' => ['joined_on' => '2026-02-10', 'employee_number' => 'STF-REAL-01', 'designation' => 'Confirmed Teacher']]))->assertCreated()->assertJsonPath('data.teacher_user_id', $teacher->id);
        $this->assertDatabaseHas('staff_profiles', ['user_id' => $teacher->id, 'joined_on' => '2026-02-10', 'employee_number' => 'STF-REAL-01', 'designation' => 'Confirmed Teacher']);
        $this->assertSame($hash, $teacher->fresh()->password);
        $this->assertSame(['teacher'], $teacher->roles()->pluck('code')->all());
        $this->asUser($teacher)->getJson('/api/v1/account/profile')->assertOk()->assertJsonCount(1, 'data.assignments');
    }

    public function test_invalid_assignment_rolls_back_new_employment_and_mismatched_ids_do_not_relink_profiles(): void
    {
        $teacher = $this->user('teacher');
        $this->asUser($this->admin);
        $row = $this->batch($teacher)['assignments'][0];
        $this->postJson('/api/v1/teaching-assignments', $this->batch($teacher, ['employment' => ['joined_on' => '2026-02-10'], 'assignments' => [$row, $row]]))->assertUnprocessable();
        $this->assertDatabaseCount('staff_profiles', 0);
        $linked = $this->teacher();
        $profile = DB::table('staff_profiles')->where('user_id', $linked->id)->value('id');
        $this->postJson('/api/v1/teaching-assignments', $this->batch($teacher, ['staff_profile_id' => $profile]))->assertUnprocessable();
        $this->assertDatabaseMissing('staff_profiles', ['user_id' => $teacher->id]);
        $this->postJson('/api/v1/teaching-assignments', $this->batch($linked, ['employment' => ['joined_on' => '2026-02-10']]))->assertUnprocessable();
    }

    public function test_diagnostic_is_read_only_and_emits_aggregate_counts_without_private_fields(): void
    {
        $teacher = $this->user('teacher');
        $linked = $this->teacher();
        $this->assertSame(0, Artisan::call('school:diagnose-teacher-selection', ['--json' => true]));
        $out = Artisan::output();
        $counts = json_decode($out, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(2, $counts['eligible_teachers']);
        $this->assertSame(1, $counts['eligible_teachers_missing_staff_profile']);
        foreach ([$teacher->id, $teacher->name, $teacher->email, $teacher->account_code, $linked->email] as $private) {
            $this->assertStringNotContainsString($private, $out);
        }
        $this->assertDatabaseCount('staff_profiles', 1);
    }
}
