<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\SessionIssuer;
use App\Support\Domain;
use Database\Seeders\FinanceConfigurationSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\MobileTestCase;

class RbacScopeTest extends MobileTestCase
{
    private function ticket(string $student, User $parent): string
    {
        return $this->asUser($parent)->postJson('/api/v1/tickets', ['student_id' => $student, 'category' => 'general', 'subject' => 'Fictional scoped request', 'message' => 'Fictional question', 'attachments' => [], 'client_id' => (string) Str::uuid()])->assertCreated()->json('data.id');
    }

    private function profile(User $u): string
    {
        return Domain::insert('staff_profiles', ['user_id' => $u->id, 'employee_number' => 'FICTIONAL-'.Str::uuid(), 'joined_on' => '2026-01-01', 'designation' => 'Fictional role']);
    }

    public function test_active_super_admin_bypasses_missing_role_grants_server_side(): void
    {
        DB::table('permission_role')->where('role_id', Role::where('code', 'super_admin')->value('id'))->delete();
        $this->assertTrue($this->admin->hasPermission('staff.manage'));
        $this->asUser($this->admin);
        foreach (['auth/me', 'school-settings', 'students', 'staff-accounts', 'rbac', 'guardians', 'catalog/sections', 'academic-filter-options', 'coordinator', 'finance/invoices', 'imports', 'tickets'] as $path) {
            $this->getJson('/api/v1/'.$path)->assertOk();
        }
        $this->getJson('/api/v1/auth/me')->assertJsonFragment(['rbac.manage']);
        $this->admin->is_active = false;
        $this->admin->save();
        $this->assertFalse($this->admin->hasPermission('staff.manage'));
        $this->getJson('/api/v1/staff-accounts')->assertUnauthorized();
    }

    public function test_administrator_manages_operations_but_cannot_escalate_or_change_privileged_users(): void
    {
        $u = $this->user('school_administrator');
        $teacher = $this->teacher();
        $this->asUser($u);
        $this->getJson('/api/v1/students')->assertOk();
        $this->getJson('/api/v1/guardians')->assertOk();
        $this->getJson('/api/v1/rbac')->assertForbidden();
        $this->patchJson('/api/v1/staff-accounts/'.$teacher->id, ['name' => 'Fictional renamed teacher', 'role' => 'coordinator'])->assertOk();
        $this->patchJson('/api/v1/staff-accounts/'.$u->id, ['role' => 'super_admin'])->assertForbidden();
        $this->patchJson('/api/v1/staff-accounts/'.$this->admin->id, ['name' => 'Forbidden change'])->assertForbidden();
        $this->postJson('/api/v1/finance/payroll', ['period' => '2026-10'])->assertForbidden();
    }

    public function test_coordinator_without_assignment_gets_no_schoolwide_student_or_section_access(): void
    {
        $student = $this->student();
        $this->asUser($this->user('coordinator'));
        $this->getJson('/api/v1/students')->assertJsonCount(0, 'data.data');
        $this->getJson('/api/v1/catalog/sections')->assertJsonCount(0, 'data.items');
        $this->getJson('/api/v1/students/'.$student)->assertNotFound();
        $this->getJson('/api/v1/attendance/register?section_id='.$this->section.'&date=2026-10-22')->assertForbidden();
    }

    public function test_coordinator_student_catalog_report_and_ticket_scope_is_assigned(): void
    {
        $one = $this->student();
        $other = $this->student($this->otherSection);
        [, $p1] = $this->guardian($one);
        [, $p2] = $this->guardian($other, '0000000000002');
        $own = $this->ticket($one, $p1);
        $outside = $this->ticket($other, $p2);
        $this->asUser($this->coordinator());
        $this->getJson('/api/v1/students')->assertJsonCount(1, 'data.data')->assertJsonPath('data.data.0.id', $one);
        $this->getJson('/api/v1/students/'.$other)->assertNotFound();
        $this->getJson('/api/v1/tickets/'.$own)->assertOk();
        $this->getJson('/api/v1/tickets/'.$outside)->assertNotFound();
        $this->postJson('/api/v1/tickets/'.$outside.'/workflow', ['version' => 1, 'status' => 'acknowledged', 'priority' => 'normal'])->assertNotFound();
        $this->getJson('/api/v1/reports/registration?format=csv')->assertOk()->assertDontSee(DB::table('students')->where('id', $other)->value('registration_number'));
        $this->getJson('/api/v1/communication/parents')->assertJsonCount(1, 'data.items');
    }

    public function test_coordinator_counts_and_records_exclude_unassigned_sections(): void
    {
        $own = $this->student();
        $other = $this->student($this->otherSection);
        foreach ([[$own, $this->section], [$other, $this->otherSection]] as [$s,$section]) {
            Domain::insert('coordination_records', ['student_id' => $s, 'section_id' => $section, 'kind' => 'issue', 'title' => 'Fictional record', 'notes' => 'Fictional scoped note', 'status' => 'open', 'created_by' => $this->admin->id]);
        }
        $u = $this->coordinator();
        $this->asUser($u)->getJson('/api/v1/coordinator')->assertJsonCount(1, 'data.items');
        $id = DB::table('coordination_records')->where('student_id', $other)->value('id');
        $this->patchJson('/api/v1/coordinator/'.$id, ['section_id' => $this->section, 'student_id' => $own, 'kind' => 'issue', 'title' => 'Intrusion', 'notes' => 'No', 'status' => 'open'])->assertNotFound();
        $this->postJson('/api/v1/coordinator', ['section_id' => $this->otherSection, 'kind' => 'issue', 'title' => 'No', 'notes' => 'No', 'status' => 'open'])->assertForbidden();
    }

    public function test_accountant_cannot_read_academics_or_manage_rbac(): void
    {
        $s = $this->student();
        $this->asUser($this->user('accountant_clerk'));
        $this->getJson('/api/v1/students')->assertOk();
        $this->getJson('/api/v1/finance/invoices')->assertOk();
        foreach (['diary', 'assessments', 'students/'.$s.'/attendance?month=2026-10', 'students/'.$s.'/assessments', 'students/'.$s.'/behavior', 'rbac', 'staff-accounts', 'imports'] as $path) {
            $this->getJson('/api/v1/'.$path)->assertForbidden();
        }
        $this->getJson('/api/v1/students/'.$s)->assertJsonMissingPath('data.date_of_birth')->assertJsonMissingPath('data.address');
        $this->get('/api/v1/reports/academic?student_id='.$s, ['Accept' => 'application/json'])->assertForbidden();
    }

    public function test_teacher_catalogs_filters_and_objects_remain_assignment_scoped(): void
    {
        $one = $this->student();
        $other = $this->student($this->otherSection);
        $teacher = $this->teacher();
        $subject = Domain::insert('subjects', ['code' => 'fictional-extra', 'name' => 'Fictional extra subject']);
        Domain::insert('class_subject_assignments', ['class_id' => DB::table('sections')->where('id', $this->section)->value('class_id'), 'subject_id' => $subject, 'academic_session_id' => $this->session]);
        $this->asUser($teacher)->getJson('/api/v1/catalog/subjects')->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.id', $this->subject);
        $this->getJson('/api/v1/students?section_id='.$this->otherSection)->assertForbidden();
        $this->getJson('/api/v1/diary?subject_id='.$subject)->assertForbidden();
        $this->getJson('/api/v1/students/'.$other)->assertNotFound();
        $this->getJson('/api/v1/academic-filter-options?section_id='.$this->section)->assertJsonCount(1, 'data.sections')->assertJsonCount(1, 'data.subjects');
    }

    public function test_teacher_assignment_deactivation_immediately_revokes_existing_session_scope(): void
    {
        $s = $this->student();
        $u = $this->teacher();
        $this->asUser($u)->getJson('/api/v1/students/'.$s)->assertOk();
        DB::table('teacher_assignments')->where('section_id', $this->section)->update(['is_active' => false]);
        $this->getJson('/api/v1/students/'.$s)->assertNotFound();
        $this->getJson('/api/v1/catalog/subjects')->assertJsonCount(0, 'data.items');
    }

    public function test_teacher_assignment_date_limits_are_enforced(): void
    {
        $this->student();
        $u = $this->teacher();
        DB::table('teacher_assignments')->update(['starts_on' => '2026-10-20']);
        $this->asUser($u)->getJson('/api/v1/attendance/register?section_id='.$this->section.'&date=2026-10-19')->assertForbidden();
        $this->getJson('/api/v1/attendance/register?section_id='.$this->section.'&date=2026-10-22')->assertOk();
    }

    public function test_staff_creation_hash_role_edit_and_suspension_are_secure(): void
    {
        $this->asUser($this->admin);
        $id = $this->postJson('/api/v1/staff-accounts', ['name' => 'Fictional New Staff', 'email' => ' STAFF@fictional.example.test ', 'password' => 'Fictional-only-password-2026', 'role' => 'teacher'])->assertCreated()->assertDontSee('Fictional-only-password-2026')->json('data.id');
        $u = User::findOrFail($id);
        $this->assertTrue(Hash::check('Fictional-only-password-2026', $u->password));
        $this->assertSame(['teacher'], $u->roles()->pluck('code')->all());
        $this->getJson('/api/v1/staff-accounts/'.$id)->assertJsonMissingPath('data.password');
        $this->patchJson('/api/v1/staff-accounts/'.$id, ['role' => 'accountant_clerk'])->assertOk();
        $this->assertSame(['accountant_clerk'], $u->roles()->pluck('code')->all());
        $token = app(SessionIssuer::class)->issue($u, 'test')['token'];
        $this->patchJson('/api/v1/staff-accounts/'.$id.'/status', ['is_active' => false])->assertOk();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->postJson('/api/v1/auth/login', ['email' => $u->email, 'password' => 'Fictional-only-password-2026'])->assertUnauthorized();
        $this->assertDatabaseHas('audit_logs', ['event' => 'staff_role_changed', 'subject_id' => $id]);
    }

    public function test_last_super_admin_cannot_be_demoted_or_self_suspended(): void
    {
        $this->asUser($this->admin)->patchJson('/api/v1/staff-accounts/'.$this->admin->id, ['role' => 'teacher'])->assertUnprocessable();
        $this->patchJson('/api/v1/staff-accounts/'.$this->admin->id.'/status', ['is_active' => false])->assertUnprocessable();
        $this->assertTrue($this->admin->fresh()->isSuperAdmin());
    }

    public function test_role_permission_management_is_explicit_and_audited(): void
    {
        $this->asUser($this->admin)->putJson('/api/v1/rbac/coordinator', ['permissions' => ['students.read', 'academics.read']])->assertOk();
        $this->assertDatabaseHas('audit_logs', ['event' => 'role_permissions_updated']);
        $this->putJson('/api/v1/rbac/teacher', ['permissions' => ['rbac.manage']])->assertForbidden();
        $this->putJson('/api/v1/rbac/parent_guardian', ['permissions' => ['finance.read']])->assertForbidden();
        $this->asUser($this->user('school_administrator'))->putJson('/api/v1/rbac/teacher', ['permissions' => []])->assertForbidden();
    }

    public function test_coordinator_assignment_workflow_rejects_wrong_role_and_duplicates(): void
    {
        $u = $this->user('coordinator');
        $p = $this->profile($u);
        $this->asUser($this->admin);
        $v = ['staff_profile_id' => $p, 'section_id' => $this->section, 'starts_on' => '2026-01-01'];
        $id = $this->postJson('/api/v1/catalog/coordinator-assignments', $v)->assertCreated()->json('data.id');
        $this->postJson('/api/v1/catalog/coordinator-assignments', $v)->assertUnprocessable();
        $this->postJson('/api/v1/catalog/teacher-assignments', [...$v, 'subject_id' => $this->subject, 'is_class_teacher' => false])->assertUnprocessable();
        $this->postJson('/api/v1/catalog/coordinator-assignments/'.$id.'/deactivate')->assertOk();
        $this->asUser($u)->getJson('/api/v1/students')->assertJsonCount(0, 'data.data');
    }

    public function test_cascading_filter_options_validate_parent_class_section_relationships(): void
    {
        $s = $this->student();
        $this->asUser($this->admin);
        $class = DB::table('sections')->where('id', $this->section)->value('class_id');
        $otherClass = DB::table('classes')->where('id', '!=', $class)->value('id');
        $this->getJson('/api/v1/students?class_id='.$class.'&section_id='.$this->section)->assertJsonCount(1, 'data.data');
        $this->getJson('/api/v1/students?class_id='.$otherClass.'&section_id='.$this->section)->assertUnprocessable();
        $this->getJson('/api/v1/students?class_id=bad-id')->assertUnprocessable();
        $this->getJson('/api/v1/academic-filter-options?class_id='.$otherClass)->assertJsonCount(0, 'data.sections');
    }

    public function test_guardian_detail_contact_update_and_unlink_remain_masked_and_audited(): void
    {
        $s = $this->student();
        [$g,$parent] = $this->guardian($s);
        $token = app(SessionIssuer::class)->issue($parent, 'test')['token'];
        $this->asUser($this->admin)->getJson('/api/v1/guardians/'.$g)->assertOk()->assertJsonCount(1, 'data.children')->assertJsonMissingPath('data.cnic_ciphertext')->assertDontSee('+923000000001');
        $this->patchJson('/api/v1/guardians/'.$g, ['mobile' => '+923111111111', 'mobile_verified' => true])->assertOk();
        $this->getJson('/api/v1/guardians?search=0000000000001')->assertJsonCount(1, 'data.data')->assertDontSee('0000000000001')->assertDontSee('+923111111111');
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->asUser($this->admin)->deleteJson('/api/v1/guardians/'.$g.'/links/'.$s)->assertOk();
        $this->asUser($parent)->getJson('/api/v1/students/'.$s)->assertNotFound();
        $this->assertDatabaseHas('audit_logs', ['event' => 'guardian_unlinked', 'subject_id' => $s]);
        $this->assertStringNotContainsString('+923111111111', DB::table('audit_logs')->pluck('context')->implode(''));
    }

    public function test_parent_two_verified_children_but_no_unrelated_or_unverified_child_access(): void
    {
        $a = $this->student();
        $b = $this->student(roll: 2);
        $c = $this->student($this->otherSection);
        [$g,$p] = $this->guardian($a);
        $this->asUser($this->admin)->putJson('/api/v1/guardians/'.$g.'/links', ['student_id' => $b, 'relationship' => 'mother', 'is_primary' => false])->assertOk();
        $this->asUser($p)->getJson('/api/v1/parent/home')->assertJsonCount(2, 'data.children');
        foreach ([$a, $b] as $id) {
            $this->getJson('/api/v1/students/'.$id)->assertOk();
        }
        foreach (['', '/assessments', '/behavior', '/finance', '/attendance?month=2026-10'] as $suffix) {
            $this->getJson('/api/v1/students/'.$c.$suffix)->assertNotFound();
        }
        DB::table('parent_student_links')->where('student_id', $b)->update(['verified_at' => null]);
        $this->getJson('/api/v1/students/'.$b)->assertNotFound();
        $this->getJson('/api/v1/parent/home')->assertJsonCount(1, 'data.children');
        $this->getJson('/api/v1/staff-accounts')->assertForbidden();
        $this->getJson('/api/v1/rbac')->assertForbidden();
        $this->getJson('/api/v1/catalog/staff')->assertForbidden();
    }

    public function test_parent_cannot_access_other_child_voucher_receipt_file_or_conversation(): void
    {
        Storage::fake('local');
        $this->seed(FinanceConfigurationSeeder::class);
        $one = $this->student();
        $other = $this->student($this->otherSection);
        [, $p1] = $this->guardian($one);
        [, $p2] = $this->guardian($other, '0000000000002');
        $ticket = $this->ticket($other, $p2);
        $this->asUser($this->admin);
        $invoice = $this->postJson('/api/v1/finance/invoices', ['student_id' => $other, 'academic_session_id' => $this->session, 'period' => '2026-10', 'kind' => 'additional', 'issued_on' => '2026-10-20', 'due_on' => '2026-10-25', 'charges' => [['description' => 'Fictional charge', 'amount_minor' => 1000]], 'request_id' => (string) Str::uuid()])->assertCreated()->json('data.id');
        $payment = $this->postJson('/api/v1/finance/payments', ['payment_method_id' => DB::table('payment_methods')->where('code', 'cash')->value('id'), 'paid_on' => '2026-10-20', 'amount_minor' => 1000, 'request_id' => (string) Str::uuid(), 'allocations' => [['fee_invoice_id' => $invoice, 'amount_minor' => 1000]]])->assertCreated()->json('data.id');
        $file = $this->post('/api/v1/files', ['student_id' => $other, 'purpose' => 'document', 'file' => UploadedFile::fake()->createWithContent('fictional.pdf', "%PDF-1.4\nfictional")], ['Accept' => 'application/json'])->assertCreated()->json('data.id');
        $this->asUser($p2)->get('/api/v1/reports/receipt?payment_id='.$payment.'&student_id='.$other, ['Accept' => 'application/json'])->assertOk();
        $this->asUser($p1)->getJson('/api/v1/finance/invoices/'.$invoice)->assertNotFound();
        $this->get('/api/v1/reports/receipt?payment_id='.$payment.'&student_id='.$one, ['Accept' => 'application/json'])->assertNotFound();
        $this->getJson('/api/v1/files/'.$file)->assertNotFound();
        $this->getJson('/api/v1/tickets/'.$ticket)->assertNotFound();
    }

    public function test_scoped_announcement_publisher_cannot_broadcast_outside_assignment(): void
    {
        $s = $this->student();
        $this->asUser($this->coordinator());
        $v = ['title' => 'Fictional scoped notice', 'body' => 'Fictional message', 'kind' => 'notice', 'audience' => 'section', 'section_id' => $this->section, 'status' => 'published', 'version' => 0];
        $this->postJson('/api/v1/announcements', $v)->assertCreated();
        $this->postJson('/api/v1/announcements', [...$v, 'section_id' => $this->otherSection])->assertForbidden();
        $this->postJson('/api/v1/announcements', [...$v, 'audience' => 'all_users'])->assertForbidden();
    }

    public function test_staff_list_contains_roles_status_and_assignment_summary_without_hashes(): void
    {
        $u = $this->teacher();
        $this->asUser($this->admin)->getJson('/api/v1/staff-accounts?search='.$u->email.'&role=teacher')->assertJsonCount(1,'data.items')->assertJsonPath('data.items.0.roles.0.code','teacher')->assertJsonCount(1,'data.items.0.assignments')->assertJsonMissingPath('data.items.0.password');
    }
}
