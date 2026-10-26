<?php

namespace Tests\Feature;

use App\Http\Controllers\ParentAuthController;
use App\Services\GuardianIdentity;
use App\Services\Sms\LocalSmsProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\MobileTestCase;

class ParentOtpTest extends MobileTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['school.sms_driver' => 'local']);
    }

    private function requestCode(string $cnic = '0000000000001')
    {
        return $this->postJson('/api/v1/parent-auth/request', ['cnic' => $cnic]);
    }

    public function test_correct_otp_creates_parent_session_without_identity_exposure(): void
    {
        [$g,$parent] = $this->guardian($this->student());
        $request = $this->requestCode()->assertStatus(202)->assertDontSee('0000000000001')->assertDontSee('+923000000001');
        $id = $request->json('data.challenge_id');
        $otp = (new LocalSmsProvider)->consumeForDevelopment($id);
        $this->assertNotNull($otp);
        $this->assertDatabaseMissing('otp_challenges', ['otp_hash' => $otp]);
        $login = $this->postJson('/api/v1/parent-auth/verify', ['challenge_id' => $id, 'otp' => $otp])->assertOk()->assertJsonPath('data.user.id', $parent->id);
        $this->withToken($login->json('data.token'))->getJson('/api/v1/students')->assertOk()->assertJsonCount(1, 'data.data');
        $this->postJson('/api/v1/parent-auth/verify', ['challenge_id' => $id, 'otp' => $otp])->assertUnauthorized();
    }

    public function test_incorrect_otp_attempts_are_committed_and_lock_out(): void
    {
        $this->guardian($this->student());
        $id = $this->requestCode()->json('data.challenge_id');
        $otp = (new LocalSmsProvider)->consumeForDevelopment($id);
        $bad = $otp === '000000' ? '111111' : '000000';
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/parent-auth/verify', ['challenge_id' => $id, 'otp' => $bad])->assertUnauthorized();
        }
        $this->assertDatabaseHas('otp_challenges', ['id' => $id, 'attempts' => 5]);
        $this->postJson('/api/v1/parent-auth/verify', ['challenge_id' => $id, 'otp' => $otp])->assertUnauthorized();
    }

    public function test_expiry_resend_cooldown_and_unknown_identity(): void
    {
        $this->guardian($this->student());
        $id = $this->requestCode()->json('data.challenge_id');
        $otp = (new LocalSmsProvider)->consumeForDevelopment($id);
        $this->requestCode()->assertStatus(429);
        $this->travel(6)->minutes();
        $this->postJson('/api/v1/parent-auth/verify', ['challenge_id' => $id, 'otp' => $otp])->assertUnauthorized();
        $unknown = $this->requestCode('0000000000099')->assertStatus(202);
        $this->assertNull((new LocalSmsProvider)->consumeForDevelopment($unknown->json('data.challenge_id')));
    }

    public function test_local_provider_cannot_send_in_production(): void
    {
        $this->app->instance('env', 'production');
        $this->expectException(HttpException::class);
        (new LocalSmsProvider)->sendOtp('test', 'not-real', '000000');
    }

    public function test_inactive_guardian_and_malformed_cnic_and_unauthenticated_session(): void
    {
        [$id,$parent] = $this->guardian($this->student());
        $parent->update(['is_active' => false]);
        $request = $this->requestCode()->assertStatus(202);
        $this->assertNull((new LocalSmsProvider)->consumeForDevelopment($request->json('data.challenge_id')));
        $this->postJson('/api/v1/parent-auth/request', ['cnic' => 'invalid'])->assertUnprocessable();
    }

    public function test_resend_invalidates_previous_code_and_normalized_identity_works(): void
    {
        $this->guardian($this->student());
        $one = $this->postJson('/api/v1/parent-auth/request', ['cnic' => '00000-0000000-1'])->assertStatus(202)->json('data.challenge_id');
        $code = (new LocalSmsProvider)->consumeForDevelopment($one);
        $this->travel(61)->seconds();
        $two = $this->requestCode()->assertStatus(202)->json('data.challenge_id');
        $this->postJson('/api/v1/parent-auth/verify', ['challenge_id' => $one, 'otp' => $code])->assertUnauthorized();
        $this->assertNotEquals($one, $two);
    }

    public function test_parent_profile_logout_and_production_disabled_provider(): void
    {
        [$g,$parent] = $this->guardian($this->student());
        $id = $this->requestCode()->json('data.challenge_id');
        $code = (new LocalSmsProvider)->consumeForDevelopment($id);
        $login = $this->postJson('/api/v1/parent-auth/verify', ['challenge_id' => $id, 'otp' => $code])->assertOk();
        $this->withToken($login->json('data.token'))->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.id', $parent->id);
        $this->postJson('/api/v1/auth/logout')->assertOk();
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->app->instance('env', 'production');
        config(['school.sms_driver' => 'local']);
        $this->postJson('/api/v1/parent-auth/request', ['cnic' => '0000000000001'])->assertStatus(503)->assertJsonMissingPath('data.otp');
    }

    public function test_failed_local_delivery_rolls_back_challenge_instead_of_false_success(): void
    {
        $this->guardian($this->student());
        Cache::shouldReceive('put')->once()->andReturn(false);
        $r = Request::create('/fixture', 'POST', ['cnic' => '0000000000001']);
        try {
            app(ParentAuthController::class)->requestOtp($r, app(GuardianIdentity::class), new LocalSmsProvider);
            $this->fail('Failed simulator must not report success.');
        } catch (HttpException $e) {
            $this->assertEquals(503, $e->getStatusCode());
        }
        $this->assertDatabaseCount('otp_challenges', 0);
    }

    public function test_local_cli_delivery_is_one_time_and_production_rejected(): void
    {
        $this->app->instance('env', 'local');
        $this->guardian($this->student());
        $this->requestCode()->assertStatus(202);
        $this->artisan('school:local-otp')->assertExitCode(0);
        $this->artisan('school:local-otp')->expectsOutput('No unconsumed local delivery.')->assertExitCode(1);
        $this->app->instance('env', 'production');
        $this->artisan('school:local-otp')->expectsOutput('Local simulator only.')->assertExitCode(1);
    }
}
