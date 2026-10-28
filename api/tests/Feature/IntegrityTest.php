<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class IntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_reference_seeding_is_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseCount('roles', 6);
        $this->assertDatabaseCount('classes', 11);
        $this->assertDatabaseCount('permissions', 1);
        $this->assertDatabaseCount('permission_role', 2);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_database_rejects_reversed_academic_dates(): void
    {
        if (! in_array(DB::getDriverName(), ['pgsql', 'mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Database CHECK constraint test.');
        }
        $this->expectException(QueryException::class);
        DB::table('academic_sessions')->insert(['id' => (string) Str::uuid(), 'name' => 'Invalid test dates', 'starts_on' => '2026-10-21', 'ends_on' => '2026-01-01']);
    }

    public function test_server_error_response_does_not_expose_exception(): void
    {
        Route::get('/api/v1/test-error', fn () => throw new \RuntimeException('private-internal-detail'));
        $this->getJson('/api/v1/test-error')->assertStatus(500)->assertJsonMissingPath('exception')->assertDontSee('private-internal-detail');
    }
}
