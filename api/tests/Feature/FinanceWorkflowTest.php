<?php

namespace Tests\Feature;

use App\Services\Finance\FeeService;
use App\Services\Finance\JournalService;
use App\Support\Domain;
use Database\Seeders\FinanceConfigurationSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\MobileTestCase;

class FinanceWorkflowTest extends MobileTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(FinanceConfigurationSeeder::class);
        Domain::insert('fee_schedules', ['academic_session_id' => $this->session, 'class_id' => DB::table('sections')->where('id', $this->section)->value('class_id'), 'kind' => 'tuition', 'amount_minor' => 140000, 'effective_on' => '2026-01-01']);
    }

    private function invoice(string $student, array $extra = []): string
    {
        return app(FeeService::class)->generate($this->admin, [...['student_id' => $student, 'academic_session_id' => $this->session, 'period' => '2026-10', 'kind' => 'tuition', 'issued_on' => now()->toDateString(), 'due_on' => now()->addDays(10)->toDateString(), 'request_id' => (string) Str::uuid()], ...$extra]);
    }

    private function payment(string $invoice, int $amount = 40000, string $method = 'cash'): array
    {
        return ['payment_method_id' => DB::table('payment_methods')->where('code', $method)->value('id'), 'paid_on' => now()->toDateString(), 'amount_minor' => $amount, 'request_id' => (string) Str::uuid(), 'reference' => $method === 'cash' ? null : 'FICTIONAL-BANK-'.Str::uuid(), 'allocations' => [['fee_invoice_id' => $invoice, 'amount_minor' => $amount]]];
    }

    public function test_default_2026_fee_configuration_is_exact_and_idempotent(): void
    {
        $this->seed(FinanceConfigurationSeeder::class);
        $session = DB::table('academic_sessions')->where('name', '2026-27')->value('id');
        $rows = DB::table('fee_schedules as f')->join('classes as c', 'c.id', '=', 'f.class_id')->where('f.academic_session_id', $session)->where('kind', 'tuition')->pluck('f.amount_minor', 'c.name');
        $this->assertSame(100000, (int) $rows['Playgroup']);
        $this->assertSame(260000, (int) $rows['Grade 8']);
        $this->assertCount(11, $rows);
        $this->assertSame(11, DB::table('fee_schedules')->where('academic_session_id', $session)->where('kind', 'admission_promotion')->where('amount_minor', 300000)->count());
    }

    public function test_partial_payment_idempotency_receipt_refund_and_balanced_ledger(): void
    {
        $student = $this->student();
        $invoice = $this->invoice($student);
        $v = $this->payment($invoice);
        $this->asUser($this->user('accountant_clerk'));
        $id = $this->postJson('/api/v1/finance/payments', $v)->assertCreated()->json('data.id');
        $this->postJson('/api/v1/finance/payments', $v)->assertCreated()->assertJsonPath('data.id', $id);
        $this->assertDatabaseCount('payments', 1);
        $this->getJson('/api/v1/finance/invoices/'.$invoice)->assertOk()->assertJsonPath('data.balance_minor', 100000)->assertJsonPath('data.status', 'partially_paid');
        $this->assertNotNull(DB::table('payments')->find($id)->receipt_number);
        $this->postJson('/api/v1/finance/payments', [...$v, 'amount_minor' => 50000])->assertStatus(409);
        $allocation = DB::table('payment_allocations')->where('payment_id', $id)->first();
        $this->asUser($this->admin);
        $refund = ['refunded_on' => now()->toDateString(), 'reason' => 'Fictional approved partial refund', 'request_id' => (string) Str::uuid(), 'allocations' => [['payment_allocation_id' => $allocation->id, 'amount_minor' => 10000]]];
        $this->postJson('/api/v1/finance/payments/'.$id.'/refund', $refund)->assertCreated();
        $this->postJson('/api/v1/finance/payments/'.$id.'/refund', $refund)->assertCreated();
        $this->assertDatabaseCount('payment_refunds', 1);
        $this->getJson('/api/v1/finance/invoices/'.$invoice)->assertJsonPath('data.balance_minor', 110000);
        $this->getJson('/api/v1/finance/trial-balance?from=2026-01-01&to=2026-12-31')->assertOk()->assertJsonPath('data.balanced', true);
    }

    public function test_bank_payment_does_not_reduce_balance_before_verification(): void
    {
        $invoice = $this->invoice($this->student());
        $v = $this->payment($invoice, 140000, 'bank_transfer');
        $this->asUser($this->user('accountant_clerk'));
        $id = $this->postJson('/api/v1/finance/payments', $v)->assertCreated()->json('data.id');
        $this->getJson('/api/v1/finance/invoices/'.$invoice)->assertJsonPath('data.balance_minor', 140000);
        $this->postJson('/api/v1/finance/payments/'.$id.'/verify')->assertForbidden();
        $this->asUser($this->admin)->postJson('/api/v1/finance/payments/'.$id.'/verify')->assertOk();
        $this->getJson('/api/v1/finance/invoices/'.$invoice)->assertJsonPath('data.status', 'paid');
    }

    public function test_cash_needs_no_bank_fields_and_records_notes_receiver_and_receipt(): void
    {
        $student = $this->student();
        $invoice = $this->invoice($student);
        $this->asUser($this->user('accountant_clerk'));
        $v = $this->payment($invoice);
        unset($v['reference']);
        $id = $this->postJson('/api/v1/finance/payments', [...$v, 'notes' => 'Received at school office'])->assertCreated()->json('data.id');
        $this->getJson('/api/v1/finance/payments/'.$id)->assertOk()->assertJsonPath('data.method', 'Cash')->assertJsonPath('data.notes', 'Received at school office')->assertJsonPath('data.status', 'verified');
        $this->getJson('/api/v1/students/'.$student.'/finance')->assertOk()->assertJsonPath('data.paid_minor', 40000)->assertJsonPath('data.balance_minor', 100000)->assertJsonPath('data.student.full_name', 'Fictional Test Student');
        $this->getJson('/api/v1/reports/receipt?payment_id='.$id.'&format=csv')->assertOk()->assertSee('Received by')->assertSee('Received at school office')->assertSee('Fictional Test Student')->assertSee('Balance');
    }

    public function test_invalid_amounts_overpayment_and_duplicate_allocations_are_rejected(): void
    {
        $invoice = $this->invoice($this->student());
        $this->asUser($this->admin);
        foreach ([0, -1, 140001] as $amount) {
            $this->postJson('/api/v1/finance/payments', $this->payment($invoice, $amount))->assertUnprocessable();
        }
        $v = $this->payment($invoice);
        $v['allocations'][] = $v['allocations'][0];
        $v['amount_minor'] = 80000;
        $this->postJson('/api/v1/finance/payments', $v)->assertUnprocessable();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_payment_denies_teacher_parent_inactive_student_and_inactive_method(): void
    {
        $student = $this->student();
        $invoice = $this->invoice($student);
        $v = $this->payment($invoice);
        $this->asUser($this->teacher())->postJson('/api/v1/finance/payments', $v)->assertForbidden();
        [, $parent] = $this->guardian($student);
        $this->asUser($parent)->postJson('/api/v1/finance/payments', $v)->assertForbidden();
        $this->asUser($this->admin);
        DB::table('students')->where('id', $student)->update(['status' => 'withdrawn']);
        $this->postJson('/api/v1/finance/payments', $v)->assertUnprocessable();
        DB::table('students')->where('id', $student)->update(['status' => 'active']);
        DB::table('payment_methods')->where('id', $v['payment_method_id'])->update(['is_active' => false]);
        $this->postJson('/api/v1/finance/payments', $v)->assertNotFound();
    }

    public function test_manual_methods_allow_missing_reference_and_pending_verification_cannot_overpay(): void
    {
        $invoice = $this->invoice($this->student());
        $this->asUser($this->admin);
        foreach (['bank_transfer', 'cheque', 'easypaisa', 'jazzcash', 'other'] as $method) {
            $v = $this->payment($invoice, 10000, $method);
            unset($v['reference']);
            $id = $this->postJson('/api/v1/finance/payments', $v)->assertCreated()->json('data.id');
            if (in_array($method, ['bank_transfer', 'cheque'])) {
                $this->postJson('/api/v1/finance/payments/'.$id.'/verify')->assertOk();
            }
        }
        $pending = $this->postJson('/api/v1/finance/payments', $this->payment($invoice, 90000, 'bank_transfer'))->assertCreated()->json('data.id');
        $this->postJson('/api/v1/finance/payments', $this->payment($invoice, 90000))->assertCreated();
        $this->postJson('/api/v1/finance/payments/'.$pending.'/verify')->assertConflict();
        $this->getJson('/api/v1/finance/invoices/'.$invoice)->assertJsonPath('data.balance_minor', 0);
    }

    public function test_effective_date_configuration_upserts_preserve_ids_and_unique_keys(): void
    {
        $this->asUser($this->user('school_administrator'));
        $class = DB::table('sections')->where('id', $this->section)->value('class_id');
        $schedule = ['academic_session_id' => $this->session, 'class_id' => $class, 'kind' => 'tuition', 'effective_on' => '2026-01-01', 'amount_minor' => 150000];
        $id = $this->postJson('/api/v1/finance/fee-schedules', $schedule)->assertCreated()->json('data.id');
        $this->postJson('/api/v1/finance/fee-schedules', [...$schedule, 'amount_minor' => 160000])->assertCreated()->assertJsonPath('data.id', $id);
        $this->assertSame(1, DB::table('fee_schedules')->where('academic_session_id', $this->session)->count());
        $student = $this->student();
        $assignment = ['student_id' => $student, 'academic_session_id' => $this->session, 'effective_on' => '2026-10-01', 'tuition_override_minor' => 120000];
        $id = $this->postJson('/api/v1/finance/fee-assignments', $assignment)->assertCreated()->json('data.id');
        $this->postJson('/api/v1/finance/fee-assignments', [...$assignment, 'tuition_override_minor' => 110000])->assertCreated()->assertJsonPath('data.id', $id);
        $this->assertDatabaseCount('student_fee_assignments', 1);
        $invoice = $this->invoice($student);
        $this->assertDatabaseHas('fee_invoices', ['id' => $invoice, 'total_minor' => 110000]);
        $this->asUser($this->user('accountant_clerk'))->postJson('/api/v1/finance/fee-schedules', $schedule)->assertForbidden();
    }

    public function test_payroll_cash_retries_authorization_and_compensation_upsert(): void
    {
        $teacher = $this->teacher();
        $staff = DB::table('staff_profiles')->where('user_id', $teacher->id)->value('id');
        $this->asUser($this->admin);
        $v = ['staff_profile_id' => $staff, 'base_salary_minor' => 3000000, 'effective_on' => '2026-01-01'];
        $id = $this->postJson('/api/v1/finance/compensation', $v)->assertCreated()->json('data.id');
        $this->postJson('/api/v1/finance/compensation', $v)->assertCreated()->assertJsonPath('data.id', $id);
        $this->postJson('/api/v1/finance/compensation', [...$v, 'base_salary_minor' => 3100000])->assertUnprocessable();
        $this->assertDatabaseCount('staff_compensation', 1);
        $payroll = $this->postJson('/api/v1/finance/payroll', ['period' => '2026-09'])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/finance/payroll', ['period' => '2026-09'])->assertConflict();
        $pay = ['payment_method_id' => DB::table('payment_methods')->where('code', 'cash')->value('id'), 'paid_on' => now()->toDateString()];
        $this->asUser($teacher)->postJson('/api/v1/finance/payroll', ['period' => '2026-08'])->assertForbidden();
        $this->postJson('/api/v1/finance/payroll/'.$payroll.'/pay', $pay)->assertForbidden();
        $this->postJson('/api/v1/finance/compensation', $v)->assertForbidden();
        $this->asUser($this->user('accountant_clerk'))->postJson('/api/v1/finance/payroll/'.$payroll.'/approve')->assertForbidden();
        $this->asUser($this->admin)->postJson('/api/v1/finance/payroll/'.$payroll.'/approve')->assertOk();
        $this->asUser($this->user('accountant_clerk'));
        $this->postJson('/api/v1/finance/payroll/'.$payroll.'/pay', $pay)->assertOk();
        $this->postJson('/api/v1/finance/payroll/'.$payroll.'/pay', $pay)->assertOk();
        $this->getJson('/api/v1/finance/payroll/'.$payroll)->assertOk()->assertJsonPath('data.paid_minor', 3000000)->assertJsonPath('data.method', 'Cash');
        $this->getJson('/api/v1/finance/payroll?period=2026-08')->assertOk()->assertJsonCount(0, 'data.items');
        $this->assertSame(1, DB::table('journal_entries')->where('posting_key', 'payroll-disbursement:'.$payroll)->count());
        $this->getJson('/api/v1/finance/trial-balance')->assertOk()->assertJsonPath('data.balanced', true);
    }

    public function test_merit_discount_limit_approval_and_non_stacking(): void
    {
        $student = $this->student();
        $this->asUser($this->admin);
        $policy = DB::table('discount_policies')->where('code', 'merit')->value('id');
        $base = ['student_id' => $student, 'academic_session_id' => $this->session, 'discount_policy_id' => $policy, 'basis_points' => 7000, 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'eligibility_notes' => 'Fictional verified scholarship eligibility'];
        $this->postJson('/api/v1/finance/discounts', [...$base, 'basis_points' => 7001])->assertUnprocessable();
        $id = $this->postJson('/api/v1/finance/discounts', $base)->assertCreated()->json('data.id');
        $this->postJson('/api/v1/finance/discounts/'.$id.'/approve', ['eligibility_verified' => true])->assertOk();
        $other = $this->postJson('/api/v1/finance/discounts', [...$base, 'discount_policy_id' => DB::table('discount_policies')->where('code', 'orphan')->value('id'), 'basis_points' => 3000])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/finance/discounts/'.$other.'/approve', ['eligibility_verified' => true])->assertOk();
        $invoice = $this->invoice($student);
        $this->assertDatabaseHas('fee_invoices', ['id' => $invoice, 'discount_minor' => 98000, 'total_minor' => 42000]);
    }

    public function test_expense_above_500_requires_prior_management_approval(): void
    {
        $this->asUser($this->user('accountant_clerk'));
        $id = $this->postJson('/api/v1/finance/expenses', ['expense_category_id' => DB::table('expense_categories')->where('code', 'stationery')->value('id'), 'description' => 'Fictional supplies', 'amount_minor' => 50001, 'expense_on' => now()->toDateString()])->assertCreated()->json('data.id');
        $payment = ['payment_method_id' => DB::table('payment_methods')->where('code', 'cash')->value('id'), 'paid_on' => now()->toDateString()];
        $this->postJson('/api/v1/finance/expenses/'.$id.'/pay', $payment)->assertForbidden();
        $this->asUser($this->admin)->postJson('/api/v1/finance/expenses/'.$id.'/approve')->assertOk();
        $this->postJson('/api/v1/finance/expenses/'.$id.'/pay', $payment)->assertOk();
        $this->postJson('/api/v1/finance/expenses/'.$id.'/pay', $payment)->assertOk();
        $this->assertSame(1, DB::table('journal_entries')->where('reference_type', 'expense')->count());
    }

    public function test_parent_financial_idor_teacher_denial_and_own_child_balance(): void
    {
        $one = $this->student();
        $two = $this->student($this->otherSection);
        $i1 = $this->invoice($one);
        $i2 = $this->invoice($two);
        [, $parent] = $this->guardian($one);
        $this->asUser($parent)->getJson('/api/v1/students/'.$one.'/finance')->assertOk()->assertJsonPath('data.balance_minor', 140000);
        $this->getJson('/api/v1/students/'.$two.'/finance')->assertNotFound();
        $this->getJson('/api/v1/finance/invoices/'.$i2)->assertNotFound();
        $this->getJson('/api/v1/finance/payments')->assertForbidden();
        $this->asUser($this->teacher())->getJson('/api/v1/students/'.$one.'/finance')->assertForbidden();
    }

    public function test_payroll_approval_before_disbursement_and_private_salary_slips(): void
    {
        $teacher = $this->teacher();
        $profile = DB::table('staff_profiles')->where('user_id', $teacher->id)->first();
        $this->asUser($this->admin);
        $this->postJson('/api/v1/finance/compensation', ['staff_profile_id' => $profile->id, 'base_salary_minor' => 3000000, 'effective_on' => '2026-01-01'])->assertCreated();
        $this->postJson('/api/v1/finance/payroll-adjustments', ['staff_profile_id' => $profile->id, 'period' => '2026-09', 'amount_minor' => -10000, 'reason' => 'Fictional authorized deduction'])->assertCreated();
        $payroll = $this->postJson('/api/v1/finance/payroll', ['period' => '2026-09'])->assertCreated()->json('data.id');
        $pay = ['payment_method_id' => DB::table('payment_methods')->where('code', 'cash')->value('id'), 'paid_on' => now()->toDateString()];
        $this->postJson('/api/v1/finance/payroll/'.$payroll.'/pay', $pay)->assertForbidden();
        $this->postJson('/api/v1/finance/payroll/'.$payroll.'/approve')->assertOk();
        $this->postJson('/api/v1/finance/payroll/'.$payroll.'/pay', $pay)->assertOk();
        $this->asUser($teacher)->getJson('/api/v1/my-staff-profile')->assertOk()->assertJsonPath('data.items.0.net_salary_minor', 2990000);
        $this->getJson('/api/v1/finance/payroll/'.$payroll)->assertForbidden();
    }

    public function test_finalized_journal_cannot_be_silently_edited(): void
    {
        $this->invoice($this->student());
        $id = DB::table('journal_entries')->value('id');
        $this->expectException(QueryException::class);
        DB::table('journal_entries')->where('id', $id)->update(['description' => 'Tampering']);
    }

    public function test_unbalanced_journal_rejected_by_service(): void
    {
        $this->expectException(ValidationException::class);
        $j = app(JournalService::class);
        $j->post($this->admin, 'bad', now()->toDateString(), 'Invalid test', 'test', (string) Str::uuid(), [['account_id' => $j->account('cash'), 'debit_minor' => 100], ['account_id' => $j->account('receivables'), 'credit_minor' => 99]]);
    }
}
