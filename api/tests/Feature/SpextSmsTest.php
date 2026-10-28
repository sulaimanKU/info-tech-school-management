<?php

namespace Tests\Feature;

use App\Contracts\SmsProvider;
use App\Services\GuardianIdentity;
use App\Services\Sms\DisabledSmsProvider;
use App\Services\Sms\LocalSmsProvider;
use App\Services\Sms\SpextSmsProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\MobileTestCase;

class SpextSmsTest extends MobileTestCase
{
    private const URL = 'https://sms.example.invalid/sendsms';

    private const KEY = 'fictional-spext-key-for-http-fakes-only';

    protected function setUp(): void
    {
        parent::setUp();
        config(['school.sms_driver' => 'spext', 'school.spext.api_url' => self::URL, 'school.spext.api_key' => self::KEY]);
        Http::preventStrayRequests();
    }

    private function fakeSuccess(): void
    {
        Http::fake([self::URL => Http::response(['STATUS' => 'SUCCESSFUL', 'MESSAGE_ID' => 'fictional-message', 'ERROR_CODE' => ''])]);
    }

    private function requestCode()
    {
        return $this->postJson('/api/v1/parent-auth/request', ['cnic' => '00000-0000000-1']);
    }

    private function assertNoSensitiveLogs(): void
    {
        foreach (['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug', 'log'] as $level) {
            Log::shouldNotHaveReceived($level);
        }
    }

    public function test_driver_resolution_keeps_local_environment_guard_and_disabled_fallback(): void
    {
        $this->assertInstanceOf(SpextSmsProvider::class, app(SmsProvider::class));
        $this->app->instance('env', 'production');
        $this->assertInstanceOf(SpextSmsProvider::class, app(SmsProvider::class));
        config(['school.sms_driver' => 'local']);
        $this->assertInstanceOf(DisabledSmsProvider::class, app(SmsProvider::class));
        $this->app->instance('env', 'testing');
        $this->assertInstanceOf(LocalSmsProvider::class, app(SmsProvider::class));
        foreach (['disabled', 'unknown'] as $driver) {
            config(['school.sms_driver' => $driver]);
            $this->assertInstanceOf(DisabledSmsProvider::class, app(SmsProvider::class));
        }
        Http::assertNothingSent();
    }

    public function test_converted_guardian_receives_the_documented_request_and_hashed_otp_logs_in(): void
    {
        [$id, $parent] = $this->guardian($this->student());
        DB::table('parent_guardians')->where('id', $id)->update([
            'cnic' => null, 'mobile' => null,
            'cnic_ciphertext' => Crypt::encryptString('0000000000001'),
            'mobile_ciphertext' => Crypt::encryptString('+923000000001'),
            'cnic_lookup' => app(GuardianIdentity::class)->lookup('0000000000001'), 'cnic_last4' => '0001',
        ]);
        $this->artisan('school:convert-readable-data')->assertSuccessful();
        $this->fakeSuccess();
        Log::spy();
        $response = $this->requestCode()->assertStatus(202)->assertDontSee(self::KEY)->assertDontSee('+923000000001')->assertJsonMissingPath('data.otp');
        $challenge = DB::table('otp_challenges')->find($response->json('data.challenge_id'));
        $otp = null;
        Http::assertSent(function (Request $request) use (&$otp) {
            $this->assertSame('POST', $request->method());
            $this->assertSame(self::URL, $request->url());
            $this->assertTrue($request->hasHeader('Content-Type', 'application/json'));
            $this->assertSame(['apikey', 'receivernum', 'textmessage'], array_keys($request->data()));
            $this->assertSame(self::KEY, $request['apikey']);
            $this->assertSame('+923000000001', $request['receivernum']);
            $this->assertMatchesRegularExpression('/^Your INFO-TECH parent login code is ([0-9]{6})\. It expires in 300 seconds\. Do not share this code\.$/', $request['textmessage']);
            preg_match('/code is ([0-9]{6})\./', $request['textmessage'], $matches);
            $otp = $matches[1];

            return true;
        });
        Http::assertSentCount(1);
        $this->assertSame($id, $challenge->parent_guardian_id);
        $this->assertNotSame($otp, $challenge->otp_hash);
        $this->assertTrue(Hash::check($otp, $challenge->otp_hash));
        $this->assertStringNotContainsString($otp, $response->getContent());
        $this->assertTrue(Hash::check('fictional-password-only', $this->admin->fresh()->password));
        $this->assertNotSame('fictional-password-only', $this->admin->fresh()->password);
        $this->postJson('/api/v1/parent-auth/verify', ['challenge_id' => $challenge->id, 'otp' => $otp])->assertOk()
            ->assertJsonPath('data.user.id', $parent->id)->assertDontSee(self::KEY)->assertDontSee('+923000000001');
        $this->postJson('/api/v1/parent-auth/verify', ['challenge_id' => $challenge->id, 'otp' => $otp])->assertUnauthorized();
        $this->assertNoSensitiveLogs();
    }

    public static function failures(): array
    {
        return [
            'server failure' => [500, ['STATUS' => 'FAILED', 'ERROR_DESCRIPTION' => self::KEY]],
            'bad credentials' => [401, ['apikey' => self::KEY]],
            'rate limited' => [429, ['error' => self::KEY]],
            'redirect' => [302, ['apikey' => self::KEY]],
            'gateway rejection despite HTTP 200' => [200, ['STATUS' => 'FAILED', 'ERROR_DESCRIPTION' => self::KEY]],
            'missing status' => [200, ['apikey' => self::KEY]],
            'malformed body' => [200, '<html>'.self::KEY.'</html>'],
            'error code despite successful status' => [200, ['STATUS' => 'SUCCESSFUL', 'ERROR_CODE' => 'INVALID_KEY', 'ERROR_DESCRIPTION' => self::KEY]],
        ];
    }

    #[DataProvider('failures')]
    public function test_provider_failure_is_redacted_and_rolls_back_the_challenge(int $status, array|string $body): void
    {
        $this->guardian($this->student());
        Http::fake([self::URL => Http::response($body, $status, ['Location' => 'https://redirect.example.invalid/'])]);
        Log::spy();
        $this->requestCode()->assertStatus(503)->assertExactJson([
            'data' => null, 'error' => ['code' => 503, 'message' => 'Service unavailable.', 'fields' => []],
        ])->assertDontSee(self::KEY)->assertDontSee('+923000000001');
        Http::assertSentCount(1);
        $this->assertDatabaseCount('otp_challenges', 0);
        $this->assertNoSensitiveLogs();
    }

    public function test_transport_exception_is_not_chained_logged_or_retried(): void
    {
        $this->guardian($this->student());
        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;
            throw new ConnectionException('Private transport details '.self::KEY);
        });
        Log::spy();
        $this->requestCode()->assertStatus(503)->assertDontSee(self::KEY)->assertDontSee('Private transport');
        $this->assertSame(1, $calls);
        $this->assertDatabaseCount('otp_challenges', 0);
        $this->assertNoSensitiveLogs();
    }

    public static function invalidConfiguration(): array
    {
        return [
            'missing URL' => ['', self::KEY],
            'missing key' => [self::URL, ''],
            'HTTP' => ['http://sms.example.invalid/sendsms', self::KEY],
            'URL user' => ['https://user@sms.example.invalid/sendsms', self::KEY],
            'URL query' => [self::URL.'?apikey=fictional', self::KEY],
            'URL fragment' => [self::URL.'#fragment', self::KEY],
        ];
    }

    #[DataProvider('invalidConfiguration')]
    public function test_invalid_configuration_fails_closed_without_network(string $url, string $key): void
    {
        $this->guardian($this->student());
        config(['school.spext.api_url' => $url, 'school.spext.api_key' => $key]);
        Http::fake();
        Log::spy();
        $this->requestCode()->assertStatus(503)->assertDontSee(self::KEY);
        Http::assertNothingSent();
        $this->assertDatabaseCount('otp_challenges', 0);
        $this->assertNoSensitiveLogs();
    }

    public function test_unknown_and_inactive_parents_do_not_trigger_external_requests(): void
    {
        $this->fakeSuccess();
        $this->requestCode()->assertStatus(202);
        $this->travel(61)->seconds();
        [, $parent] = $this->guardian($this->student());
        $parent->update(['is_active' => false]);
        $this->requestCode()->assertStatus(202);
        Http::assertNothingSent();
    }

    public function test_failed_resend_preserves_the_previous_challenge(): void
    {
        $this->guardian($this->student());
        Http::fake([self::URL => Http::sequence()
            ->push(['STATUS' => 'SUCCESSFUL', 'ERROR_CODE' => ''])
            ->push(['STATUS' => 'FAILED'])]);
        $challenge = $this->requestCode()->assertStatus(202)->json('data.challenge_id');
        $this->travel(61)->seconds();
        $this->requestCode()->assertStatus(503);
        Http::assertSentCount(2);
        $this->assertDatabaseCount('otp_challenges', 1);
        $this->assertNull(DB::table('otp_challenges')->find($challenge)->consumed_at);
    }
}
