<?php

namespace Tests\Feature;

use App\Services\Finance\MonthlyBilling;
use App\Support\Domain;
use Database\Seeders\FinanceConfigurationSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\MobileTestCase;

class AdmissionFeeLifecycleTest extends MobileTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(FinanceConfigurationSeeder::class);
        Domain::insert('fee_schedules', ['academic_session_id' => $this->session, 'class_id' => DB::table('sections')->where('id', $this->section)->value('class_id'), 'kind' => 'tuition', 'amount_minor' => 100000, 'effective_on' => '2026-01-01']);
        Domain::insert('fee_schedules', ['academic_session_id' => $this->session, 'class_id' => DB::table('sections')->where('id', $this->section)->value('class_id'), 'kind' => 'admission_promotion', 'amount_minor' => 50000, 'effective_on' => '2026-01-01']);
        $this->asUser($this->admin);
    }

    private function input(array $finance = []): array
    {
        return [...$this->admissionFields(), 'request_id' => (string) Str::uuid(), ...($finance ? ['admission_finance' => $finance] : [])];
    }

    private function admissionFields(): array
    {
        return [...parent::admission(), 'admitted_on' => '2026-03-01'];
    }

    private function march(array $finance = []): string
    {
        return $this->postJson('/api/v1/students', $this->input($finance))->assertCreated()->json('data.id');
    }

    private function monthly(string $student, bool $preview = false, array $extra = [])
    {
        return $this->postJson('/api/v1/finance/monthly-fees', ['student_id' => $student, 'through' => '2026-10', 'preview' => $preview, ...$extra]);
    }

    private function pay(array $invoices, int $amount)
    {
        $remaining = $amount;
        $allocations = [];
        foreach ($invoices as $i) {
            $part = min($remaining, $i['balance_minor']);
            if ($part > 0) {
                $allocations[] = ['fee_invoice_id' => $i['id'], 'amount_minor' => $part];
                $remaining -= $part;
            }
        }

        return $this->postJson('/api/v1/finance/payments', ['request_id' => (string) Str::uuid(), 'amount_minor' => $amount, 'payment_method_id' => DB::table('payment_methods')->where('code', 'cash')->value('id'), 'paid_on' => now()->toDateString(), 'allocations' => $allocations]);
    }

    public function test_march_to_october_preview_generation_payments_and_posted_vs_unbilled(): void
    {
        $student = $this->march();
        $this->getJson('/api/v1/students/'.$student.'/finance')->assertOk()->assertJsonPath('data.balance_minor', 0)->assertJsonPath('data.unbilled_minor', 800000)->assertJsonPath('data.missing_months', 8)->assertJsonPath('data.monthly_periods.0.period', '2026-03');
        $this->getJson('/api/v1/dashboard-summary')->assertOk()->assertJsonPath('data.finance.outstanding_minor', 0)->assertJsonPath('data.finance.unbilled_minor', 800000);
        $this->get('/api/v1/reports/unbilled_fees?format=csv')->assertOk()->assertSee('8,000.00')->assertDontSee($student);
        $this->monthly($student, true)->assertOk()->assertJsonPath('data.missing_months', 8)->assertJsonCount(0, 'data.generated_ids');
        $this->assertDatabaseCount('fee_invoices', 0);
        $this->monthly($student)->assertOk()->assertJsonCount(8, 'data.generated_ids');
        $this->monthly($student)->assertOk()->assertJsonCount(0, 'data.generated_ids');
        $this->assertDatabaseCount('fee_invoices', 8);
        $this->assertDatabaseMissing('fee_invoices', ['period' => '2026-02']);
        $snapshot = $this->getJson('/api/v1/students/'.$student.'/finance')->assertOk()->assertJsonPath('data.total_minor', 800000)->assertJsonPath('data.balance_minor', 800000)->json('data');
        $items = collect($snapshot['items'])->sortBy('period')->values()->all();
        $this->pay($items, 200000)->assertCreated();
        $this->getJson('/api/v1/students/'.$student.'/finance')->assertOk()->assertJsonPath('data.paid_minor', 200000)->assertJsonPath('data.balance_minor', 600000)->assertJsonPath('data.arrears_minor', 500000)->assertJsonPath('data.current_period_due_minor', 100000)->assertJsonPath('data.monthly_periods.0.status', 'paid')->assertJsonPath('data.monthly_periods.1.status', 'paid');
        $may = $this->getJson('/api/v1/students/'.$student.'/finance')->json('data.items');
        $this->pay(collect($may)->sortBy('period')->values()->all(), 50000)->assertCreated();
        $snapshot = $this->getJson('/api/v1/students/'.$student.'/finance')->assertOk()->assertJsonPath('data.monthly_periods.2.status', 'partially_paid')->assertJsonPath('data.balance_minor', 550000)->json('data');
        $this->pay(collect($snapshot['items'])->sortBy('period')->values()->all(), 550000)->assertCreated();
        $this->getJson('/api/v1/students/'.$student.'/finance')->assertJsonPath('data.balance_minor', 0)->assertJsonPath('data.monthly_periods.7.status', 'paid');
        $this->get('/api/v1/reports/fee_statement?student_id='.$student.'&format=csv')->assertOk()->assertSee('2026-03')->assertDontSee($student);
        $this->get('/api/v1/reports/fee_balances?format=csv')->assertOk()->assertSee(DB::table('students')->find($student)->registration_number)->assertDontSee($student);
        $this->getJson('/api/v1/finance/trial-balance')->assertOk()->assertJsonPath('data.balanced', true);
    }

    public function test_historical_rates_assignments_and_period_discounts_are_deterministic(): void
    {
        $student = $this->march();
        $class = DB::table('sections')->where('id', $this->section)->value('class_id');
        Domain::insert('fee_schedules', ['academic_session_id' => $this->session, 'class_id' => $class, 'kind' => 'tuition', 'amount_minor' => 120000, 'effective_on' => '2026-07-01']);
        $plain = app(MonthlyBilling::class)->plan($student, '2026-10');
        $this->assertSame(100000, $plain[0]['gross_minor']);
        $this->assertSame(120000, $plain[4]['gross_minor']);
        $this->postJson('/api/v1/finance/fee-assignments', ['student_id' => $student, 'academic_session_id' => $this->session, 'effective_on' => '2026-04-01', 'tuition_override_minor' => 80000])->assertCreated();
        $id = $this->postJson('/api/v1/finance/discounts', ['student_id' => $student, 'academic_session_id' => $this->session, 'discount_policy_id' => DB::table('discount_policies')->where('code', 'special')->value('id'), 'basis_points' => 2000, 'starts_on' => '2026-05-01', 'ends_on' => '2026-06-30', 'eligibility_notes' => 'Fictional dated concession'])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/finance/discounts/'.$id.'/approve', ['eligibility_verified' => true])->assertOk();
        $rows = app(MonthlyBilling::class)->plan($student, '2026-10');
        $this->assertSame([100000, 80000, 64000, 64000, 80000, 80000, 80000, 80000], array_column($rows, 'net_minor'));
        $this->postJson('/api/v1/finance/discounts/'.$id.'/revoke')->assertOk();
        $this->assertSame(64000, app(MonthlyBilling::class)->plan($student, '2026-10')[2]['net_minor']);
        $this->monthly($student)->assertOk();
        DB::table('fee_schedules')->where('academic_session_id', $this->session)->update(['amount_minor' => 999999]);
        $this->assertSame(628000, (int) DB::table('fee_invoices')->where('student_id', $student)->sum('total_minor'));
        $this->assertSame(64000, app(MonthlyBilling::class)->plan($student, '2026-10')[2]['net_minor']);
    }

    public function test_session_changes_and_withdrawal_bound_billable_months(): void
    {
        DB::table('academic_sessions')->where('id', $this->session)->update(['ends_on' => '2026-06-30']);
        $student = $this->march();
        $session = Domain::insert('academic_sessions', ['name' => 'Fictional second term', 'starts_on' => '2026-07-01', 'ends_on' => '2026-12-31']);
        $class = DB::table('classes')->where('name', 'Grade 2')->value('id');
        $section = Domain::insert('sections', ['class_id' => $class, 'academic_session_id' => $session, 'name' => 'Next']);
        Domain::insert('fee_schedules', ['academic_session_id' => $session, 'class_id' => $class, 'kind' => 'tuition', 'amount_minor' => 200000, 'effective_on' => '2026-07-01']);
        $this->postJson('/api/v1/students/'.$student.'/transitions', ['type' => 'promotion', 'effective_on' => '2026-07-01', 'section_id' => $section, 'roll_number' => 1])->assertOk();
        $this->assertSame(1200000, array_sum(array_column(app(MonthlyBilling::class)->plan($student, '2026-10'), 'net_minor')));
        $this->postJson('/api/v1/students/'.$student.'/transitions', ['type' => 'withdrawal', 'effective_on' => '2026-09-01'])->assertOk();
        $this->monthly($student)->assertOk()->assertJsonCount(6, 'data.generated_ids');
        $this->assertDatabaseMissing('fee_invoices', ['student_id' => $student, 'period' => '2026-09']);
        $this->assertSame(800000, (int) DB::table('fee_invoices')->where('student_id', $student)->sum('total_minor'));
    }

    public function test_admission_partial_cash_receipt_and_idempotent_retry_are_atomic(): void
    {
        $v = $this->input(['admission_fee' => true, 'bill_tuition' => true, 'charges' => [['description' => 'Additional registration fee', 'amount_minor' => 10000]], 'payment' => ['amount_minor' => 60000, 'payment_method_id' => DB::table('payment_methods')->where('code', 'cash')->value('id'), 'paid_on' => now()->toDateString(), 'notes' => 'Fictional cash at admission']]);
        $id = $this->postJson('/api/v1/students', $v)->assertCreated()->json('data.id');
        $this->postJson('/api/v1/students', $v)->assertCreated()->assertJsonPath('data.id', $id);
        $this->assertDatabaseCount('students', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('fee_invoices', 3);
        $payment = DB::table('payments')->first();
        $this->assertSame('verified', $payment->status);
        $this->assertNotNull($payment->receipt_number);
        $this->assertNull($payment->reference);
        $this->getJson('/api/v1/students/'.$id.'/finance')->assertOk()->assertJsonPath('data.total_minor', 160000)->assertJsonPath('data.paid_minor', 60000)->assertJsonPath('data.balance_minor', 100000);
        $this->assertMatchesRegularExpression('/^INF-SG-26-[0-9]{4}$/', DB::table('students')->find($id)->registration_number);
        $this->postJson('/api/v1/students', [...$v, 'full_name' => 'Changed retry'])->assertConflict();
        $this->getJson('/api/v1/finance/trial-balance')->assertOk()->assertJsonPath('data.balanced', true);
    }

    public function test_admission_failure_rolls_back_student_vouchers_and_payment(): void
    {
        $this->postJson('/api/v1/students', $this->input(['bill_tuition' => true, 'payment' => ['amount_minor' => 100001, 'payment_method_id' => DB::table('payment_methods')->where('code', 'cash')->value('id'), 'paid_on' => now()->toDateString()]]))->assertUnprocessable();
        foreach (['students', 'fee_invoices', 'payments', 'journal_entries', 'admission_requests'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_preview_current_only_missing_configuration_and_scope_permissions(): void
    {
        $student = $this->march();
        $this->monthly($student, true, ['current_only' => true])->assertOk()->assertJsonPath('data.missing_months', 1);
        $this->monthly($student, true, ['through' => '2026-11'])->assertUnprocessable();
        $this->asUser($this->user('school_administrator'));
        $this->monthly($student)->assertForbidden();
        $this->postJson('/api/v1/students', [...$this->input(['bill_tuition' => true]), 'roll_number' => 2])->assertForbidden();
        $configured = [...$this->input(['tuition_override_minor' => 90000]), 'roll_number' => 2];
        $this->postJson('/api/v1/students', $configured)->assertCreated();
        $this->asUser($this->teacher())->getJson('/api/v1/students/'.$student.'/finance')->assertForbidden();
        [, $parent] = $this->guardian($student);
        $this->asUser($parent)->getJson('/api/v1/students/'.$student.'/finance')->assertOk();
        $this->monthly($student)->assertForbidden();
        $other = $this->student(roll: 3);
        $this->asUser($parent)->getJson('/api/v1/students/'.$other.'/finance')->assertNotFound();
        $this->asUser($this->user('accountant_clerk'));
        $this->monthly($student, true)->assertOk();
        DB::table('fee_schedules')->where('academic_session_id', $this->session)->update(['effective_on' => '2026-07-01']);
        $this->monthly($student)->assertUnprocessable();
        $this->assertDatabaseCount('fee_invoices', 0);
    }

    public function test_command_requires_actor_and_previews_without_posting(): void
    {
        $student = $this->march();
        $this->artisan('school:generate-monthly-fees', ['--student' => $student])->assertFailed();
        $this->artisan('school:generate-monthly-fees', ['--actor' => $this->admin->id, '--student' => $student, '--through' => '2026-10', '--dry-run' => true])->assertSuccessful();
        $this->assertDatabaseCount('fee_invoices', 0);
        $this->artisan('school:generate-monthly-fees', ['--actor' => $this->admin->id, '--student' => $student, '--through' => '2026-10'])->assertSuccessful();
        $this->artisan('school:generate-monthly-fees', ['--actor' => $this->admin->id, '--student' => $student, '--through' => '2026-10'])->assertSuccessful();
        $this->assertDatabaseCount('fee_invoices', 8);
    }

    public function test_bulk_class_preview_respects_assignments_without_duplicate_generation(): void
    {
        $one = $this->march();
        $two = $this->postJson('/api/v1/students', [...$this->input(), 'roll_number' => 2])->assertCreated()->json('data.id');
        $class = DB::table('sections')->where('id', $this->section)->value('class_id');
        $this->postJson('/api/v1/finance/monthly-fees', ['class_id' => $class, 'academic_session_id' => $this->session, 'through' => '2026-10', 'preview' => true])->assertOk()->assertJsonPath('data.student_count', 2)->assertJsonPath('data.missing_months', 16);
        Domain::insert('student_fee_assignments', ['student_id' => $two, 'academic_session_id' => $this->session, 'effective_on' => '2026-03-01', 'tuition_override_minor' => 50000, 'approved_by' => $this->admin->id]);
        $this->postJson('/api/v1/finance/monthly-fees', ['class_id' => $class, 'academic_session_id' => $this->session, 'through' => '2026-10', 'preview' => false])->assertOk()->assertJsonCount(16, 'data.generated_ids');
        $this->assertSame(800000, (int) DB::table('fee_invoices')->where('student_id', $one)->sum('total_minor'));
        $this->assertSame(400000, (int) DB::table('fee_invoices')->where('student_id', $two)->sum('total_minor'));
        $this->get('/api/v1/reports/unbilled_fees?format=csv')->assertOk();
        $this->getJson('/api/v1/finance/summary')->assertOk()->assertJsonPath('data.unbilled_minor', 0)->assertJsonPath('data.balance_minor', 1200000);
    }

    public function test_opening_balance_is_explicit_and_receipts_cannot_mix_students(): void
    {
        $one = $this->march(['opening_balance_minor' => 10000]);
        $two = $this->postJson('/api/v1/students', [...$this->input(['opening_balance_minor' => 10000]), 'roll_number' => 2])->assertCreated()->json('data.id');
        $allocations = DB::table('fee_invoices')->whereIn('student_id', [$one, $two])->get()->map(fn ($i) => ['fee_invoice_id' => $i->id, 'amount_minor' => 5000])->all();
        $this->postJson('/api/v1/finance/payments', ['request_id' => (string) Str::uuid(), 'amount_minor' => 10000, 'payment_method_id' => DB::table('payment_methods')->where('code', 'cash')->value('id'), 'paid_on' => now()->toDateString(), 'allocations' => $allocations])->assertUnprocessable();
        $this->assertDatabaseCount('payments', 0);
        $this->monthly($one, true)->assertOk()->assertJsonPath('data.missing_months', 8);
        $this->assertDatabaseCount('fee_invoices', 2);
    }

    public function test_batch_is_atomic_when_a_later_student_has_missing_historical_rates(): void
    {
        $one = $this->march();
        $two = $this->postJson('/api/v1/students', [...$this->input(), 'roll_number' => 2])->assertCreated()->json('data.id');
        $ids = [$one, $two];
        sort($ids);
        DB::table('students')->where('id', $ids[0])->update(['admitted_on' => '2026-04-01']);
        DB::table('enrollments')->where('student_id', $ids[0])->update(['starts_on' => '2026-04-01']);
        DB::table('fee_schedules')->where('academic_session_id', $this->session)->update(['effective_on' => '2026-04-01']);
        try {
            app(MonthlyBilling::class)->generate($this->admin, $ids, '2026-10');
            $this->fail('Missing historical rate must reject the complete batch.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('fee_schedule', $e->errors());
        }
        $this->assertDatabaseCount('fee_invoices', 0);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_reviewed_opening_coverage_prevents_duplicate_monthly_debt(): void
    {
        $student = $this->march(['opening_balance_minor' => 200000]);
        $opening = DB::table('fee_invoices')->where('student_id', $student)->value('id');
        $this->monthly($student)->assertUnprocessable();
        $this->assertDatabaseCount('fee_invoices', 1);
        $url = '/api/v1/finance/invoices/'.$opening.'/opening-coverage';
        $range = ['covered_from' => '2026-03', 'covered_through' => '2026-04'];
        $this->asUser($this->user('accountant_clerk'))->postJson($url, $range)->assertForbidden();
        $this->asUser($this->admin)->postJson($url, $range)->assertOk();
        $this->postJson($url, $range)->assertOk();
        $this->postJson($url, [...$range, 'covered_through' => '2026-05'])->assertConflict();
        $this->assertDatabaseCount('opening_balance_coverage', 1);
        $this->monthly($student)->assertOk()->assertJsonCount(6, 'data.generated_ids');
        $this->getJson('/api/v1/students/'.$student.'/finance')->assertOk()->assertJsonPath('data.total_minor', 800000)->assertJsonPath('data.monthly_periods.0.status', 'opening_balance_covered')->assertJsonPath('data.monthly_periods.0.net_minor', null)->assertJsonPath('data.unbilled_minor', 0);
        $this->assertDatabaseMissing('fee_invoices', ['student_id' => $student, 'period' => '2026-03', 'kind' => 'tuition']);
        $this->getJson('/api/v1/finance/trial-balance')->assertOk()->assertJsonPath('data.balanced', true);
    }

    public function test_class_generation_only_posts_months_applicable_to_the_selected_class(): void
    {
        $student = $this->march();
        $first = DB::table('sections')->where('id', $this->section)->value('class_id');
        $second = DB::table('classes')->where('name', 'Grade 2')->value('id');
        $section = Domain::insert('sections', ['class_id' => $second, 'academic_session_id' => $this->session, 'name' => 'New class']);
        Domain::insert('fee_schedules', ['class_id' => $second, 'academic_session_id' => $this->session, 'kind' => 'tuition', 'amount_minor' => 200000, 'effective_on' => '2026-05-01']);
        $this->postJson('/api/v1/students/'.$student.'/transitions', ['type' => 'section_change', 'effective_on' => '2026-05-01', 'section_id' => $section, 'roll_number' => 1])->assertOk();
        $base = ['academic_session_id' => $this->session, 'through' => '2026-10', 'preview' => false];
        $this->postJson('/api/v1/finance/monthly-fees', [...$base, 'class_id' => $first])->assertOk()->assertJsonCount(2, 'data.generated_ids');
        $this->postJson('/api/v1/finance/monthly-fees', [...$base, 'class_id' => $second])->assertOk()->assertJsonCount(6, 'data.generated_ids');
        $this->assertDatabaseCount('fee_invoices', 8);
        $this->assertSame(1400000, (int) DB::table('fee_invoices')->sum('total_minor'));
    }
}
