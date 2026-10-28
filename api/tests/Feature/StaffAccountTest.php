<?php

namespace Tests\Feature;

use App\Services\SessionIssuer;
use App\Support\Domain;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MobileTestCase;

class StaffAccountTest extends MobileTestCase
{
    public function test_all_staff_roles_can_read_own_account_without_employee_link_and_empty_payroll(): void
    {
        foreach (['super_admin', 'school_administrator', 'coordinator', 'accountant_clerk', 'teacher'] as $role) {
            $u = $this->user($role);
            $this->asUser($u)->getJson('/api/v1/account/profile')->assertOk()->assertJsonPath('data.account.id', $u->id)->assertJsonPath('data.profile', null)->assertJsonPath('data.staff_linked', false)->assertJsonMissingPath('data.account.password')->assertHeader('Cache-Control', 'no-store, private');
            $this->getJson('/api/v1/my-staff-profile')->assertOk()->assertJsonCount(0, 'data.items')->assertJsonPath('data.staff_linked', false);
        }
    }

    public function test_own_linked_profile_and_personal_edit_leave_employment_and_other_users_unchanged(): void
    {
        $u = $this->teacher();
        $other = $this->teacher($this->otherSection);
        $before = DB::table('staff_profiles')->where('user_id', $u->id)->first();
        $this->asUser($u)->patchJson('/api/v1/account/profile', ['name' => 'Fictional Updated Teacher', 'mobile' => '+923000000009', 'address' => 'Fictional campus address'])->assertOk();
        $this->getJson('/api/v1/account/profile')->assertOk()->assertJsonPath('data.account.name', 'Fictional Updated Teacher')->assertJsonPath('data.account.mobile', '+923000000009')->assertJsonPath('data.profile.employee_number', $before->employee_number)->assertJsonPath('data.staff_linked', true);
        $this->assertSame($other->name, $other->fresh()->name);
        $this->assertEquals($before, DB::table('staff_profiles')->where('id', $before->id)->first());
        $this->getJson('/api/v1/my-staff-profile')->assertOk()->assertJsonCount(0, 'data.items')->assertJsonPath('data.staff_linked', true);
    }

    public function test_target_ids_and_protected_self_service_fields_are_rejected(): void
    {
        $u = $this->teacher();
        $other = $this->teacher($this->otherSection);
        $this->asUser($u);
        foreach (['role', 'roles', 'permissions', 'salary', 'base_salary_minor', 'employee_number', 'designation', 'joined_on', 'is_active', 'email', 'user_id', 'staff_profile_id', 'photo_file_id', 'password'] as $key) {
            $this->patchJson('/api/v1/account/profile', [$key => 'unauthorized'])->assertUnprocessable();
        }
        $this->getJson('/api/v1/account/profile?user_id='.$other->id)->assertUnprocessable();
        $this->getJson('/api/v1/my-staff-profile?staff_profile_id='.$other->id)->assertUnprocessable();
        $this->getJson('/api/v1/account/profile/'.$other->id)->assertNotFound();
        $this->getJson('/api/v1/staff-accounts/'.$other->id)->assertForbidden();
        $this->patchJson('/api/v1/account/profile', ['mobile' => 'not a phone'])->assertUnprocessable();
        $this->patchJson('/api/v1/account/profile', ['mobile' => '-------'])->assertUnprocessable();
        $this->patchJson('/api/v1/account/profile', ['name' => ''])->assertUnprocessable();
    }

    public function test_unauthenticated_and_parent_cannot_use_staff_account_endpoints(): void
    {
        $this->getJson('/api/v1/account/profile')->assertUnauthorized();
        [, $parent] = $this->guardian($this->student());
        $this->asUser($parent);
        foreach (['account/profile', 'my-staff-profile'] as $path) {
            $this->getJson('/api/v1/'.$path)->assertForbidden();
        }
        $this->patchJson('/api/v1/account/profile', ['name' => 'Forbidden'])->assertForbidden();
        $this->postJson('/api/v1/account/password', [])->assertForbidden();
        $this->postJson('/api/v1/account/photo', [])->assertForbidden();
        $this->deleteJson('/api/v1/account/photo')->assertForbidden();
    }

    public function test_password_requires_current_confirmation_policy_and_preserves_password_on_failure(): void
    {
        $u = $this->teacher();
        $old = $u->password;
        $this->asUser($u);
        foreach ([
            ['password' => 'Fictional-new-password-only', 'password_confirmation' => 'Fictional-new-password-only'],
            ['current_password' => 'wrong', 'password' => 'Fictional-new-password-only', 'password_confirmation' => 'Fictional-new-password-only'],
            ['current_password' => 'fictional-password-only', 'password' => 'short', 'password_confirmation' => 'short'],
            ['current_password' => 'fictional-password-only', 'password' => 'Fictional-new-password-only', 'password_confirmation' => 'different'],
        ] as $v) {
            $this->postJson('/api/v1/account/password', $v)->assertUnprocessable();
        }
        $this->assertSame($old, $u->fresh()->password);
    }

    public function test_password_is_hashed_and_all_old_sessions_revoked_new_login_required(): void
    {
        $u = $this->teacher();
        $other = $this->teacher($this->otherSection);
        $otherToken = app(SessionIssuer::class)->issue($other, 'test')['token'];
        $token = app(SessionIssuer::class)->issue($u, 'test')['token'];
        $second = app(SessionIssuer::class)->issue($u, 'test')['token'];
        $this->withToken($token)->postJson('/api/v1/account/password', ['current_password' => 'fictional-password-only', 'password' => 'Fictional-new-password-only', 'password_confirmation' => 'Fictional-new-password-only'])->assertOk()->assertJsonPath('data.reauthenticate', true)->assertDontSee('Fictional-new-password-only');
        $this->assertTrue(Hash::check('Fictional-new-password-only', $u->fresh()->password));
        $this->withToken($token)->getJson('/api/v1/account/profile')->assertUnauthorized();
        $this->withToken($second)->getJson('/api/v1/account/profile')->assertUnauthorized();
        $this->withToken($otherToken)->getJson('/api/v1/account/profile')->assertOk();
        $this->postJson('/api/v1/auth/login', ['email' => $u->email, 'password' => 'fictional-password-only'])->assertUnauthorized();
        $this->postJson('/api/v1/auth/login', ['email' => $u->email, 'password' => 'Fictional-new-password-only'])->assertOk();
    }

    public function test_staff_photo_is_private_replace_remove_invalid_files_and_idor(): void
    {
        Storage::fake('local');
        $u = $this->teacher();
        $other = $this->teacher($this->otherSection);
        $this->asUser($u);
        $one = $this->post('/api/v1/account/photo', ['file' => UploadedFile::fake()->image('fictional.png', 40, 40)], ['Accept' => 'application/json'])->assertCreated()->json('data.id');
        $this->get('/api/v1/files/'.$one)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->asUser($other)->getJson('/api/v1/files/'.$one)->assertNotFound();
        $this->asUser($this->admin)->getJson('/api/v1/files/'.$one)->assertNotFound();
        $this->asUser($u);
        $two = $this->post('/api/v1/account/photo', ['file' => UploadedFile::fake()->image('fictional.jpg', 32, 32)], ['Accept' => 'application/json'])->assertCreated()->json('data.id');
        $this->getJson('/api/v1/files/'.$one)->assertNotFound();
        $this->get('/api/v1/files/'.$two)->assertOk();
        $this->deleteJson('/api/v1/account/photo')->assertOk();
        $this->getJson('/api/v1/files/'.$two)->assertNotFound();
        foreach ([UploadedFile::fake()->create('fictional.pdf', 5, 'application/pdf'), UploadedFile::fake()->create('oversized.png', 5121, 'image/png'), UploadedFile::fake()->createWithContent('bad.png', 'not an image')] as $file) {
            $this->post('/api/v1/account/photo', ['file' => $file], ['Accept' => 'application/json'])->assertUnprocessable();
        }
        $this->assertNull($u->fresh()->photo_file_id);
    }

    public function test_own_salary_documents_never_use_admin_bypass_and_unpaid_items_hidden(): void
    {
        $u = $this->teacher();
        $other = $this->teacher($this->otherSection);
        $period = Domain::insert('payroll_periods', ['period' => '2026-09', 'total_minor' => 200000, 'status' => 'paid', 'paid_on' => '2026-10-01', 'created_by' => $this->admin->id]);
        $items = [];
        foreach ([$u, $other] as $staff) {
            $items[] = Domain::insert('payroll_items', ['payroll_period_id' => $period, 'staff_profile_id' => DB::table('staff_profiles')->where('user_id', $staff->id)->value('id'), 'base_salary_minor' => 100000, 'adjustment_minor' => 0, 'net_salary_minor' => 100000]);
        }
        $this->asUser($u)->getJson('/api/v1/my-staff-profile')->assertOk()->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.id', $items[0]);
        $this->get('/api/v1/account/salary-slips/'.$items[0].'/document')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->getJson('/api/v1/account/salary-slips/'.$items[1].'/document')->assertNotFound();
        $this->asUser($this->admin)->getJson('/api/v1/account/salary-slips/'.$items[0].'/document')->assertNotFound();
        $draft = Domain::insert('payroll_periods', ['period' => '2026-10', 'total_minor' => 100000, 'status' => 'draft', 'created_by' => $this->admin->id]);
        $draftItem = Domain::insert('payroll_items', ['payroll_period_id' => $draft, 'staff_profile_id' => DB::table('staff_profiles')->where('user_id', $u->id)->value('id'), 'base_salary_minor' => 100000, 'adjustment_minor' => 0, 'net_salary_minor' => 100000]);
        $this->asUser($u)->getJson('/api/v1/my-staff-profile')->assertJsonCount(1, 'data.items');
        $this->getJson('/api/v1/account/salary-slips/'.$draftItem.'/document')->assertNotFound();
    }
}
