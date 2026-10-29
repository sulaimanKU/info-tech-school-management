<?php

namespace Tests\Feature;

use App\Services\Finance\FeeService;
use App\Services\Finance\PaymentService;
use App\Support\Domain;
use Database\Seeders\FinanceConfigurationSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use setasign\Fpdi\PdfParser\PdfParser;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\PdfReader\PdfReader;
use Tests\Support\MobileTestCase;

class FinalDocumentValidationTest extends MobileTestCase
{
    public function test_student_id_is_one_standard_landscape_card(): void
    {
        $student = $this->student();
        $this->asUser($this->admin);
        $response = $this->get('/api/v1/reports/student_id?student_id='.$student)->assertOk();
        $pdf = new PdfReader(new PdfParser(StreamReader::createByString($response->getContent())));
        $this->assertSame(1, $pdf->getPageCount());
        $size = $pdf->getPage(1)->getBoundary();
        $this->assertEqualsWithDelta(86, $size->getWidth() * 25.4 / 72, 0.1);
        $this->assertEqualsWithDelta(54, $size->getHeight() * 25.4 / 72, 0.1);
    }

    public function test_parent_voucher_receipt_and_annual_documents_are_scoped_to_one_child(): void
    {
        $this->seed(FinanceConfigurationSeeder::class);
        Domain::insert('fee_schedules', ['academic_session_id' => $this->session, 'class_id' => DB::table('sections')->where('id', $this->section)->value('class_id'), 'kind' => 'tuition', 'amount_minor' => 140000, 'effective_on' => '2026-01-01']);
        $one = $this->student();
        $two = $this->student(roll: 2);
        [, $parent] = $this->guardian($one);
        $invoices = [];
        foreach ([$one, $two] as $student) {
            $invoices[] = app(FeeService::class)->generate($this->admin, ['student_id' => $student, 'academic_session_id' => $this->session, 'period' => '2026-10', 'kind' => 'tuition', 'issued_on' => now()->toDateString(), 'due_on' => now()->addDays(5)->toDateString(), 'request_id' => (string) Str::uuid()]);
        }
        // Legacy shared receipts must remain readable and scoped; new mixed-student receipts are rejected.
        $payment = Domain::insert('payments', ['payment_method_id' => DB::table('payment_methods')->where('code', 'cash')->value('id'), 'paid_on' => now()->toDateString(), 'amount_minor' => 280000, 'status' => 'pending', 'request_id' => (string) Str::uuid(), 'payload_hash' => hash('sha256', 'fictional-legacy-shared-receipt'), 'recorded_by' => $this->admin->id]);
        foreach ($invoices as $invoice) {
            Domain::insert('payment_allocations', ['payment_id' => $payment, 'fee_invoice_id' => $invoice, 'amount_minor' => 140000]);
        }
        app(PaymentService::class)->verify($this->admin, $payment);
        $this->asUser($parent);
        $this->get('/api/v1/reports/voucher?invoice_id='.$invoices[0])->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get('/api/v1/reports/voucher?invoice_id='.$invoices[1])->assertNotFound();
        $first = DB::table('students')->find($one)->registration_number;
        $second = DB::table('students')->find($two)->registration_number;
        $this->get('/api/v1/reports/receipt?format=csv&payment_id='.$payment.'&student_id='.$one)->assertOk()->assertSee($first)->assertDontSee($second);
        $this->get('/api/v1/reports/receipt?payment_id='.$payment.'&student_id='.$one)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get('/api/v1/reports/receipt?payment_id='.$payment.'&student_id='.$two)->assertNotFound();
        $this->get('/api/v1/reports/annual_fee?student_id='.$one.'&year=2026')->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_paid_salary_document_is_available_only_to_its_staff_owner_or_finance(): void
    {
        $this->seed(FinanceConfigurationSeeder::class);
        $teacher = $this->teacher();
        $profile = DB::table('staff_profiles')->where('user_id', $teacher->id)->first();
        Domain::insert('staff_compensation', ['staff_profile_id' => $profile->id, 'base_salary_minor' => 3000000, 'effective_on' => '2026-01-01', 'approved_by' => $this->admin->id]);
        $this->asUser($this->admin);
        $id = $this->postJson('/api/v1/finance/payroll', ['period' => '2026-09'])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/finance/payroll/'.$id.'/approve')->assertOk();
        $this->postJson('/api/v1/finance/payroll/'.$id.'/pay', ['payment_method_id' => DB::table('payment_methods')->where('code', 'cash')->value('id'), 'paid_on' => now()->toDateString()])->assertOk();
        $item = DB::table('payroll_items')->where('payroll_period_id', $id)->value('id');
        $this->asUser($teacher)->get('/api/v1/reports/salary_slip?payroll_item_id='.$item)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->asUser($this->user('teacher'))->get('/api/v1/reports/salary_slip?payroll_item_id='.$item)->assertForbidden();
    }
}
