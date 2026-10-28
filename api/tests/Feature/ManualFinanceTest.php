<?php

namespace Tests\Feature;

use App\Services\Finance\FeeService;
use App\Support\Domain;
use Database\Seeders\FinanceConfigurationSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Support\MobileTestCase;

class ManualFinanceTest extends MobileTestCase
{
    private function setupFees(): void
    {
        $this->seed(FinanceConfigurationSeeder::class);
        Domain::insert('fee_schedules', ['academic_session_id' => $this->session, 'class_id' => DB::table('sections')->where('id', $this->section)->value('class_id'), 'kind' => 'tuition', 'amount_minor' => 100000, 'effective_on' => '2026-01-01']);
        $this->asUser($this->admin);
    }

    private function invoice(string $student, array $extra = []): string
    {
        return app(FeeService::class)->generate($this->admin, [...['student_id' => $student, 'academic_session_id' => $this->session, 'period' => '2026-10', 'kind' => 'tuition', 'issued_on' => now()->toDateString(), 'due_on' => now()->toDateString(), 'request_id' => (string) Str::uuid()], ...$extra]);
    }

    private function payment(string $invoice, int $amount, string $method = 'cash'): array
    {
        return ['payment_method_id' => DB::table('payment_methods')->where('code', $method)->value('id'), 'paid_on' => now()->toDateString(), 'amount_minor' => $amount, 'notes' => 'Fictional manual receipt note', 'request_id' => (string) Str::uuid(), 'allocations' => [['fee_invoice_id' => $invoice, 'amount_minor' => $amount]]];
    }

    public function test_safe_defaults_insert_missing_methods_and_preserve_existing_customizations(): void
    {
        $this->artisan('school:seed-finance-defaults')->assertSuccessful();
        $this->artisan('school:seed-finance-defaults')->assertSuccessful();
        $this->assertDatabaseCount('payment_methods', 6);
        $this->assertDatabaseCount('fee_schedules', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(0, DB::table('payment_methods')->where('requires_verification', true)->count());
        $cash = DB::table('payment_methods')->where('code', 'cash')->first();
        DB::table('payment_methods')->where('id', $cash->id)->update(['name' => 'School cash desk', 'is_active' => false]);
        $this->artisan('school:seed-finance-defaults')->assertSuccessful();
        $this->assertDatabaseHas('payment_methods', ['id' => $cash->id, 'name' => 'School cash desk', 'is_active' => false]);
        $this->asUser($this->user('accountant_clerk'))->getJson('/api/v1/finance/payment-methods')->assertOk()->assertJsonCount(5, 'data.items')->assertDontSee('School cash desk');
    }

    public function test_every_standard_method_is_manual_with_partial_full_payments_history_and_no_network(): void
    {
        $this->setupFees();
        Http::preventStrayRequests();
        Http::fake();
        foreach (['cash', 'bank_transfer', 'cheque', 'easypaisa', 'jazzcash', 'other'] as $index => $method) {
            $student = $this->student(roll: $index + 1);
            $invoice = $this->invoice($student);
            $this->asUser($this->user('accountant_clerk'));
            $v = $this->payment($invoice, 25000, $method);
            $id = $this->postJson('/api/v1/finance/payments', $v)->assertCreated()->json('data.id');
            $this->postJson('/api/v1/finance/payments', $v)->assertCreated()->assertJsonPath('data.id', $id);
            $this->getJson('/api/v1/finance/payments/'.$id)->assertOk()->assertJsonPath('data.status', 'verified')->assertJsonPath('data.notes', $v['notes'])->assertJsonPath('data.reference', null);
            $this->getJson('/api/v1/students/'.$student.'/finance')->assertOk()->assertJsonPath('data.paid_minor', 25000)->assertJsonPath('data.balance_minor', 75000)->assertJsonPath('data.payments.0.id', $id);
            $this->postJson('/api/v1/finance/payments', $this->payment($invoice, 75000, $method))->assertCreated();
            $this->getJson('/api/v1/finance/invoices/'.$invoice)->assertOk()->assertJsonPath('data.balance_minor', 0)->assertJsonPath('data.status', 'paid');
            $this->assertNotNull(DB::table('payments')->find($id)->receipt_number);
        }
        $this->getJson('/api/v1/finance/payments')->assertOk()->assertJsonCount(12, 'data.data')->assertJsonStructure(['data' => ['data' => [['method', 'recorded_by_name', 'notes', 'receipt_number']]]]);
        $this->getJson('/api/v1/finance/trial-balance')->assertOk()->assertJsonPath('data.balanced', true);
        Http::assertNothingSent();
    }

    public function test_quote_posted_dues_discount_and_previous_balance_do_not_double_count(): void
    {
        $this->setupFees();
        $student = $this->student();
        Domain::insert('student_fee_assignments', ['student_id' => $student, 'academic_session_id' => $this->session, 'effective_on' => '2026-01-01', 'tuition_override_minor' => 120000, 'approved_by' => $this->admin->id]);
        $discount = $this->postJson('/api/v1/finance/discounts', ['student_id' => $student, 'academic_session_id' => $this->session, 'discount_policy_id' => DB::table('discount_policies')->where('code', 'special')->value('id'), 'basis_points' => 2000, 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'eligibility_notes' => 'Fictional approved concession'])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/finance/discounts/'.$discount.'/approve', ['eligibility_verified' => true])->assertOk();
        $this->getJson('/api/v1/students/'.$student.'/finance')->assertOk()->assertJsonPath('data.configured_fee_minor', 120000)->assertJsonPath('data.configured_discount_minor', 24000)->assertJsonPath('data.unbilled_minor', 96000)->assertJsonPath('data.voucher_required', true)->assertJsonPath('data.balance_minor', 0);
        DB::table('enrollments')->where('student_id', $student)->update(['starts_on' => '2026-09-01']);
        $this->invoice($student, ['period' => '2026-09', 'kind' => 'opening_balance', 'charges' => [['description' => 'Previous dues', 'amount_minor' => 50000]]]);
        $invoice = $this->invoice($student);
        $this->postJson('/api/v1/finance/payments', $this->payment($invoice, 26000))->assertCreated();
        $this->getJson('/api/v1/students/'.$student.'/finance')->assertOk()->assertJsonPath('data.voucher_required', false)->assertJsonPath('data.total_minor', 146000)->assertJsonPath('data.discount_minor', 24000)->assertJsonPath('data.arrears_minor', 50000)->assertJsonPath('data.balance_minor', 120000)->assertJsonPath('data.current_period_due_minor', 70000);
        $this->getJson('/api/v1/finance/summary?academic_session_id='.$this->session)->assertOk()->assertJsonPath('data.total_minor', 146000)->assertJsonPath('data.paid_minor', 26000)->assertJsonPath('data.balance_minor', 120000)->assertJsonPath('data.students_partially_paid', 1);
    }

    public function test_invalid_overpaid_inactive_and_reused_requests_do_not_create_extra_transactions(): void
    {
        $this->setupFees();
        $invoice = $this->invoice($this->student());
        $v = $this->payment($invoice, 100001);
        $this->postJson('/api/v1/finance/payments', $v)->assertUnprocessable();
        $this->postJson('/api/v1/finance/payments', [...$v, 'amount_minor' => 0])->assertUnprocessable();
        DB::table('payment_methods')->where('id', $v['payment_method_id'])->update(['is_active' => false]);
        $this->postJson('/api/v1/finance/payments', $this->payment($invoice, 50000))->assertNotFound();
        $this->assertDatabaseCount('payments', 0);
        DB::table('payment_methods')->where('id', $v['payment_method_id'])->update(['is_active' => true]);
        $v = $this->payment($invoice, 50000);
        $this->postJson('/api/v1/finance/payments', $v)->assertCreated();
        $this->postJson('/api/v1/finance/payments', [...$v, 'notes' => 'Changed payload'])->assertConflict();
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(1, DB::table('journal_entries')->where('reference_type', 'payment')->count());
    }

    public function test_payroll_partial_staff_allocations_full_settlement_and_idempotency(): void
    {
        $this->setupFees();
        foreach ([100000, 200000] as $salary) {
            $teacher = $this->teacher($salary === 100000 ? $this->section : $this->otherSection);
            $staff = DB::table('staff_profiles')->where('user_id', $teacher->id)->value('id');
            $this->postJson('/api/v1/finance/compensation', ['staff_profile_id' => $staff, 'base_salary_minor' => $salary, 'effective_on' => '2026-01-01'])->assertCreated();
        }
        $id = $this->postJson('/api/v1/finance/payroll', ['period' => '2026-09'])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/finance/payroll/'.$id.'/approve')->assertOk();
        $items = DB::table('payroll_items')->where('payroll_period_id', $id)->orderBy('net_salary_minor')->get();
        $v = ['payment_method_id' => DB::table('payment_methods')->where('code', 'cash')->value('id'), 'paid_on' => now()->toDateString(), 'amount_minor' => 40000, 'request_id' => (string) Str::uuid(), 'notes' => 'Fictional salary instalment', 'allocations' => [['payroll_item_id' => $items[0]->id, 'amount_minor' => 40000]]];
        $this->asUser($this->user('accountant_clerk'));
        $this->postJson('/api/v1/finance/payroll/'.$id.'/pay', $v)->assertOk();
        $this->postJson('/api/v1/finance/payroll/'.$id.'/pay', $v)->assertOk();
        $this->getJson('/api/v1/finance/payroll/'.$id)->assertOk()->assertJsonPath('data.status', 'approved')->assertJsonPath('data.payment_status', 'partially_paid')->assertJsonPath('data.paid_minor', 40000)->assertJsonPath('data.balance_minor', 260000)->assertJsonCount(1, 'data.payments');
        $this->postJson('/api/v1/finance/payroll/'.$id.'/pay', [...$v, 'notes' => 'Changed'])->assertConflict();
        $this->postJson('/api/v1/finance/payroll/'.$id.'/pay', [...$v, 'request_id' => (string) Str::uuid(), 'amount_minor' => 60001, 'allocations' => [['payroll_item_id' => $items[0]->id, 'amount_minor' => 60001]]])->assertUnprocessable();
        foreach ([[$items[0]->id, 60000], [$items[1]->id, 200000]] as [$item, $amount]) {
            $this->postJson('/api/v1/finance/payroll/'.$id.'/pay', [...$v, 'request_id' => (string) Str::uuid(), 'amount_minor' => $amount, 'allocations' => [['payroll_item_id' => $item, 'amount_minor' => $amount]]])->assertOk();
        }
        $this->getJson('/api/v1/finance/payroll/'.$id)->assertOk()->assertJsonPath('data.status', 'paid')->assertJsonPath('data.paid_minor', 300000)->assertJsonPath('data.balance_minor', 0)->assertJsonCount(3, 'data.payments');
        $this->getJson('/api/v1/finance/summary')->assertOk()->assertJsonPath('data.payroll.paid_minor', 300000)->assertJsonPath('data.payroll.balance_minor', 0);
        $this->getJson('/api/v1/finance/trial-balance')->assertOk()->assertJsonPath('data.balanced', true);
        $this->assertDatabaseCount('payroll_payments', 3);
    }

    public function test_admin_cannot_access_payroll_or_post_payments_and_parent_cannot_access_summary(): void
    {
        $this->setupFees();
        $this->asUser($this->user('school_administrator'))->getJson('/api/v1/finance/payroll')->assertForbidden();
        $this->postJson('/api/v1/finance/payments', [])->assertForbidden();
        $this->getJson('/api/v1/finance/journals')->assertForbidden();
        $this->getJson('/api/v1/finance/trial-balance')->assertForbidden();
        $this->getJson('/api/v1/finance/summary')->assertOk()->assertJsonMissingPath('data.payroll');
        [, $parent] = $this->guardian($this->student());
        $this->asUser($parent)->getJson('/api/v1/finance/summary')->assertForbidden();
        $this->getJson('/api/v1/finance/payment-methods')->assertForbidden();
    }

    public function test_session_filters_isolate_invoices_payments_and_summary_without_losing_old_dues_by_default(): void
    {
        $this->setupFees();
        $student = $this->student();
        $current = $this->invoice($student);
        $oldSession = Domain::insert('academic_sessions', ['name' => 'Fictional previous session', 'starts_on' => '2025-01-01', 'ends_on' => '2025-12-31']);
        $section = Domain::insert('sections', ['class_id' => DB::table('sections')->where('id', $this->section)->value('class_id'), 'academic_session_id' => $oldSession, 'name' => 'Historical']);
        Domain::insert('enrollments', ['student_id' => $student, 'academic_session_id' => $oldSession, 'section_id' => $section, 'roll_number' => 1, 'starts_on' => '2025-01-01', 'ends_on' => '2025-12-31', 'status' => 'withdrawn', 'created_by' => $this->admin->id]);
        $old = $this->invoice($student, ['academic_session_id' => $oldSession, 'period' => '2025-12', 'kind' => 'opening_balance', 'charges' => [['description' => 'Old dues', 'amount_minor' => 20000]]]);
        $this->postJson('/api/v1/finance/payments', $this->payment($old, 5000))->assertCreated();
        $this->getJson('/api/v1/students/'.$student.'/finance')->assertOk()->assertJsonPath('data.balance_minor', 115000)->assertJsonPath('data.arrears_minor', 15000);
        $this->getJson('/api/v1/students/'.$student.'/finance?academic_session_id='.$this->session)->assertOk()->assertJsonPath('data.balance_minor', 100000)->assertJsonCount(0, 'data.payments')->assertJsonCount(1, 'data.items');
        $this->getJson('/api/v1/finance/summary?academic_session_id='.$oldSession)->assertOk()->assertJsonPath('data.total_minor', 20000)->assertJsonPath('data.balance_minor', 15000);
        $this->getJson('/api/v1/finance/payments?academic_session_id='.$this->session)->assertOk()->assertJsonCount(0, 'data.data');
        $this->postJson('/api/v1/finance/payments', [...$this->payment($current, 1000), 'allocations' => [['fee_invoice_id' => $old, 'amount_minor' => 2000]]])->assertUnprocessable();
    }

    public function test_historical_paid_payroll_remains_paid_without_backfilling_or_reposting_journals(): void
    {
        $this->setupFees();
        $teacher = $this->teacher();
        $p = Domain::insert('payroll_periods', ['period' => '2026-08', 'status' => 'paid', 'total_minor' => 123000, 'created_by' => $this->admin->id, 'paid_on' => '2026-09-01', 'payment_method_id' => DB::table('payment_methods')->where('code', 'cash')->value('id')]);
        Domain::insert('payroll_items', ['payroll_period_id' => $p, 'staff_profile_id' => DB::table('staff_profiles')->where('user_id', $teacher->id)->value('id'), 'base_salary_minor' => 123000, 'adjustment_minor' => 0, 'net_salary_minor' => 123000]);
        $this->getJson('/api/v1/finance/payroll/'.$p)->assertOk()->assertJsonPath('data.paid_minor', 123000)->assertJsonPath('data.balance_minor', 0)->assertJsonPath('data.items.0.payment_status', 'paid');
        $this->assertDatabaseCount('payroll_payments', 0);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_salary_payment_requires_idempotency_and_rejects_cross_period_allocation(): void
    {
        $this->setupFees();
        $teacher = $this->teacher();
        $staff = DB::table('staff_profiles')->where('user_id', $teacher->id)->value('id');
        $this->postJson('/api/v1/finance/compensation', ['staff_profile_id' => $staff, 'base_salary_minor' => 100000, 'effective_on' => '2026-01-01'])->assertCreated();
        $ids = [];
        foreach (['2026-08', '2026-09'] as $period) {
            $ids[] = $id = $this->postJson('/api/v1/finance/payroll', ['period' => $period])->assertCreated()->json('data.id');
            $this->postJson('/api/v1/finance/payroll/'.$id.'/approve')->assertOk();
        }
        $v = ['payment_method_id' => DB::table('payment_methods')->where('code', 'cash')->value('id'), 'paid_on' => now()->toDateString(), 'amount_minor' => 10000];
        $this->postJson('/api/v1/finance/payroll/'.$ids[0].'/pay', $v)->assertUnprocessable();
        $this->postJson('/api/v1/finance/payroll/'.$ids[0].'/pay', [...$v, 'request_id' => (string) Str::uuid(), 'allocations' => [['payroll_item_id' => DB::table('payroll_items')->where('payroll_period_id', $ids[1])->value('id'), 'amount_minor' => 10000]]])->assertUnprocessable();
        $this->assertDatabaseCount('payroll_payments', 0);
        $this->assertSame(2, DB::table('journal_entries')->count());
    }

    public function test_partial_salary_is_visible_only_to_its_employee_and_history_is_immutable(): void
    {
        $this->setupFees();
        $teacher = $this->teacher();
        $staff = DB::table('staff_profiles')->where('user_id', $teacher->id)->value('id');
        $this->postJson('/api/v1/finance/compensation', ['staff_profile_id' => $staff, 'base_salary_minor' => 100000, 'effective_on' => '2026-01-01'])->assertCreated();
        $id = $this->postJson('/api/v1/finance/payroll', ['period' => '2026-09'])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/finance/payroll/'.$id.'/approve')->assertOk();
        $item = DB::table('payroll_items')->where('payroll_period_id', $id)->value('id');
        $this->postJson('/api/v1/finance/payroll/'.$id.'/pay', ['payment_method_id' => DB::table('payment_methods')->where('code', 'cash')->value('id'), 'paid_on' => now()->toDateString(), 'amount_minor' => 25000, 'request_id' => (string) Str::uuid(), 'notes' => 'Fictional private salary note'])->assertOk();
        $this->asUser($teacher)->getJson('/api/v1/my-staff-profile')->assertOk()->assertJsonPath('data.items.0.paid_minor', 25000)->assertJsonPath('data.items.0.balance_minor', 75000)->assertJsonCount(1, 'data.items.0.payments');
        $this->asUser($this->user('school_administrator'))->getJson('/api/v1/reports/salary_slip?payroll_item_id='.$item)->assertForbidden();
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->expectException(QueryException::class);
            DB::table('payroll_payments')->where('payroll_period_id', $id)->update(['amount_minor' => 1]);
        }
    }

    public function test_method_settings_change_future_manual_review_without_rewriting_pending_history(): void
    {
        $this->setupFees();
        $invoice = $this->invoice($this->student());
        $bank = DB::table('payment_methods')->where('code', 'bank_transfer')->value('id');
        DB::table('payment_methods')->where('id', $bank)->update(['requires_verification' => true]);
        $pending = $this->postJson('/api/v1/finance/payments', $this->payment($invoice, 10000, 'bank_transfer'))->assertCreated()->json('data.id');
        $this->asUser($this->user('school_administrator'))->patchJson('/api/v1/finance/payment-methods/'.$bank, ['requires_verification' => false, 'name' => 'Manual transfer'])->assertOk();
        $this->assertDatabaseHas('payments', ['id' => $pending, 'status' => 'pending']);
        $this->patchJson('/api/v1/finance/payment-methods/'.$bank, ['account_id' => DB::table('accounts')->where('code', 'cash')->value('id')])->assertUnprocessable();
        $cash = DB::table('payment_methods')->where('code', 'cash')->value('id');
        $this->patchJson('/api/v1/finance/payment-methods/'.$cash, ['requires_verification' => true])->assertUnprocessable();
        $this->asUser($this->user('accountant_clerk'))->patchJson('/api/v1/finance/payment-methods/'.$bank, ['is_active' => false])->assertForbidden();
        $id = $this->postJson('/api/v1/finance/payments', $this->payment($invoice, 10000, 'bank_transfer'))->assertCreated()->json('data.id');
        $this->assertDatabaseHas('payments', ['id' => $id, 'status' => 'verified']);
        $this->getJson('/api/v1/finance/invoices/'.$invoice)->assertOk()->assertJsonPath('data.balance_minor', 90000);
    }
}
