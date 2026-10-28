<?php

namespace Tests\Feature;

use App\Support\Domain;
use Database\Seeders\FinanceConfigurationSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\MobileTestCase;

class SecurityReviewTest extends MobileTestCase
{
    public function test_account_suspension_revokes_sessions_and_protects_privileged_accounts(): void
    {
        $teacher = $this->teacher();
        $this->asUser($teacher);
        $session = DB::table('authentication_sessions')->where('user_id', $teacher->id)->value('id');
        $this->asUser($this->admin)->patchJson('/api/v1/staff-accounts/'.$teacher->id.'/status', ['is_active' => false])->assertOk();
        $this->assertNotNull(DB::table('authentication_sessions')->find($session)->revoked_at);
        $this->asUser($teacher)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->asUser($this->user('school_administrator'))->patchJson('/api/v1/staff-accounts/'.$this->admin->id.'/status', ['is_active' => false])->assertForbidden();
        $this->asUser($this->admin)->patchJson('/api/v1/staff-accounts/'.$this->admin->id.'/status', ['is_active' => false])->assertUnprocessable();
    }

    public function test_sign_in_history_never_exposes_session_token_hashes(): void
    {
        $this->asUser($this->admin)->getJson('/api/v1/auth/history')->assertOk()->assertDontSee('token_hash')->assertJsonCount(1, 'data.data')->assertJsonPath('data.data.0.current', true);
    }

    public function test_future_promotion_does_not_prematurely_change_teacher_scope(): void
    {
        $student = $this->student();
        $oldTeacher = $this->teacher();
        $newSession = Domain::insert('academic_sessions', ['name' => 'Fictional future session', 'starts_on' => '2027-01-01', 'ends_on' => '2027-12-31']);
        $next = Domain::insert('sections', ['name' => 'Fictional next class', 'academic_session_id' => $newSession, 'class_id' => DB::table('classes')->where('name', 'Grade 2')->value('id')]);
        $newTeacher = $this->teacher($next);
        $this->asUser($this->admin)->postJson('/api/v1/students/'.$student.'/transitions', ['type' => 'promotion', 'effective_on' => '2027-01-01', 'section_id' => $next, 'roll_number' => 1])->assertOk();
        $this->asUser($oldTeacher)->getJson('/api/v1/students/'.$student)->assertOk();
        $this->asUser($newTeacher)->getJson('/api/v1/students/'.$student)->assertNotFound();
    }

    public function test_catalog_edits_do_not_reassign_enrolled_sections_or_staff_identity(): void
    {
        $student = $this->student();
        $this->asUser($this->admin)->patchJson('/api/v1/catalog/sessions/'.$this->session, ['name' => 'Fictional revised session'])->assertOk();
        $this->patchJson('/api/v1/catalog/sessions/'.$this->session, ['starts_on' => '2026-02-01'])->assertUnprocessable();
        $this->patchJson('/api/v1/catalog/sections/'.$this->section, ['class_id' => DB::table('classes')->where('name', 'Grade 3')->value('id')])->assertUnprocessable();
        $this->asUser($this->teacher())->patchJson('/api/v1/catalog/sessions/'.$this->session, ['name' => 'Forbidden'])->assertForbidden();
    }

    public function test_postgresql_deferred_balance_trigger_rejects_direct_unbalanced_insert(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL deferred constraint trigger; MariaDB validates journals in JournalService.');
        }
        $this->seed(FinanceConfigurationSeeder::class);
        $entry = Domain::insert('journal_entries', ['number' => 'FICTIONAL-BAD-JOURNAL', 'posting_key' => 'fictional-direct-insert', 'payload_hash' => str_repeat('0', 64), 'date' => now()->toDateString(), 'description' => 'Fictional constraint test', 'reference_type' => 'test', 'reference_id' => (string) Str::uuid(), 'posted_by' => $this->admin->id]);
        Domain::insert('journal_lines', ['journal_entry_id' => $entry, 'account_id' => DB::table('accounts')->where('code', 'cash')->value('id'), 'debit_minor' => 100, 'credit_minor' => 0]);
        $this->expectException(QueryException::class);
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }
}
