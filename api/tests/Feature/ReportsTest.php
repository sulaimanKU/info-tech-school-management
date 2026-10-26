<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Support\MobileTestCase;

class ReportsTest extends MobileTestCase
{
    public function test_private_branded_pdf_and_cross_parent_report_idor(): void
    {
        $student = $this->student();
        $other = $this->student($this->otherSection);
        [, $parent] = $this->guardian($student);
        [, $otherParent] = $this->guardian($other, '0000000000002');
        $res = $this->asUser($parent)->get('/api/v1/reports/student_id?student_id='.$student, ['Accept' => 'application/pdf'])->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('Cache-Control', 'no-store, private');
        $this->assertStringStartsWith('%PDF-', $res->getContent());
        $this->get('/api/v1/reports/student_id?student_id='.$other, ['Accept' => 'application/json'])->assertNotFound();
        $this->get('/api/v1/reports/academic?student_id='.$other, ['Accept' => 'application/json'])->assertNotFound();
        $this->get('/api/v1/reports/annual_fee?student_id='.$other, ['Accept' => 'application/json'])->assertNotFound();
        $this->get('/api/v1/reports/financial', ['Accept' => 'application/json'])->assertForbidden();
    }

    public function test_csv_and_xlsx_exports_do_not_execute_spreadsheet_formulas(): void
    {
        $student = $this->student();
        DB::table('students')->where('id', $student)->update(['full_name' => '=HYPERLINK("https://example.invalid")']);
        $this->asUser($this->admin);
        $res = $this->get('/api/v1/reports/registration?format=csv')->assertOk();
        $this->assertStringContainsString("'=HYPERLINK", $res->getContent());
        $res = $this->get('/api/v1/reports/registration?format=xlsx')->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'fictional-export');
        file_put_contents($path, $res->getContent());
        try {
            $book = IOFactory::load($path);
            $found = false;
            foreach ($book->getActiveSheet()->getRowIterator() as $row) {
                foreach ($row->getCellIterator() as $cell) {
                    if (str_starts_with((string) $cell->getValue(), '=HYPERLINK')) {
                        $found = true;
                        $this->assertSame('s', $cell->getDataType());
                    }
                }
            }$this->assertTrue($found);
            $book->disconnectWorksheets();
        } finally {
            unlink($path);
        }
    }

    public function test_exam_lifecycle_conflicts_and_parent_visibility(): void
    {
        $student = $this->student();
        [, $parent] = $this->guardian($student);
        $coordinator = $this->coordinator();
        $v = ['section_id' => $this->section, 'subject_id' => $this->subject, 'title' => 'Fictional unit examination', 'date' => '2026-11-02', 'starts_at' => '09:00', 'ends_at' => '10:00', 'status' => 'draft', 'version' => 0];
        $this->asUser($coordinator);
        $id = $this->postJson('/api/v1/exam-schedules', $v)->assertOk()->json('data.id');
        $this->asUser($parent)->getJson('/api/v1/exam-schedules')->assertOk()->assertJsonCount(0, 'data.items');
        $this->asUser($coordinator)->putJson('/api/v1/exam-schedules/'.$id, [...$v, 'version' => 1, 'status' => 'published'])->assertOk();
        $this->postJson('/api/v1/exam-schedules', [...$v, 'starts_at' => '09:30', 'ends_at' => '10:30'])->assertUnprocessable();
        $this->asUser($parent)->getJson('/api/v1/exam-schedules')->assertOk()->assertJsonCount(1, 'data.items');
        $this->postJson('/api/v1/exam-schedules', $v)->assertForbidden();
        $this->get('/api/v1/reports/exam_schedule?format=csv')->assertOk()->assertSee('Fictional unit examination');
    }
}
