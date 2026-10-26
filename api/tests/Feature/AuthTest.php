<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function staff(string $role = 'teacher', bool $active = true): User
    {
        $u = User::create(['name' => 'Test Staff', 'email' => 'staff@example.test', 'password' => 'test-password-only', 'is_active' => $active]);
        $u->roles()->attach(Role::where('code', $role)->firstOrFail());

        return $u;
    }

    private function login()
    {
        return $this->postJson('/api/v1/auth/login', ['email' => 'staff@example.test', 'password' => 'test-password-only']);
    }

    public function test_login_and_current_user_and_logout(): void
    {
        $u = $this->staff();
        $login = $this->login()->assertOk()->assertJsonPath('data.user.id', $u->id);
        $login->assertJsonPath('data.token_type', 'Bearer')->assertJsonPath('data.user.roles.0.code', 'teacher')->assertJsonPath('data.user.permissions', [])->assertJsonMissingPath('data.user.password');
        $token = $login->json('data.token');
        $this->assertSame(64, strlen($token));
        $this->assertDatabaseHas('authentication_sessions', ['user_id' => $u->id, 'token_hash' => hash('sha256', $token), 'method' => 'staff_password']);
        $this->assertDatabaseMissing('authentication_sessions', ['token_hash' => $token]);
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk()->assertJsonMissingPath('data.password');
        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->assertDatabaseHas('audit_logs', ['event' => 'login_succeeded', 'user_id' => $u->id]);
    }

    public function test_invalid_credentials(): void
    {
        $this->staff();
        $this->postJson('/api/v1/auth/login', ['email' => 'staff@example.test', 'password' => 'wrong'])->assertUnauthorized();
    }

    public function test_unknown_account(): void
    {
        $this->login()->assertUnauthorized();
    }

    public function test_protected_endpoint(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_suspended_account(): void
    {
        $this->staff(active: false);
        $this->login()->assertUnauthorized();
    }

    public function test_parent_cannot_use_staff_login(): void
    {
        $this->staff('parent_guardian');
        $this->login()->assertUnauthorized();
    }

    public function test_permission_denied(): void
    {
        $this->staff();
        $token = $this->login()->json('data.token');
        $this->withToken($token)->getJson('/api/v1/school-settings')->assertForbidden();
    }

    public function test_permission_granted(): void
    {
        $this->staff('school_administrator');
        $token = $this->login()->json('data.token');
        $this->withToken($token)->getJson('/api/v1/school-settings')->assertOk()->assertJsonPath('data.currency', 'PKR');
    }

    public function test_suspension_revokes_access_immediately(): void
    {
        $u = $this->staff();
        $token = $this->login()->json('data.token');
        $u->update(['is_active' => false]);
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_expired_session(): void
    {
        $this->staff();
        $token = $this->login()->json('data.token');
        $this->travel(13)->hours();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_validation_and_not_found(): void
    {
        $this->postJson('/api/v1/auth/login', [])->assertUnprocessable()->assertJsonStructure(['data', 'error' => ['code', 'message', 'fields']]);
        $this->postJson('/api/v1/auth/login', ['login' => 'staff@example.test', 'password' => 'test-password-only'])->assertUnprocessable()->assertJsonValidationErrors('email', 'error.fields');
        $this->getJson('/api/v1/missing')->assertNotFound();
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->login()->assertUnauthorized();
        } $this->login()->assertStatus(429);
    }
}
