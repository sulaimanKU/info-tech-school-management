<?php

namespace Tests\Feature;

use App\Services\Finance\InvoiceBalance;
use App\Services\ParentChildren;
use App\Support\Domain;
use Database\Seeders\FinanceConfigurationSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\MobileTestCase;

class ParentExperienceTest extends MobileTestCase
{
    private function family(): array
    {
        $a = $this->student();
        $b = $this->student($this->otherSection);
        $c = $this->student(roll: 2);
        [$guardian, $parent] = $this->guardian($a);
        $this->asUser($this->admin)->putJson('/api/v1/guardians/'.$guardian.'/links', ['student_id' => $b, 'relationship' => 'guardian', 'is_primary' => false])->assertOk();
        [, $other] = $this->guardian($c, '0000000000002');

        return [$a, $b, $c, $parent, $other, $guardian];
    }

    private function publishedResult(string $student, string $subject, string $date, string $title, int $marks, int $maximum = 100, string $status = 'published'): string
    {
        $section = DB::table('enrollments')->where('student_id', $student)->value('section_id');
        $id = Domain::insert('assessments', ['academic_session_id' => $this->session, 'section_id' => $section, 'subject_id' => $subject, 'teacher_user_id' => $this->admin->id, 'type' => 'monthly_test', 'title' => $title, 'date' => $date, 'maximum_marks' => $maximum, 'status' => $status, 'published_at' => $status === 'published' ? now() : null]);
        Domain::insert('assessment_results', ['assessment_id' => $id, 'student_id' => $student, 'obtained_marks' => $marks, 'maximum_marks' => $maximum, 'percentage' => 100 * $marks / $maximum, 'grade' => 'A', 'remarks' => 'Fictional published encouragement']);

        return $id;
    }

    private function invoice(string $student, int $amount = 1000): string
    {
        return Domain::insert('fee_invoices', ['student_id' => $student, 'academic_session_id' => $this->session, 'voucher_number' => 'FICTIONAL-'.Str::uuid(), 'period' => now()->subMonths(DB::table('fee_invoices')->where('student_id', $student)->count())->format('Y-m'), 'kind' => 'additional', 'issued_on' => now()->toDateString(), 'due_on' => now()->addDays(5)->toDateString(), 'gross_minor' => $amount, 'discount_minor' => 0, 'total_minor' => $amount, 'request_id' => (string) Str::uuid(), 'payload_hash' => str_repeat('a', 64), 'created_by' => $this->admin->id]);
    }

    public function test_parent_overviews_switch_children_without_cross_family_data(): void
    {
        [$a, $b, $c, $parent, $other] = $this->family();
        $this->publishedResult($a, $this->subject, '2026-10-01', 'Fictional Alpha result', 80);
        $this->publishedResult($b, $this->subject, '2026-10-02', 'Fictional Beta result', 60);
        $this->invoice($a, 1234);
        $this->invoice($b, 5678);
        $this->asUser($parent)->getJson('/api/v1/parent/home')->assertOk()->assertJsonCount(2, 'data.children')->assertHeader('Cache-Control', 'no-store, private');
        foreach ([[$a, 1234, 'Fictional Alpha result'], [$b, 5678, 'Fictional Beta result']] as [$student,$balance,$title]) {
            $this->getJson('/api/v1/parent/children/'.$student.'/overview')->assertOk()->assertJsonPath('data.child.id', $student)->assertJsonPath('data.child.balance_minor', $balance)->assertJsonPath('data.child.latest_academic.title', $title)->assertJsonCount(0, 'data.recent_homework')->assertDontSee($c);
        }
        $this->getJson('/api/v1/parent/children/'.$c.'/overview')->assertNotFound();
        $this->asUser($other)->getJson('/api/v1/parent/children/'.$a.'/overview')->assertNotFound();
        $this->asUser($this->admin)->getJson('/api/v1/parent/children/'.$a.'/overview')->assertForbidden();
    }

    public function test_published_assessment_selector_weighted_totals_history_and_report_scope(): void
    {
        [$a,$b,$c,$parent] = $this->family();
        $math = Domain::insert('subjects', ['name' => 'Fictional Mathematics', 'code' => 'parent-math']);
        Domain::insert('grading_rules', ['academic_session_id' => $this->session, 'minimum_percent' => 0, 'grade' => 'F']);
        Domain::insert('grading_rules', ['academic_session_id' => $this->session, 'minimum_percent' => 70, 'grade' => 'B']);
        foreach ([['2026-10-01', 'Fictional September', 30, 40], ['2026-10-10', 'Fictional October', 40, 80]] as [$date,$title,$en,$ma]) {
            $this->publishedResult($a, $this->subject, $date, $title, $en, 50);
            $this->publishedResult($a, $math, $date, $title, $ma, 100);
        }
        $this->publishedResult($a, $this->subject, '2026-10-20', 'Fictional secret draft', 50, 50, 'draft');
        $this->publishedResult($b, $this->subject, '2026-10-10', 'Fictional Beta only', 50);
        $this->asUser($parent);

        $res = $this->getJson('/api/v1/students/'.$a.'/assessments?exam_key=latest')->assertOk()->assertJsonCount(2, 'data.periods')->assertJsonCount(2, 'data.items')->assertJsonCount(2, 'data.trend')->assertJsonPath('data.overall_percentage', 80)->assertJsonPath('data.selected_period.grade', 'B')->assertDontSee('Fictional secret draft')->assertDontSee('Fictional Beta only')->assertSee('Fictional published encouragement')->assertJsonMissingPath('data.items.0.class_position');
        $key = $res->json('data.periods.1.key');
        $this->getJson('/api/v1/students/'.$a.'/assessments?exam_key='.$key)->assertOk()->assertJsonPath('data.selected_period.title', 'Fictional September')->assertJsonPath('data.overall_percentage', 46.67)->assertJsonCount(0, 'data.trend');
        $this->getJson('/api/v1/students/'.$b.'/assessments?exam_key='.$key)->assertNotFound();
        $this->getJson('/api/v1/students/'.$a.'/assessments?exam_key=bad')->assertUnprocessable();
        $this->getJson('/api/v1/students/'.$a.'/assessments?exam_key='.str_repeat('a', 64))->assertNotFound();
        $this->get('/api/v1/reports/academic?format=csv&student_id='.$a.'&exam_key='.$key, ['Accept' => 'application/json'])->assertOk()->assertSee('Fictional September')->assertDontSee('Fictional October')->assertDontSee('Fictional secret draft');
        $this->get('/api/v1/reports/academic?student_id='.$a.'&exam_key='.$key, ['Accept' => 'application/json'])->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->getJson('/api/v1/students/'.$c.'/assessments?exam_key=latest')->assertNotFound();
        $this->get('/api/v1/reports/academic?student_id='.$c, ['Accept' => 'application/json'])->assertNotFound();
    }

    public function test_all_child_endpoints_deny_cross_family_id_and_query_substitution(): void
    {
        [$a,$b,$c,$parent] = $this->family();
        $invoice = $this->invoice($c);
        $this->asUser($parent);
        foreach (['students/'.$c, 'students/'.$c.'/attendance?month=2026-10', 'students/'.$c.'/assessments', 'students/'.$c.'/behavior', 'students/'.$c.'/finance', 'diary?student_id='.$c, 'announcements?student_id='.$c, 'tickets?student_id='.$c, 'finance/invoices/'.$invoice] as $path) {
            $this->getJson('/api/v1/'.$path)->assertNotFound();
        }
        $this->postJson('/api/v1/tickets', ['student_id' => $c, 'category' => 'general', 'subject' => 'Fictional forbidden query', 'message' => 'Fictional access attempt', 'attachments' => [], 'client_id' => (string) Str::uuid()])->assertNotFound();
        $this->get('/api/v1/reports/voucher?invoice_id='.$invoice, ['Accept' => 'application/json'])->assertNotFound();
    }

    public function test_diary_overview_and_direct_detail_preserve_selected_child_and_publication(): void
    {
        [$a,$b,$c,$parent] = $this->family();
        foreach ([[$a, $this->section, 'Fictional alpha work', 'published'], [$b, $this->otherSection, 'Fictional beta work', 'published'], [$a, $this->section, 'Fictional hidden work', 'draft']] as [$student,$section,$topic,$status]) {
            $id = Domain::insert('diary_entries', ['section_id' => $section, 'subject_id' => $this->subject, 'teacher_user_id' => $this->admin->id, 'date' => '2026-10-01', 'topic' => $topic, 'homework' => 'Fictional homework', 'whole_class' => false, 'status' => $status, 'version' => 1, 'client_id' => (string) Str::uuid(), 'payload_hash' => str_repeat('a', 64)]);
            DB::table('diary_students')->insert(['diary_entry_id' => $id, 'student_id' => $student]);
            if ($student === $a && $status === 'published') {
                $alpha = $id;
            }
        }
        $this->asUser($parent)->getJson('/api/v1/parent/children/'.$a.'/overview')->assertOk()->assertJsonPath('data.homework_count', 1)->assertJsonPath('data.recent_homework.0.topic', 'Fictional alpha work')->assertJsonCount(1, 'data.subjects')->assertDontSee('Fictional beta work')->assertDontSee('Fictional hidden work');
        $this->getJson('/api/v1/diary/'.$alpha.'?student_id='.$a)->assertOk();
        $this->getJson('/api/v1/diary/'.$alpha.'?student_id='.$b)->assertNotFound();
        $this->getJson('/api/v1/diary/'.$alpha.'?student_id='.$c)->assertNotFound();
    }

    public function test_parent_home_query_count_does_not_grow_with_children_or_invoices(): void
    {
        [$a,$b,$c,$parent,$other,$guardian] = $this->family();
        $this->invoice($a);
        $parent->load('roles');
        $measure = function () use ($parent) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $rows = app(ParentChildren::class)->get($parent);
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return [$count, $rows];
        };
        [$before] = $measure();
        for ($i = 3; $i <= 7; $i++) {
            $student = $this->student(roll: $i);
            $this->asUser($this->admin)->putJson('/api/v1/guardians/'.$guardian.'/links', ['student_id' => $student, 'relationship' => 'guardian', 'is_primary' => false])->assertOk();
            for ($j = 0; $j < 3; $j++) {
                $this->invoice($student);
            }
        }
        [$after,$rows] = $measure();
        $this->assertCount(7, $rows);
        $this->assertSame($before, $after, 'Parent summary must batch child and invoice reads.');
        foreach (DB::table('fee_invoices')->get() as $invoice) {
            $this->assertSame(app(InvoiceBalance::class)->calculate($invoice), app(InvoiceBalance::class)->many(collect([$invoice]))[$invoice->id]);
        }
    }

    public function test_two_verified_guardians_can_share_a_child_without_sharing_other_children(): void
    {
        [$a,$b,$c,$parent,$other] = $this->family();
        [$second,$secondParent] = $this->guardian($a, '0000000000003');
        $this->asUser($secondParent)->getJson('/api/v1/parent/home')->assertOk()->assertJsonCount(1, 'data.children')->assertJsonPath('data.children.0.id', $a);
        $this->getJson('/api/v1/parent/children/'.$a.'/overview')->assertOk();
        $this->getJson('/api/v1/parent/children/'.$b.'/overview')->assertNotFound();
        $this->getJson('/api/v1/parent/children/'.$c.'/overview')->assertNotFound();
        $this->asUser($other)->getJson('/api/v1/parent/children/'.$c.'/overview')->assertOk();
    }

    public function test_batched_parent_balances_match_partial_payment_refund_adjustment_and_document_access(): void
    {
        $this->seed(FinanceConfigurationSeeder::class);
        [$a,$b,$c,$parent,$other] = $this->family();
        $invoice = $this->invoice($a, 10000);
        $this->asUser($this->admin);
        $payment = $this->postJson('/api/v1/finance/payments', ['payment_method_id' => DB::table('payment_methods')->where('code', 'cash')->value('id'), 'paid_on' => now()->toDateString(), 'amount_minor' => 4000, 'request_id' => (string) Str::uuid(), 'allocations' => [['fee_invoice_id' => $invoice, 'amount_minor' => 4000]]])->assertCreated()->json('data.id');
        $allocation = DB::table('payment_allocations')->where('payment_id', $payment)->value('id');
        $this->postJson('/api/v1/finance/payments/'.$payment.'/refund', ['refunded_on' => now()->toDateString(), 'reason' => 'Fictional parent regression refund', 'request_id' => (string) Str::uuid(), 'allocations' => [['payment_allocation_id' => $allocation, 'amount_minor' => 1000]]])->assertCreated();
        $this->postJson('/api/v1/finance/invoices/'.$invoice.'/adjust', ['amount_minor' => -500, 'reason' => 'Fictional approved adjustment', 'request_id' => (string) Str::uuid()])->assertCreated();
        $this->asUser($parent)->getJson('/api/v1/parent/children/'.$a.'/overview')->assertOk()->assertJsonPath('data.child.balance_minor', 6500);
        $this->getJson('/api/v1/finance/invoices/'.$invoice)->assertOk()->assertJsonPath('data.balance_minor', 6500)->assertJsonPath('data.status', 'partially_paid');
        $this->get('/api/v1/reports/voucher?invoice_id='.$invoice, ['Accept' => 'application/json'])->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get('/api/v1/reports/receipt?payment_id='.$payment.'&student_id='.$a, ['Accept' => 'application/json'])->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get('/api/v1/reports/receipt?payment_id='.$payment.'&student_id='.$b, ['Accept' => 'application/json'])->assertNotFound();
        $this->asUser($other)->get('/api/v1/reports/voucher?invoice_id='.$invoice, ['Accept' => 'application/json'])->assertNotFound();
        $this->get('/api/v1/reports/receipt?payment_id='.$payment.'&student_id='.$a, ['Accept' => 'application/json'])->assertNotFound();
        $row = DB::table('fee_invoices')->find($invoice);
        $this->assertSame(app(InvoiceBalance::class)->calculate($row),app(InvoiceBalance::class)->many(collect([$row]))[$invoice]);
    }
}
