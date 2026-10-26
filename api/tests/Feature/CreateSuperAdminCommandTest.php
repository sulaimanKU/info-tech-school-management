<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\MobilePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CreateSuperAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Fictional-bootstrap-test-2026!';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->seed(MobilePermissionSeeder::class);
    }

    private function prompted(array $input = [])
    {
        $v = [...['name' => 'Fictional Managing Director', 'email' => 'director@example.test', 'password' => self::PASSWORD, 'confirmation' => self::PASSWORD], ...$input];

        return $this->artisan('school:create-super-admin')
            ->expectsQuestion('Full name', $v['name'])
            ->expectsQuestion('Email', $v['email'])
            ->expectsQuestion('Password', $v['password'])
            ->expectsQuestion('Password confirmation', $v['confirmation'])
            ->doesntExpectOutputToContain(self::PASSWORD);
    }

    public function test_creates_hashed_active_user_with_only_the_existing_super_admin_role(): void
    {
        $roles = Role::count();
        $this->prompted(['name' => '  Fictional Managing Director  ', 'email' => ' Director@Example.Test '])
            ->expectsOutput('Super Admin / Managing Director account created successfully.')
            ->assertSuccessful();

        $user = User::where('email', 'director@example.test')->sole();
        $this->assertSame('Fictional Managing Director', $user->name);
        $this->assertTrue($user->is_active);
        $this->assertNotSame(self::PASSWORD, $user->getRawOriginal('password'));
        $this->assertTrue(Hash::check(self::PASSWORD, $user->getRawOriginal('password')));
        $this->assertSame(['super_admin'], $user->roles()->pluck('code')->all());
        $this->assertSame('Super Admin / Managing Director', $user->roles()->sole()->name);
        $this->assertSame($roles, Role::count());
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('user_role_assignments', 1);
        $this->assertTrue($user->hasPermission('finance.approve'));
        $audit = DB::table('audit_logs')->where('event', 'super_admin_account_created')->sole();
        $this->assertSame($user->id, $audit->subject_id);
        $this->assertNull($audit->user_id);
        $this->assertStringNotContainsString(self::PASSWORD, json_encode($audit));

        $this->postJson('/api/v1/auth/login', ['email' => 'director@example.test', 'password' => self::PASSWORD])
            ->assertOk()->assertJsonPath('data.user.roles.0.code', 'super_admin');
    }

    public function test_duplicate_email_is_rejected_without_changing_the_existing_account(): void
    {
        $existing = User::create(['name' => 'Fictional Existing Teacher', 'email' => 'director@example.test', 'password' => 'Fictional-original-password']);
        $existing->roles()->attach(Role::where('code', 'teacher')->sole());
        $hash = $existing->getRawOriginal('password');

        $this->prompted(['email' => 'DIRECTOR@EXAMPLE.TEST'])
            ->expectsOutput('The email has already been taken.')->assertFailed();

        $this->assertDatabaseCount('users', 1);
        $this->assertSame($hash, $existing->fresh()->getRawOriginal('password'));
        $this->assertSame(['teacher'], $existing->roles()->pluck('code')->all());
        $this->assertDatabaseMissing('audit_logs', ['event' => 'super_admin_account_created']);
    }

    #[DataProvider('invalidInput')]
    public function test_invalid_input_creates_no_user_or_assignment(array $input): void
    {
        $this->prompted($input)->assertFailed();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('user_role_assignments', 0);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'super_admin_account_created']);
    }

    public static function invalidInput(): array
    {
        return [
            'blank name' => [['name' => '   ']],
            'long name' => [['name' => str_repeat('N', 201)]],
            'invalid email' => [['email' => 'not-an-email']],
            'empty email' => [['email' => '']],
            'empty password' => [['password' => '', 'confirmation' => '']],
            'short password' => [['password' => 'too-short', 'confirmation' => 'too-short']],
            'oversized password' => [['password' => str_repeat('a', 1025), 'confirmation' => str_repeat('a', 1025)]],
            'mismatched confirmation' => [['confirmation' => 'Fictional-different-password']],
            'empty confirmation' => [['confirmation' => '']],
        ];
    }

    public function test_missing_role_does_not_create_a_role_or_orphan_account(): void
    {
        $role = Role::where('code', 'super_admin')->sole();
        DB::table('permission_role')->where('role_id', $role->id)->delete();
        $role->delete();

        $this->prompted()->expectsOutput('The existing super_admin role is missing. Run the project role/reference seeders first. No account was created.')->assertFailed();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('user_role_assignments', 0);
        $this->assertDatabaseMissing('roles', ['code' => 'super_admin']);
    }

    public function test_assignment_failure_rolls_back_user_creation(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Exercises a PostgreSQL assignment-failure trigger.');
        }
        // Transactional test-only DDL is removed by RefreshDatabase's rollback.
        DB::unprepared("CREATE FUNCTION validation_reject_admin_assignment() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'Fictional assignment failure' USING ERRCODE='23514'; END $$");
        DB::unprepared('CREATE TRIGGER validation_reject_admin_assignment BEFORE INSERT ON user_role_assignments FOR EACH ROW EXECUTE FUNCTION validation_reject_admin_assignment()');

        $this->prompted()->expectsOutput('The database operation failed. No account was created.')->assertFailed();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('user_role_assignments', 0);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'super_admin_account_created']);
    }

    public function test_noninteractive_execution_is_rejected(): void
    {
        $this->artisan('school:create-super-admin', ['--no-interaction' => true])
            ->expectsOutput('An interactive terminal is required. Passwords cannot be supplied as command arguments.')
            ->assertFailed();
        $this->assertDatabaseCount('users', 0);
    }
}
