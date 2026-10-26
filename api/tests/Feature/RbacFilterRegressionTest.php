<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Services\SessionIssuer;
use App\Support\Domain;
use Database\Seeders\FinanceConfigurationSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\MobileTestCase;

class RbacFilterRegressionTest extends MobileTestCase
{
    public function test_expired_subject_is_not_reintroduced_by_another_current_assignment(): void
    {
        $u = $this->teacher();
        $profile = DB::table('staff_profiles')->where('user_id', $u->id)->value('id');
        $subject = Domain::insert('subjects', ['name' => 'Fictional expired subject', 'code' => 'expired-fiction']);
        Domain::insert('class_subject_assignments', ['class_id' => DB::table('sections')->where('id', $this->section)->value('class_id'), 'academic_session_id' => $this->session, 'subject_id' => $subject]);
        Domain::insert('teacher_assignments', ['staff_profile_id' => $profile, 'section_id' => $this->section, 'subject_id' => $subject, 'ends_on' => now()->subDay()->toDateString()]);
        Domain::insert('diary_entries', ['section_id' => $this->section, 'subject_id' => $subject, 'teacher_user_id' => $u->id, 'date' => now()->toDateString(), 'topic' => 'Fictional expired topic', 'homework' => 'Fictional homework', 'whole_class' => true, 'status' => 'published', 'version' => 1, 'client_id' => (string) Str::uuid(), 'payload_hash' => str_repeat('a', 64)]);
        $this->asUser($u)->getJson('/api/v1/academic-filter-options?section_id='.$this->section)->assertOk()->assertJsonCount(1, 'data.subjects')->assertJsonPath('data.subjects.0.id', $this->subject);
        $this->getJson('/api/v1/diary')->assertOk()->assertJsonCount(0, 'data.data');
    }

    public function test_super_admin_subject_choices_follow_the_selected_class_and_section(): void
    {
        $unconfigured = Domain::insert('subjects', ['name' => 'Fictional unrelated subject', 'code' => 'unrelated']);
        $this->asUser($this->admin)->getJson('/api/v1/academic-filter-options?section_id='.$this->section)->assertOk()->assertJsonCount(1, 'data.subjects')->assertJsonPath('data.subjects.0.id', $this->subject);
        $class = DB::table('sections')->where('id', $this->section)->value('class_id');
        $this->getJson('/api/v1/academic-filter-options?class_id='.$class)->assertOk()->assertJsonCount(1, 'data.subjects');
    }

    public function test_invoice_status_filters_match_calculated_status_before_pagination(): void
    {
        $student = $this->student();
        $today = now()->toDateString();
        foreach ([['overdue', now()->subDay()->toDateString(), 100], ['unpaid', $today, 200], ['waived_adjusted', $today, 0]] as [$status,$due,$amount]) {
            Domain::insert('fee_invoices', ['student_id' => $student, 'academic_session_id' => $this->session, 'voucher_number' => 'FICTIONAL-'.$status, 'period' => now()->format('Y-m'), 'kind' => ['overdue' => 'additional', 'unpaid' => 'tuition', 'waived_adjusted' => 'admission'][$status], 'issued_on' => now()->subDays(2)->toDateString(), 'due_on' => $due, 'gross_minor' => $amount, 'discount_minor' => 0, 'total_minor' => $amount, 'request_id' => (string) Str::uuid(), 'payload_hash' => str_repeat('a', 64), 'created_by' => $this->admin->id]);
        }
        $this->asUser($this->user('accountant_clerk'));
        foreach (['overdue', 'unpaid', 'waived_adjusted'] as $status) {
            $this->getJson('/api/v1/finance/invoices?status='.$status.'&period='.now()->format('Y-m'))->assertOk()->assertJsonCount(1, 'data.data')->assertJsonPath('data.data.0.status', $status);
        }
        $this->getJson('/api/v1/finance/invoices?status=paid')->assertOk()->assertJsonCount(0, 'data.data');
        $this->getJson('/api/v1/finance/invoices?from='.now()->addDay()->toDateString())->assertOk()->assertJsonCount(0, 'data.data');
    }

    public function test_financial_report_accepts_and_validates_date_bounds(): void
    {
        $this->asUser($this->user('accountant_clerk'))->get('/api/v1/reports/financial?format=csv&from=2026-01-01&to=2026-12-31', ['Accept' => 'application/json'])->assertOk();
        $this->get('/api/v1/reports/financial?format=csv&from=2026-12-31&to=2026-01-01', ['Accept' => 'application/json'])->assertUnprocessable();
    }

    public function test_guardian_status_revokes_existing_parent_session_and_filters_directory(): void
    {
        [$g,$p] = $this->guardian($this->student());
        $this->asUser($p);
        $token = app(SessionIssuer::class)->issue($p, 'test')['token'];
        $this->asUser($this->admin)->patchJson('/api/v1/guardians/'.$g, ['is_active' => false])->assertOk();
        $this->getJson('/api/v1/guardians?status=inactive')->assertOk()->assertJsonCount(1, 'data.data');
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_role_announcement_and_selected_child_announcements_do_not_cross_targets(): void
    {
        $one = $this->student();
        $two = $this->student($this->otherSection);
        [$g,$p] = $this->guardian($one);
        $this->asUser($this->admin)->putJson('/api/v1/guardians/'.$g.'/links', ['student_id' => $two, 'relationship' => 'guardian', 'is_primary' => false])->assertOk();
        foreach ([['section', $this->section, null, 'Only alpha'], ['section', $this->otherSection, null, 'Only beta'], ['role', null, 'teacher', 'Teacher only']] as [$audience,$section,$role,$title]) {
            $this->postJson('/api/v1/announcements', ['title' => $title, 'body' => 'Fictional notice', 'kind' => 'notice', 'audience' => $audience, 'section_id' => $section, 'target_role_code' => $role, 'status' => 'published', 'version' => 0])->assertCreated();
        }
        $this->asUser($p)->getJson('/api/v1/announcements?student_id='.$one)->assertOk()->assertJsonCount(1, 'data.data')->assertJsonPath('data.data.0.title', 'Only alpha');
        $this->getJson('/api/v1/announcements?student_id='.$two)->assertOk()->assertJsonCount(1, 'data.data')->assertJsonPath('data.data.0.title', 'Only beta');
        $this->asUser($this->teacher())->getJson('/api/v1/announcements?search=Teacher')->assertOk()->assertJsonCount(1, 'data.data');
    }

    public function test_current_assignment_does_not_expose_records_before_its_start_date(): void
    {
        $u = $this->teacher();
        DB::table('teacher_assignments')->whereIn('staff_profile_id', DB::table('staff_profiles')->where('user_id', $u->id)->select('id'))->update(['starts_on' => now()->toDateString()]);
        Domain::insert('assessments', ['section_id' => $this->section, 'academic_session_id' => $this->session, 'subject_id' => $this->subject, 'teacher_user_id' => $u->id, 'type' => 'monthly_test', 'title' => 'Fictional historical record', 'date' => now()->subDay()->toDateString(), 'maximum_marks' => 100]);
        $this->asUser($u)->getJson('/api/v1/assessments')->assertOk()->assertJsonCount(0, 'data.data');
        $diary = Domain::insert('diary_entries', ['section_id' => $this->section, 'subject_id' => $this->subject, 'teacher_user_id' => $u->id, 'date' => now()->subDay()->toDateString(), 'topic' => 'Fictional pre-assignment', 'homework' => 'Fictional work', 'whole_class' => true, 'status' => 'published', 'version' => 1, 'client_id' => (string) Str::uuid(), 'payload_hash' => str_repeat('a', 64)]);
        $this->getJson('/api/v1/diary/'.$diary)->assertNotFound();
        $id = DB::table('assessments')->value('id');
        $this->getJson('/api/v1/assessments/'.$id)->assertForbidden();
    }

    public function test_explicit_finance_grants_never_bypass_teacher_student_scope(): void
    {
        $this->seed(FinanceConfigurationSeeder::class);
        $other = $this->student($this->otherSection);
        $this->asUser($this->admin);
        $invoice = $this->postJson('/api/v1/finance/invoices', ['student_id' => $other, 'academic_session_id' => $this->session, 'period' => '2026-10', 'kind' => 'additional', 'issued_on' => '2026-10-20', 'due_on' => '2026-10-30', 'request_id' => (string) Str::uuid(), 'charges' => [['description' => 'Fictional charge', 'amount_minor' => 1000]]])->assertCreated()->json('data.id');
        $payment = $this->postJson('/api/v1/finance/payments', ['payment_method_id' => DB::table('payment_methods')->where('code', 'cash')->value('id'), 'paid_on' => '2026-10-20', 'amount_minor' => 1000, 'request_id' => (string) Str::uuid(), 'allocations' => [['fee_invoice_id' => $invoice, 'amount_minor' => 1000]]])->assertCreated()->json('data.id');
        $u = $this->teacher();
        $role = Role::where('code', 'teacher')->firstOrFail();
        foreach (['finance.read', 'finance.write', 'finance.approve', 'reports.export'] as $code) {
            DB::table('permission_role')->insertOrIgnore(['role_id' => $role->id, 'permission_id' => DB::table('permissions')->where('code', $code)->value('id')]);
        }
        $this->asUser($u)->getJson('/api/v1/finance/invoices/'.$invoice)->assertNotFound();
        $this->getJson('/api/v1/finance/payments/'.$payment)->assertNotFound();
        $this->getJson('/api/v1/finance/payments')->assertOk()->assertJsonCount(0, 'data.data');
        $this->postJson('/api/v1/finance/payments/'.$payment.'/verify')->assertNotFound();
        $this->get('/api/v1/reports/receipt?payment_id='.$payment, ['Accept' => 'application/json'])->assertNotFound();
    }

    public function test_parent_attendance_filters_narrow_records_without_changing_month_statistics(): void
    {
        $student = $this->student();
        [$guardian,$parent] = $this->guardian($student);
        $this->asUser($this->admin)->postJson('/api/v1/attendance/register', ['section_id' => $this->section, 'date' => '2026-10-01', 'status' => 'submitted', 'version' => 0, 'request_id' => (string) Str::uuid(), 'records' => [['student_id' => $student, 'status' => 'present']]])->assertOk();
        $this->asUser($parent)->getJson('/api/v1/students/'.$student.'/attendance?month=2026-10&status=present')->assertOk()->assertJsonCount(1, 'data.items')->assertJsonPath('data.recorded_days', 1);
        $this->getJson('/api/v1/students/'.$student.'/attendance?month=2026-10&status=absent')->assertOk()->assertJsonCount(0, 'data.items')->assertJsonPath('data.recorded_days', 1);
        $this->getJson('/api/v1/students/'.$student.'/attendance?month=2026-10&from=2026-10-02')->assertOk()->assertJsonCount(0, 'data.items');
    }

    public function test_parent_communication_report_preserves_selected_child_and_dates(): void
    {
        $one = $this->student();
        $two = $this->student($this->otherSection);
        [$g,$p] = $this->guardian($one);
        $this->asUser($this->admin)->putJson('/api/v1/guardians/'.$g.'/links', ['student_id' => $two, 'relationship' => 'guardian', 'is_primary' => false])->assertOk();
        $this->asUser($p);
        foreach ([$one, $two] as $student) {
            $this->postJson('/api/v1/tickets', ['student_id' => $student, 'category' => 'general', 'subject' => 'Fictional report query', 'message' => 'Fictional report message', 'attachments' => [], 'client_id' => (string) Str::uuid()])->assertCreated();
        }
        $registration = DB::table('students')->where('id', $two)->value('registration_number');
        $this->get('/api/v1/reports/communication?format=csv&student_id='.$one, ['Accept' => 'application/json'])->assertOk()->assertDontSee($registration);
        $this->get('/api/v1/reports/communication?format=csv&from='.now()->addDay()->toDateString(), ['Accept' => 'application/json'])->assertOk()->assertDontSee('Fictional report query');
    }
}
