<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Support\Domain;
use Database\Seeders\FinanceConfigurationSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\MobileTestCase;

class DashboardTest extends MobileTestCase
{
    public function test_super_admin_summary_uses_real_counts_even_without_role_grants(): void
    {
        $this->student();
        $this->student($this->otherSection);
        $this->teacher();
        DB::table('permission_role')->where('role_id', Role::where('code', 'super_admin')->value('id'))->delete();
        $this->asUser($this->admin)->getJson('/api/v1/dashboard-summary')->assertOk()->assertJsonPath('data.scope', 'school')->assertJsonPath('data.students.total', 2)->assertJsonPath('data.students.active', 2)->assertJsonPath('data.staff.active', 2)->assertJsonPath('data.staff.teachers', 1)->assertJsonPath('data.school.sections', 2)->assertJsonPath('data.finance.outstanding_minor', 0)->assertHeader('Cache-Control', 'no-store, private')->assertJsonMissingPath('data.students.items');
    }

    public function test_teacher_summary_is_assignment_scoped_and_revocation_is_immediate(): void
    {
        $this->student();
        $this->student($this->otherSection);
        $u = $this->teacher();
        $this->asUser($u)->getJson('/api/v1/dashboard-summary')->assertOk()->assertJsonPath('data.scope', 'assigned')->assertJsonPath('data.students.total', 1)->assertJsonPath('data.school.sections', 1)->assertJsonMissingPath('data.finance')->assertJsonMissingPath('data.staff')->assertJsonMissingPath('data.guardians');
        DB::table('teacher_assignments')->whereIn('staff_profile_id', DB::table('staff_profiles')->where('user_id', $u->id)->select('id'))->update(['is_active' => false]);
        $this->getJson('/api/v1/dashboard-summary')->assertOk()->assertJsonPath('data.students.total', 0)->assertJsonPath('data.school.sections', 0)->assertJsonPath('data.attendance.expected', 0);
    }

    public function test_coordinator_requests_and_announcements_do_not_include_other_sections(): void
    {
        $one = $this->student();
        $two = $this->student($this->otherSection);
        [, $parent] = $this->guardian($one);
        [, $other] = $this->guardian($two, '0000000000002');
        foreach ([[$parent, $one], [$other, $two]] as [$u,$student]) {
            $this->asUser($u)->postJson('/api/v1/tickets', ['student_id' => $student, 'category' => 'general', 'subject' => 'Fictional dashboard request', 'message' => 'Fictional request', 'attachments' => [], 'client_id' => (string) Str::uuid()])->assertCreated();
        }
        $this->asUser($this->admin);
        foreach ([[$this->section, 'Fictional assigned notice'], [$this->otherSection, 'Fictional excluded notice']] as [$section,$title]) {
            $this->postJson('/api/v1/announcements', ['title' => $title, 'body' => 'Fictional notice', 'kind' => 'notice', 'audience' => 'section', 'section_id' => $section, 'status' => 'published', 'version' => 0])->assertCreated();
        }
        $this->asUser($this->coordinator())->getJson('/api/v1/dashboard-summary')->assertOk()->assertJsonPath('data.students.total', 1)->assertJsonPath('data.open_requests', 1)->assertJsonCount(1, 'data.announcements')->assertJsonPath('data.announcements.0.title', 'Fictional assigned notice')->assertJsonMissingPath('data.finance')->assertJsonMissingPath('data.staff');
    }

    public function test_accountant_summary_has_finance_and_no_academic_or_staff_counts(): void
    {
        $this->student();
        $this->asUser($this->user('accountant_clerk'))->getJson('/api/v1/dashboard-summary')->assertOk()->assertJsonPath('data.students.total', 1)->assertJsonPath('data.finance.outstanding_minor', 0)->assertJsonMissingPath('data.attendance')->assertJsonMissingPath('data.academic')->assertJsonMissingPath('data.school')->assertJsonMissingPath('data.staff')->assertJsonMissingPath('data.open_requests');
    }

    public function test_parent_is_denied_staff_summary_and_retains_child_home(): void
    {
        [$g,$p] = $this->guardian($this->student());
        $this->asUser($p)->getJson('/api/v1/dashboard-summary')->assertForbidden();
        $this->getJson('/api/v1/parent/home')->assertOk()->assertJsonCount(1, 'data.children');
    }

    public function test_summary_requires_an_active_authenticated_session(): void
    {
        $this->getJson('/api/v1/dashboard-summary')->assertUnauthorized();
        $u = $this->teacher();
        $this->asUser($u);
        $u->update(['is_active' => false]);
        $this->getJson('/api/v1/dashboard-summary')->assertUnauthorized();
    }

    public function test_summary_cannot_be_retargeted_using_guessed_user_or_section_ids(): void
    {
        $this->student($this->otherSection);
        $this->asUser($this->teacher())->getJson('/api/v1/dashboard-summary?section_id='.$this->otherSection)->assertUnprocessable();
        $this->getJson('/api/v1/dashboard-summary?user_id='.$this->admin->id)->assertUnprocessable();
        $this->getJson('/api/v1/dashboard-summary')->assertOk()->assertJsonPath('data.students.total', 0);
    }

    public function test_submitted_attendance_coverage_and_calendar_overrides_are_scoped(): void
    {
        $one = $this->student();
        $two = $this->student($this->otherSection);
        $this->asUser($this->admin);
        foreach ([[$one, $this->section, 'present'], [$two, $this->otherSection, 'absent']] as [$student,$section,$status]) {
            $this->postJson('/api/v1/attendance/register', ['section_id' => $section, 'date' => now()->toDateString(), 'status' => 'submitted', 'version' => 0, 'request_id' => (string) Str::uuid(), 'records' => [['student_id' => $student, 'status' => $status]]])->assertOk();
        }
        $this->asUser($this->teacher())->getJson('/api/v1/dashboard-summary')->assertOk()->assertJsonPath('data.attendance.expected', 1)->assertJsonPath('data.attendance.present', 1)->assertJsonPath('data.attendance.absent', 0)->assertJsonPath('data.attendance.not_marked', 0);
        Domain::insert('school_days', ['academic_session_id' => $this->session, 'date' => now()->toDateString(), 'is_school_day' => false, 'label' => 'Fictional closure']);
        $this->getJson('/api/v1/dashboard-summary')->assertOk()->assertJsonPath('data.attendance.expected', 0)->assertJsonPath('data.attendance.coverage_percentage', null);
    }

    public function test_finance_summary_matches_verified_payments_and_adjustments_with_student_scope(): void
    {
        $this->seed(FinanceConfigurationSeeder::class);
        $one = $this->student();
        $two = $this->student($this->otherSection);
        $this->asUser($this->admin);
        $own = null;
        foreach ([[$one, 10000], [$two, 20000]] as [$student,$amount]) {
            $id = $this->postJson('/api/v1/finance/invoices', ['student_id' => $student, 'academic_session_id' => $this->session, 'period' => now()->format('Y-m'), 'kind' => 'additional', 'issued_on' => now()->subDays(2)->toDateString(), 'due_on' => now()->subDay()->toDateString(), 'request_id' => (string) Str::uuid(), 'charges' => [['description' => 'Fictional dashboard charge', 'amount_minor' => $amount]]])->assertCreated()->json('data.id');
            if ($student === $one) {
                $own = $id;
            }
        }
        $this->postJson('/api/v1/finance/payments', ['payment_method_id' => DB::table('payment_methods')->where('code', 'cash')->value('id'), 'paid_on' => now()->toDateString(), 'amount_minor' => 4000, 'request_id' => (string) Str::uuid(), 'allocations' => [['fee_invoice_id' => $own, 'amount_minor' => 4000]]])->assertCreated();
        $this->postJson('/api/v1/finance/invoices/'.$own.'/adjust', ['amount_minor' => -1000, 'reason' => 'Fictional adjustment', 'request_id' => (string) Str::uuid()])->assertCreated();
        $this->getJson('/api/v1/dashboard-summary')->assertOk()->assertJsonPath('data.finance.outstanding_minor', 25000)->assertJsonPath('data.finance.receipts_month_minor', 4000)->assertJsonPath('data.finance.students_with_balance', 2);
        $u = $this->teacher();
        DB::table('permission_role')->insert(['role_id' => Role::where('code', 'teacher')->value('id'), 'permission_id' => DB::table('permissions')->where('code', 'finance.read')->value('id')]);
        $this->asUser($u)->getJson('/api/v1/dashboard-summary')->assertOk()->assertJsonPath('data.finance.outstanding_minor', 5000)->assertJsonPath('data.finance.overdue_minor', 5000)->assertJsonPath('data.finance.students_with_balance', 1);
    }

    public function test_empty_unprivileged_staff_workspace_does_not_imply_a_schoolwide_grant(): void
    {
        $u = $this->teacher();
        DB::table('permission_role')->where('role_id', Role::where('code', 'teacher')->value('id'))->delete();
        $this->asUser($u)->getJson('/api/v1/dashboard-summary')->assertOk()->assertJsonMissingPath('data.students')->assertJsonMissingPath('data.staff')->assertJsonMissingPath('data.finance')->assertJsonMissingPath('data.attendance');
    }

    public function test_dashboard_query_count_does_not_grow_per_student(): void
    {
        $u = $this->teacher();
        $this->student();
        $this->asUser($u);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson('/api/v1/dashboard-summary')->assertOk();
        $before = count(DB::getQueryLog());
        DB::disableQueryLog();
        for ($i = 2; $i <= 15; $i++) {
            $this->student(roll: $i);
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson('/api/v1/dashboard-summary')->assertOk()->assertJsonPath('data.students.total', 15);
        $after = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertSame($before, $after);
    }
}
