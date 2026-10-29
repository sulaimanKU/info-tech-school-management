<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SessionIssuer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\Support\MobileTestCase;

class SchoolManagedPasswordTest extends MobileTestCase
{
    public function test_four_character_staff_creation_reset_login_and_session_revocation(): void
    {
        $this->asUser($this->admin);
        $id = $this->postJson('/api/v1/staff-accounts', ['name' => 'Fictional Simple Password', 'email' => 'simple@example.test', 'role' => 'teacher', 'password' => '1234', 'password_confirmation' => '1234'])->assertCreated()->json('data.id');
        $user = User::findOrFail($id);
        $this->assertTrue(Hash::check('1234', $user->password));
        $this->assertNotSame('1234', $user->getRawOriginal('password'));
        $this->assertMatchesRegularExpression('/^ACC-[0-9]{6,}$/', $user->account_code);
        $login = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => '1234'])->assertOk()->assertJsonMissingPath('data.user.password');
        $oldToken = $login->json('data.token');
        $this->patchJson('/api/v1/staff-accounts/'.$id, ['password' => '5678', 'password_confirmation' => 'wrong'])->assertUnprocessable();
        $this->patchJson('/api/v1/staff-accounts/'.$id, ['password' => '5678', 'password_confirmation' => '5678'])->assertOk();
        $this->withToken($oldToken)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => '1234'])->assertUnauthorized();
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => '5678'])->assertOk();
        $this->asUser($this->admin)->patchJson('/api/v1/staff-accounts/'.$id, ['password' => '1234', 'password_confirmation' => '1234'])->assertOk();
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => '1234'])->assertOk();
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => '5678'])->assertUnauthorized();
        $this->assertTrue(Hash::check('1234', $user->fresh()->password));
        $this->getJson('/api/v1/staff-accounts/'.$id)->assertOk()->assertJsonPath('data.account_code', $user->account_code)->assertJsonMissingPath('data.password');
        $this->assertStringNotContainsString('1234', DB::table('audit_logs')->where('subject_id', $id)->pluck('context')->toJson());
    }

    public function test_own_change_to_1234_revokes_all_sessions_and_old_password(): void
    {
        $user = $this->teacher();
        $other = app(SessionIssuer::class)->issue($user, 'test')['token'];
        $this->asUser($user)->postJson('/api/v1/account/password', ['current_password' => 'fictional-password-only', 'password' => '1234', 'password_confirmation' => '1234'])->assertOk()->assertJsonPath('data.reauthenticate', true);
        $this->getJson('/api/v1/account/profile')->assertUnauthorized();
        $this->withToken($other)->getJson('/api/v1/account/profile')->assertUnauthorized();
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'fictional-password-only'])->assertUnauthorized();
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => '1234'])->assertOk();
        $this->assertTrue(Hash::check('1234', $user->fresh()->password));
    }

    public function test_reset_preserves_privileged_target_authorization_and_rejects_short_password(): void
    {
        $teacher = $this->teacher();
        $this->asUser($teacher)->patchJson('/api/v1/staff-accounts/'.$this->admin->id, ['password' => '1234', 'password_confirmation' => '1234'])->assertForbidden();
        $manager = $this->user('school_administrator');
        $this->asUser($manager)->patchJson('/api/v1/staff-accounts/'.$this->admin->id, ['password' => '1234', 'password_confirmation' => '1234'])->assertForbidden();
        $this->asUser($this->admin)->patchJson('/api/v1/staff-accounts/'.$teacher->id, ['password' => '123', 'password_confirmation' => '123'])->assertUnprocessable();
        $this->assertTrue(Hash::check('fictional-password-only', $teacher->fresh()->password));
    }

    public function test_login_cannot_issue_a_session_from_credentials_read_before_a_concurrent_reset(): void
    {
        $user = $this->teacher();
        $changed = false;
        $newHash = Hash::make('1234');
        $event = 'eloquent.retrieved: '.User::class;
        Event::listen($event, function (User $loaded) use ($user, $newHash, &$changed) {
            // Simulate the reset committing between login's initial read and its user lock.
            if ($loaded->id === $user->id && ! $changed) {
                $changed = true;
                DB::table('users')->where('id', $user->id)->update(['password' => $newHash]);
            }
        });
        try {
            $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'fictional-password-only'])->assertUnauthorized();
            $this->assertDatabaseMissing('authentication_sessions', ['user_id' => $user->id, 'revoked_at' => null]);
            $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => '1234'])->assertOk();
        } finally {
            Event::forget($event);
        }
    }

    public function test_bootstrap_refuses_production_and_existing_schools(): void
    {
        $this->artisan('school:bootstrap')->expectsOutput('Existing accounts found. Bootstrap will not reset an installed school.')->assertFailed();
        app()->instance('env', 'production');
        $this->artisan('school:bootstrap')->expectsOutput('Interactive local/testing installation only. Production is refused.')->assertFailed();
        $this->artisan('migrate:fresh', ['--no-interaction' => true])->expectsConfirmation('Are you sure you want to run this command?', false)->assertFailed();
        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
    }
}
