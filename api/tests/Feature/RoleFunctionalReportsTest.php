<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Services\AcademicCalendar;
use App\Support\Domain;
use Database\Seeders\FinanceConfigurationSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\MobileTestCase;

class RoleFunctionalReportsTest extends MobileTestCase
{
    public function test_academic_registers_and_assignments_follow_coordinator_scope_and_readable_ids(): void
    {
        $one = $this->student();
        $other = $this->student($this->otherSection);
        $this->teacher();
        $this->teacher($this->otherSection);
        $this->asUser($this->coordinator());
        foreach (['enrollment', 'teacher_assignments'] as $type) {
            $this->get('/api/v1/reports/'.$type.'?format=csv')->assertOk()->assertDontSee($this->otherSection)->assertDontSee($one)->assertDontSee($other);
        }
        $this->get('/api/v1/reports/enrollment?format=csv')->assertSee(DB::table('students')->find($one)->registration_number)->assertDontSee(DB::table('students')->find($other)->registration_number);
        foreach (['class_attendance', 'class_results'] as $type) {
            $this->get('/api/v1/reports/'.$type.'?format=csv&section_id='.$this->otherSection)->assertForbidden();
        }
        foreach (['payment_register', 'payroll_summary'] as $type) {
            $this->get('/api/v1/reports/'.$type.'?format=csv')->assertForbidden();
        }
    }

    public function test_reports_enforce_role_permissions_even_with_direct_urls(): void
    {
        $this->student();
        $this->asUser($this->user('accountant_clerk'));
        foreach (['enrollment', 'payment_register', 'payroll_summary'] as $type) {
            $this->get('/api/v1/reports/'.$type.'?format=csv')->assertOk();
        }
        foreach (['teacher_assignments', 'class_attendance', 'class_results'] as $type) {
            $this->get('/api/v1/reports/'.$type.'?format=csv')->assertForbidden();
        }
        $this->asUser($this->teacher());
        foreach (['enrollment', 'payment_register', 'payroll_summary'] as $type) {
            $this->get('/api/v1/reports/'.$type.'?format=csv')->assertForbidden();
        }
        $this->asUser($this->user('parent_guardian'));
        foreach (['enrollment', 'teacher_assignments', 'class_attendance', 'class_results', 'payment_register', 'payroll_summary'] as $type) {
            $this->get('/api/v1/reports/'.$type.'?format=csv')->assertForbidden();
        }
        $this->asUser($this->user('school_administrator'));
        foreach (['enrollment', 'teacher_assignments', 'class_attendance', 'class_results', 'payment_register'] as $type) {
            $this->get('/api/v1/reports/'.$type.'?format=csv')->assertOk();
        }
        $this->get('/api/v1/reports/payroll_summary?format=csv')->assertForbidden();
    }

    public function test_teacher_attendance_counts_and_pending_workspace_update_after_submission(): void
    {
        $teacher = $this->teacher();
        $ids = [];
        foreach (range(1, 4) as $roll) {
            $ids[] = $this->student(roll: $roll);
        }
        $this->asUser($teacher);
        $this->getJson('/api/v1/my-teaching')->assertJsonPath('data.pending_attendance_sections', 1);
        $body = ['section_id' => $this->section, 'date' => now()->toDateString(), 'status' => 'submitted', 'version' => 0, 'request_id' => (string) Str::uuid(), 'records' => array_map(fn ($id, $status) => ['student_id' => $id, 'status' => $status], $ids, ['present', 'absent', 'excused', 'late'])];
        $this->postJson('/api/v1/attendance/register', $body)->assertOk();
        $this->postJson('/api/v1/attendance/register', $body)->assertOk();
        $this->assertDatabaseCount('attendance_records', 4);
        $this->getJson('/api/v1/my-teaching')->assertJsonPath('data.pending_attendance_sections', 0)->assertJsonPath('data.sections.0.attendance_status', 'submitted');
        $this->getJson('/api/v1/dashboard-summary')->assertJsonPath('data.attendance.present', 1)->assertJsonPath('data.attendance.absent', 1)->assertJsonPath('data.attendance.excused', 1)->assertJsonPath('data.attendance.late', 1);
    }

    public function test_populated_permission_upgrade_preserves_existing_data_custom_grants_and_later_revocation(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $hash = $teacher->password;
        $code = $teacher->account_code;
        $assignment = DB::table('teacher_assignments')->sole();
        $permission = DB::table('permissions')->where('code', 'assignments.manage')->value('id');
        DB::table('permission_role')->where('permission_id', $permission)->delete();
        DB::table('permissions')->where('id', $permission)->delete();
        $before = DB::table('permission_role')->orderBy('role_id')->orderBy('permission_id')->get()->toJson();
        $migration = require database_path('migrations/2026_10_29_000020_add_scoped_assignment_permission.php');
        $migration->up();
        $permission = DB::table('permissions')->where('code', 'assignments.manage')->value('id');
        $this->assertSame($before, DB::table('permission_role')->where('permission_id', '!=', $permission)->orderBy('role_id')->orderBy('permission_id')->get()->toJson());
        $this->assertSame($hash, $teacher->fresh()->password);
        $this->assertSame($code, $teacher->fresh()->account_code);
        $this->assertDatabaseHas('students', ['id' => $student]);
        $this->assertEquals($assignment, DB::table('teacher_assignments')->sole());
        $coordinator = Role::where('code', 'coordinator')->value('id');
        DB::table('permission_role')->where('permission_id', $permission)->where('role_id', $coordinator)->delete();
        $migration->up();
        $this->assertDatabaseMissing('permission_role', ['permission_id' => $permission, 'role_id' => $coordinator]);
    }

    public function test_weekday_edits_preserve_prior_attendance_calendar_and_future_policy_changes(): void
    {
        // Keep October 31 in the future; this test compares preserved past days.
        $this->travelTo(Carbon::parse('2026-10-29 09:00:00'));
        $student = $this->student();
        $this->asUser($this->admin)->postJson('/api/v1/attendance/register', ['section_id' => $this->section, 'date' => '2026-10-03', 'status' => 'submitted', 'version' => 0, 'request_id' => (string) Str::uuid(), 'records' => [['student_id' => $student, 'status' => 'present']]])->assertOk();
        $before = $this->getJson('/api/v1/students/'.$student.'/attendance?month=2026-10')->json('data.school_days');
        $this->assertGreaterThan(0, $before);
        $this->putJson('/api/v1/academic-settings', ['weekdays' => [1, 2, 3, 4, 5], 'class_position_enabled' => false])->assertOk();
        $this->assertTrue(app(AcademicCalendar::class)->isSchoolDay($this->session, '2026-10-03'));
        $this->assertFalse(app(AcademicCalendar::class)->isSchoolDay($this->session, '2026-10-31'));
        $this->getJson('/api/v1/students/'.$student.'/attendance?month=2026-10')->assertJsonPath('data.school_days', $before);
    }

    public function test_payment_register_reports_allocations_refunds_and_actual_collected_amount(): void
    {
        $this->seed(FinanceConfigurationSeeder::class);
        $student = $this->student();
        $this->asUser($this->admin);
        $invoice = $this->postJson('/api/v1/finance/invoices', ['student_id' => $student, 'academic_session_id' => $this->session, 'period' => '2026-10', 'kind' => 'additional', 'issued_on' => '2026-10-01', 'due_on' => '2026-10-01', 'request_id' => (string) Str::uuid(), 'charges' => [['description' => 'Fictional charge', 'amount_minor' => 10000]]])->assertCreated()->json('data.id');
        $payment = $this->postJson('/api/v1/finance/payments', ['request_id' => (string) Str::uuid(), 'payment_method_id' => DB::table('payment_methods')->where('code', 'cash')->value('id'), 'paid_on' => now()->toDateString(), 'amount_minor' => 6000, 'allocations' => [['fee_invoice_id' => $invoice, 'amount_minor' => 6000]]])->assertCreated()->json('data.id');
        $allocation = DB::table('payment_allocations')->where('payment_id', $payment)->value('id');
        $this->postJson('/api/v1/finance/payments/'.$payment.'/refund', ['request_id' => (string) Str::uuid(), 'refunded_on' => now()->toDateString(), 'reason' => 'Fictional refund', 'allocations' => [['payment_allocation_id' => $allocation, 'amount_minor' => 2000]]])->assertCreated();
        $receipt = DB::table('payments')->find($payment)->receipt_number;
        $this->get('/api/v1/reports/payment_register?format=csv&status=verified')->assertOk()->assertSee($receipt)->assertSee('PKR 60.00')->assertSee('PKR 20.00')->assertSee('PKR 40.00')->assertDontSee($payment);
        $this->get('/api/v1/reports/payment_register?format=csv&status=pending')->assertOk()->assertDontSee($receipt);
        $this->getJson('/api/v1/finance/trial-balance')->assertOk()->assertJsonPath('data.balanced', true);
    }

    public function test_payroll_report_and_executive_dashboard_reflect_actual_partial_payment(): void
    {
        $this->seed(FinanceConfigurationSeeder::class);
        $teacher = $this->teacher();
        $profile = DB::table('staff_profiles')->where('user_id', $teacher->id)->value('id');
        Domain::insert('staff_compensation', ['staff_profile_id' => $profile, 'base_salary_minor' => 100000, 'effective_on' => '2026-01-01', 'approved_by' => $this->admin->id]);
        $this->asUser($this->admin);
        $period = $this->postJson('/api/v1/finance/payroll', ['period' => now()->subMonthNoOverflow()->format('Y-m')])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/finance/payroll/'.$period.'/approve')->assertOk();
        $this->postJson('/api/v1/finance/payroll/'.$period.'/pay', ['payment_method_id' => DB::table('payment_methods')->where('code', 'cash')->value('id'), 'paid_on' => now()->toDateString(), 'amount_minor' => 30000, 'request_id' => (string) Str::uuid()])->assertOk();
        $this->get('/api/v1/reports/payroll_summary?format=csv')->assertOk()->assertSee('INF-SG-PR-'.now()->subMonthNoOverflow()->format('Y-m'))->assertSee('PKR 1,000.00')->assertSee('PKR 300.00')->assertSee('PKR 700.00')->assertDontSee($period);
        $this->getJson('/api/v1/dashboard-summary')->assertJsonPath('data.payroll.total_minor', 100000)->assertJsonPath('data.payroll.paid_minor', 30000)->assertJsonPath('data.payroll.balance_minor', 70000);
        $this->asUser($teacher)->getJson('/api/v1/dashboard-summary')->assertOk()->assertJsonMissingPath('data.payroll')->assertJsonMissingPath('data.finance');
    }

    public function test_future_homework_publish_keeps_parent_and_subject_scopes(): void
    {
        $one = $this->student();
        $other = $this->student($this->otherSection);
        [, $parent] = $this->guardian($one);
        [, $outside] = $this->guardian($other, '0000000000002');
        $teacher = $this->teacher();
        $this->asUser($teacher);
        $body = ['section_id' => $this->section, 'subject_id' => $this->subject, 'date' => now()->addDay()->toDateString(), 'topic' => 'Fictional future homework', 'homework' => 'Read the fictional worksheet', 'whole_class' => true, 'students' => [], 'attachments' => [], 'status' => 'published', 'version' => 0, 'client_id' => (string) Str::uuid()];
        $id = $this->postJson('/api/v1/diary', $body)->assertOk()->json('data.id');
        $this->postJson('/api/v1/diary', [...$body, 'section_id' => $this->otherSection, 'client_id' => (string) Str::uuid()])->assertForbidden();
        $this->asUser($parent)->getJson('/api/v1/diary/'.$id.'?student_id='.$one)->assertOk()->assertJsonPath('data.topic', 'Fictional future homework');
        $this->asUser($outside)->getJson('/api/v1/diary/'.$id)->assertNotFound();
        $this->getJson('/api/v1/diary?student_id='.$other)->assertJsonCount(0, 'data.data');
    }
}
