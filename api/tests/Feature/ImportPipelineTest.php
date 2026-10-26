<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Support\MobileTestCase;

class ImportPipelineTest extends MobileTestCase
{
    private function upload(string $kind, string $csv): string
    {
        return $this->post('/api/v1/imports', ['kind' => $kind, 'file' => UploadedFile::fake()->createWithContent('fictional.csv', $csv)], ['Accept' => 'application/json'])->assertCreated()->json('data.id');
    }

    public function test_preview_rolls_back_and_confirmation_is_atomic_and_idempotent(): void
    {
        $this->asUser($this->admin);
        $id = $this->upload('classes', "label,order\nFictional imported class,90\n");
        $this->postJson('/api/v1/imports/'.$id.'/confirm', ['confirm' => true])->assertConflict();
        $this->postJson('/api/v1/imports/'.$id.'/preview', ['mapping' => ['name' => 'label', 'sort_order' => 'order']])->assertOk()->assertJsonPath('data.valid', true);
        $this->assertDatabaseMissing('classes', ['name' => 'Fictional imported class']);
        $this->postJson('/api/v1/imports/'.$id.'/confirm', ['confirm' => true])->assertOk();
        $this->postJson('/api/v1/imports/'.$id.'/confirm', ['confirm' => true])->assertOk();
        $this->assertSame(1, DB::table('classes')->where('name', 'Fictional imported class')->count());
        $this->assertNull(DB::table('import_batches')->find($id)->encrypted_rows);
        $this->postJson('/api/v1/imports/'.$id.'/preview', ['mapping' => ['name' => 'label']])->assertConflict();
        $this->asUser($this->user('school_administrator'))->getJson('/api/v1/imports/'.$id)->assertNotFound();
    }

    public function test_invalid_duplicate_and_unmatched_rows_never_persist(): void
    {
        $this->asUser($this->admin);
        $id = $this->upload('classes', "name,sort_order\nFictional valid,90\nFictional invalid,no-number\n");
        $this->postJson('/api/v1/imports/'.$id.'/preview', ['mapping' => ['name' => 'name', 'sort_order' => 'sort_order']])->assertOk()->assertJsonPath('data.valid', false);
        $this->assertDatabaseMissing('classes', ['name' => 'Fictional valid']);
        $this->postJson('/api/v1/imports/'.$id.'/confirm', ['confirm' => true])->assertConflict();
        $id = $this->upload('biometric', "device,id,time,direction\nfictional-device,unmatched,2026-10-01 08:00:00,in\n");
        $this->postJson('/api/v1/imports/'.$id.'/preview', ['mapping' => ['device_code' => 'device', 'external_id' => 'id', 'punched_at' => 'time', 'direction' => 'direction']])->assertOk()->assertJsonPath('data.valid', false);
        $this->assertDatabaseCount('biometric_punches', 0);
        $this->asUser($this->teacher())->getJson('/api/v1/imports')->assertForbidden();
    }

    public function test_xlsx_formula_cells_are_rejected(): void
    {
        $this->asUser($this->admin);
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setCellValue('A1', 'name');
        $sheet->setCellValue('A2', '=1+1');
        $path = tempnam(sys_get_temp_dir(), 'fictional');
        (new Xlsx($book))->save($path);
        try {
            $this->post('/api/v1/imports', ['kind' => 'classes', 'file' => new UploadedFile($path, 'fictional.xlsx', null, null, true)], ['Accept' => 'application/json'])->assertUnprocessable();
        } finally {
            unlink($path);
            $book->disconnectWorksheets();
        }
    }

    public function test_biometric_evidence_does_not_fabricate_attendance(): void
    {
        $student = $this->student();
        $this->asUser($this->admin)->postJson('/api/v1/biometric/mappings', ['device_code' => 'fictional', 'external_id' => '001', 'student_id' => $student])->assertCreated();
        $id = $this->upload('biometric', "device,id,time,direction\nfictional,001,2026-10-01 08:00:00,in\nfictional,001,2026-10-01 13:00:00,out\n");
        $this->postJson('/api/v1/imports/'.$id.'/preview', ['mapping' => ['device_code' => 'device', 'external_id' => 'id', 'punched_at' => 'time', 'direction' => 'direction']])->assertOk()->assertJsonPath('data.valid', true);
        $this->postJson('/api/v1/imports/'.$id.'/confirm', ['confirm' => true])->assertOk();
        $this->getJson('/api/v1/biometric/summary?month=2026-10')->assertOk()->assertJsonPath('data.items.0.punch_count', 2)->assertJsonPath('data.items.0.needs_review', false)->assertJsonPath('data.items.0.attendance_submitted', false);
        $this->assertDatabaseCount('attendance_records', 0);
    }

    public function test_reversed_or_missing_punch_pairs_require_manual_review(): void
    {
        $student = $this->student();
        $this->asUser($this->admin)->postJson('/api/v1/biometric/mappings', ['device_code' => 'fictional', 'external_id' => '001', 'student_id' => $student])->assertCreated();
        $id = $this->upload('biometric', "device,id,time,direction\nfictional,001,2026-10-01 08:00:00,out\nfictional,001,2026-10-01 13:00:00,in\nfictional,001,2026-10-02 08:00:00,in\n");
        $this->postJson('/api/v1/imports/'.$id.'/preview', ['mapping' => ['device_code' => 'device', 'external_id' => 'id', 'punched_at' => 'time', 'direction' => 'direction']])->assertOk()->assertJsonPath('data.valid', true);
        $this->postJson('/api/v1/imports/'.$id.'/confirm', ['confirm' => true])->assertOk();
        $this->getJson('/api/v1/biometric/summary?month=2026-10')->assertOk()->assertJsonCount(2, 'data.items')->assertJsonPath('data.items.0.needs_review', true)->assertJsonPath('data.items.1.needs_review', true);
        $this->assertDatabaseCount('attendance_records', 0);
    }
}
