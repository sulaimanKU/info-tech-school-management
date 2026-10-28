<?php

namespace Tests\Feature;

use App\Services\Finance\FinancialLock;
use App\Support\Domain;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Support\MobileTestCase;

class MySqlCompatibilityTest extends MobileTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Requires MySQL/MariaDB integrity and row locking.');
        }
    }

    public function test_connection_uses_unicode_karachi_and_statement_visibility(): void
    {
        $settings = DB::selectOne('SELECT @@character_set_connection AS charset, @@session.time_zone AS timezone, @@session.tx_isolation AS isolation_level');
        $this->assertSame('utf8mb4', $settings->charset);
        $this->assertSame('+05:00', $settings->timezone);
        $this->assertSame('READ-COMMITTED', $settings->isolation_level);
        $name = 'Unicode · اردو 🏫';
        DB::table('subjects')->where('id', $this->subject)->update(['name' => $name]);
        $this->assertSame($name, DB::table('subjects')->where('id', $this->subject)->value('name'));
    }

    public function test_conditional_unique_index_rejects_a_second_active_enrollment(): void
    {
        $student = $this->student();
        $this->expectException(QueryException::class);
        Domain::insert('enrollments', ['student_id' => $student, 'section_id' => $this->otherSection,
            'academic_session_id' => $this->session, 'roll_number' => 2, 'starts_on' => '2026-10-02',
            'status' => 'active', 'created_by' => $this->admin->id]);
    }

    public function test_internal_unique_columns_do_not_appear_in_api_rows(): void
    {
        $student = $this->student();
        $row = (array) DB::table('enrollments')->where('student_id', $student)->first();
        $this->assertArrayNotHasKey('active_student_key', $row);
        $this->assertArrayNotHasKey('active_roll_key', $row);
        DB::table('enrollments')->where('student_id', $student)->update(['status' => 'withdrawn']);
        Domain::insert('enrollments', ['student_id' => $student, 'section_id' => $this->otherSection,
            'academic_session_id' => $this->session, 'roll_number' => 1, 'starts_on' => '2026-10-02',
            'status' => 'active', 'created_by' => $this->admin->id]);
        $this->assertSame(2, DB::table('enrollments')->where('student_id', $student)->count());
    }

    public function test_duplicate_database_error_keeps_conflict_response_and_hides_sql(): void
    {
        Route::get('/api/v1/test-mysql-duplicate', function () {
            Domain::insert('subjects', ['code' => 'fictional-en', 'name' => 'Duplicate']);
        });
        $this->getJson('/api/v1/test-mysql-duplicate')->assertConflict()
            ->assertJsonPath('error.message', 'Record conflict. Refresh and retry.')
            ->assertDontSee('SQLSTATE')->assertDontSee('fictional-en');
    }

    public function test_check_database_error_keeps_validation_response_and_hides_sql(): void
    {
        Route::get('/api/v1/test-mysql-check', function () {
            Domain::insert('academic_sessions', ['name' => 'Invalid dates', 'starts_on' => '2026-12-31', 'ends_on' => '2026-01-01']);
        });
        $this->getJson('/api/v1/test-mysql-check')->assertUnprocessable()
            ->assertJsonPath('error.message', 'Validation failed.')->assertDontSee('SQLSTATE');
    }

    public function test_audit_style_records_are_immutable_at_database_level(): void
    {
        $student = $this->student();
        $this->expectException(QueryException::class);
        // Support events share the same immutable-record trigger as finalized finance.
        $ticket = Domain::insert('support_tickets', ['student_id' => $student, 'guardian_user_id' => $this->admin->id,
            'number' => 'FICTIONAL-IMMUTABLE', 'category' => 'general', 'subject' => 'Fictional']);
        $event = Domain::insert('support_ticket_events', ['support_ticket_id' => $ticket,
            'to_status' => 'submitted', 'created_by' => $this->admin->id]);
        DB::table('support_ticket_events')->where('id', $event)->delete();
    }

    public function test_transaction_lock_blocks_another_connection_and_releases_on_rollback(): void
    {
        $config = DB::connection()->getConfig();
        $first = DB::build($config);
        $second = DB::build($config);
        $key = hash('sha256', 'compatibility-lock:'.bin2hex(random_bytes(16)));
        try {
            $first->beginTransaction();
            $first->table('transaction_locks')->insert(['key' => $key]);
            $first->table('transaction_locks')->where('key', $key)->lockForUpdate()->firstOrFail();
            $second->statement('SET SESSION innodb_lock_wait_timeout = 1');
            $second->beginTransaction();
            try {
                $second->table('transaction_locks')->insertOrIgnore(['key' => $key]);
                $this->fail('A second connection must not enter the same request lock.');
            } catch (QueryException $e) {
                $this->assertSame(1205, (int) $e->errorInfo[1]);
            }
            $second->rollBack();
            $first->rollBack();
            $second->beginTransaction();
            $second->table('transaction_locks')->insert(['key' => $key]);
            $this->assertNotNull($second->table('transaction_locks')->where('key', $key)->lockForUpdate()->first());
        } finally {
            if ($first->transactionLevel()) {
                $first->rollBack();
            }
            if ($second->transactionLevel()) {
                $second->rollBack();
            }
            $first->disconnect();
            $second->disconnect();
        }
    }

    public function test_request_lock_keys_are_hashed_and_stable(): void
    {
        app(FinancialLock::class)->request('parent-otp', 'fictional-identity');
        app(FinancialLock::class)->request('parent-otp', 'fictional-identity');
        $this->assertSame(1, DB::table('transaction_locks')->where('key', hash('sha256', 'parent-otp:fictional-identity'))->count());
    }

    public function test_migration_identifiers_and_table_engines_match_mariadb_limits(): void
    {
        $constraints = DB::selectOne('SELECT MAX(CHAR_LENGTH(CONSTRAINT_NAME)) AS longest FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE()');
        $indexes = DB::selectOne('SELECT MAX(CHAR_LENGTH(INDEX_NAME)) AS longest FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE()');
        $this->assertLessThanOrEqual(64, (int) $constraints->longest);
        $this->assertLessThanOrEqual(64, (int) $indexes->longest);
        $this->assertSame(0, (int) DB::selectOne("SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' AND ENGINE <> 'InnoDB'")->total);
    }

    public function test_query_builder_boolean_fields_keep_the_existing_flutter_contract(): void
    {
        $teacher = $this->teacher();
        $this->asUser($this->admin)->getJson('/api/v1/catalog/teacher-assignments')->assertOk()
            ->assertJsonPath('data.items.0.is_class_teacher', true)->assertJsonPath('data.items.0.is_active', true);
        $student = $this->student();
        [$guardian] = $this->guardian($student);
        $this->getJson('/api/v1/guardians/'.$guardian)->assertOk()
            ->assertJsonPath('data.children.0.is_primary', true)->assertJsonPath('data.children.0.is_active', true);
        $this->asUser($teacher)->getJson('/api/v1/account/profile')->assertOk()
            ->assertJsonPath('data.profile.is_active', true);
        $response = Domain::response(['items' => [['is_primary' => 0, 'whole_class' => 1, 'amount_minor' => 1]],
            'mapping' => ['is_active' => 1], 'snapshot' => ['whole_class' => 0], 'optional' => null])->getData(true);
        $this->assertFalse($response['data']['items'][0]['is_primary']);
        $this->assertTrue($response['data']['items'][0]['whole_class']);
        $this->assertSame(1, $response['data']['items'][0]['amount_minor']);
        $this->assertSame(1, $response['data']['mapping']['is_active']);
        $this->assertSame(0, $response['data']['snapshot']['whole_class']);
        $this->assertNull($response['data']['optional']);
    }
}
