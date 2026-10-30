<?php

namespace Tests\Feature;

use App\Services\SessionIssuer;
use App\Support\Domain;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\MobileTestCase;

class StaffProfilePolishTest extends MobileTestCase
{
    public function test_self_profile_includes_contact_readable_account_and_all_own_dated_assignments_only(): void
    {
        $teacher = $this->teacher();
        $other = $this->teacher($this->otherSection);
        $profile = DB::table('staff_profiles')->where('user_id', $teacher->id)->first();
        Domain::insert('teacher_assignments', ['staff_profile_id' => $profile->id, 'section_id' => $this->otherSection, 'subject_id' => $this->subject, 'is_active' => false]);
        DB::table('users')->where('id', $teacher->id)->update(['mobile' => '+923000000009', 'address' => 'Fictional staff address']);
        $response = $this->asUser($teacher)->getJson('/api/v1/account/profile')->assertOk()
            ->assertJsonPath('data.account.account_code', $teacher->account_code)
            ->assertJsonPath('data.account.mobile', '+923000000009')->assertJsonPath('data.account.address', 'Fictional staff address')
            ->assertJsonPath('data.profile.employee_number', $profile->employee_number)->assertJsonCount(2, 'data.assignments')
            ->assertJsonMissingPath('data.account.password')->assertJsonMissingPath('data.account.permissions');
        foreach ($response->json('data.assignments') as $row) {
            $this->assertSame($profile->id, $row['staff_profile_id']);
            $this->assertSame('Fictional English', $row['subject_name']);
            $this->assertSame('Fictional Test 2026', $row['session_name']);
        }
        $this->assertNotSame($other->id, $response->json('data.account.id'));
    }

    public function test_admin_contact_and_employment_edit_round_trip_preserves_credentials_and_assignments(): void
    {
        $teacher = $this->teacher();
        $hash = $teacher->password;
        $profile = DB::table('staff_profiles')->where('user_id', $teacher->id)->first();
        $this->asUser($this->admin)->patchJson('/api/v1/staff-accounts/'.$teacher->id, [
            'mobile' => '+923000000008', 'address' => 'Fictional updated contact',
            'employment' => ['employee_number' => 'STF-FICTIONAL-1', 'designation' => 'Fictional senior teacher', 'joined_on' => $profile->joined_on, 'is_active' => true],
        ])->assertOk();
        $this->getJson('/api/v1/staff-accounts/'.$teacher->id)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.mobile', '+923000000008')->assertJsonPath('data.address', 'Fictional updated contact')
            ->assertJsonPath('data.profile.employee_number', 'STF-FICTIONAL-1')->assertJsonPath('data.profile.designation', 'Fictional senior teacher')
            ->assertJsonCount(1, 'data.assignments')->assertJsonMissingPath('data.password');
        $this->assertSame($hash, $teacher->fresh()->password);
        $this->assertTrue(Hash::check('fictional-password-only', $hash));
        $this->assertDatabaseCount('teacher_assignments', 1);
    }

    public function test_self_contact_update_cannot_change_employment_role_status_or_permissions(): void
    {
        $teacher = $this->teacher();
        $this->asUser($teacher);
        foreach (['employment' => ['designation' => 'Director'], 'role' => 'super_admin', 'permissions' => ['rbac.manage'], 'is_active' => false] as $key => $value) {
            $this->patchJson('/api/v1/account/profile', [$key => $value])->assertUnprocessable();
            $this->patchJson('/api/v1/staff-accounts/'.$teacher->id, [$key => $value])->assertForbidden();
        }
        $this->assertSame(['teacher'], $teacher->roles()->pluck('code')->all());
    }

    public function test_admin_employment_updates_preserve_hierarchy_and_reject_unknown_fields(): void
    {
        $teacher = $this->teacher();
        $admin = $this->user('school_administrator');
        $this->asUser($admin)->patchJson('/api/v1/staff-accounts/'.$this->admin->id, ['employment' => ['designation' => 'Unauthorized']])->assertForbidden();
        $this->asUser($this->admin)->patchJson('/api/v1/staff-accounts/'.$teacher->id, ['employment' => ['user_id' => $admin->id]])->assertUnprocessable();
        $this->patchJson('/api/v1/staff-accounts/'.$teacher->id, ['mobile' => 'not-a-mobile'])->assertUnprocessable();
        $this->patchJson('/api/v1/staff-accounts/'.$teacher->id, ['employment' => ['employee_number' => '']])->assertUnprocessable();
        $this->patchJson('/api/v1/staff-accounts/'.$this->user('teacher')->id, ['employment' => ['designation' => 'Unlinked']])->assertUnprocessable();
    }

    public function test_employment_edit_cannot_rewrite_joining_date_after_payroll(): void
    {
        $teacher = $this->teacher();
        $profile = DB::table('staff_profiles')->where('user_id', $teacher->id)->first();
        $period = Domain::insert('payroll_periods', ['period' => '2026-09', 'status' => 'draft', 'created_by' => $this->admin->id, 'total_minor' => 1000]);
        Domain::insert('payroll_items', ['payroll_period_id' => $period, 'staff_profile_id' => $profile->id, 'base_salary_minor' => 1000, 'adjustment_minor' => 0, 'net_salary_minor' => 1000]);
        $this->asUser($this->admin)->patchJson('/api/v1/staff-accounts/'.$teacher->id, ['employment' => ['designation' => 'Must roll back', 'joined_on' => '2026-02-01']])->assertUnprocessable();
        $this->assertDatabaseHas('staff_profiles', ['id' => $profile->id, 'joined_on' => $profile->joined_on, 'designation' => $profile->designation]);
    }

    public function test_staff_photo_survives_fresh_login_and_remains_private_to_its_owner(): void
    {
        Storage::fake('local');
        $teacher = $this->teacher();
        $file = $this->asUser($teacher)->post('/api/v1/account/photo', ['file' => UploadedFile::fake()->image('fictional.jpg', 40, 40)], ['Accept' => 'application/json'])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/auth/logout')->assertOk();
        $token = $this->postJson('/api/v1/auth/login', ['email' => $teacher->email, 'password' => 'fictional-password-only'])->assertOk()->json('data.token');
        $this->withToken($token)->getJson('/api/v1/account/profile')->assertOk()->assertJsonPath('data.account.photo_file_id', $file);
        $this->get('/api/v1/files/'.$file)->assertOk();
        $this->asUser($this->admin)->getJson('/api/v1/files/'.$file)->assertNotFound();
    }

    public function test_profile_handles_unlinked_missing_and_inactive_accounts_without_cross_account_fallback(): void
    {
        $teacher = $this->user('teacher');
        $token = app(SessionIssuer::class)->issue($teacher, 'test')['token'];
        $this->withToken($token)->getJson('/api/v1/account/profile')->assertOk()->assertJsonPath('data.profile', null)->assertJsonPath('data.assignments', []);
        DB::table('users')->where('id', $teacher->id)->update(['is_active' => false]);
        $this->getJson('/api/v1/account/profile')->assertUnauthorized();
        $this->asUser($this->admin)->getJson('/api/v1/staff-accounts/'.Str::uuid())->assertNotFound();
        $this->getJson('/api/v1/staff-accounts/'.$teacher->id)->assertOk()->assertJsonPath('data.is_active', false);
    }
}
