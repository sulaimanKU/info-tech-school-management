<?php

namespace Tests\Feature;

use App\Services\NotificationService;
use App\Services\Push\PushDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Support\MobileTestCase;

class PushNotificationTest extends MobileTestCase
{
    public function test_device_token_is_encrypted_owned_and_revoked_on_logout(): void
    {
        $this->asUser($this->admin);
        $id = $this->postJson('/api/v1/push-devices', ['token' => 'fictional-test-device-token-0001', 'platform' => 'android'])->assertOk()->json('data.id');
        $row = DB::table('push_devices')->find($id);
        $this->assertStringNotContainsString('fictional-test-device', $row->token_ciphertext);
        $this->asUser($this->teacher())->deleteJson('/api/v1/push-devices/'.$id)->assertNotFound();
        // A device belongs to the registering session, not any session for that account.
        $this->asUser($this->admin)->deleteJson('/api/v1/push-devices/'.$id)->assertNotFound();
        $this->postJson('/api/v1/push-devices', ['token' => 'fictional-test-device-token-0001', 'platform' => 'android'])->assertOk();
        $this->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertNotNull(DB::table('push_devices')->find($id)->revoked_at);
    }

    public function test_disabled_push_never_fakes_external_delivery(): void
    {
        Http::fake();
        config(['school.push_driver' => 'disabled']);
        $this->asUser($this->admin)->postJson('/api/v1/push-devices', ['token' => 'fictional-test-device-token-0001', 'platform' => 'android'])->assertOk();
        app(NotificationService::class)->user($this->admin->id, 'announcement', 'announcement', (string) Str::uuid(), 'fictional-event');
        $counts = app(PushDispatcher::class)->dispatch();
        $this->assertSame(0, $counts['sent']);
        $this->assertSame(1, $counts['disabled']);
        Http::assertNothingSent();
    }

    public function test_dispatch_sends_only_generic_metadata_and_records_provider_acceptance(): void
    {
        $conf = tempnam(sys_get_temp_dir(), 'fictional-openssl');
        file_put_contents($conf, "[ req ]\ndefault_bits = 2048\ndistinguished_name = dn\n[ dn ]\n");
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'config' => $conf]);
        openssl_pkey_export($key, $private, null, ['config' => $conf]);
        unlink($conf);
        $path = tempnam(sys_get_temp_dir(), 'fictional-fcm');
        file_put_contents($path, json_encode(['project_id' => 'fictional-test-project', 'client_email' => 'fictional@example.invalid', 'private_key' => $private]));
        try {
            config(['school.push_driver' => 'fcm', 'school.firebase_credentials' => $path]);
            Http::fake(['oauth2.googleapis.com/*' => Http::response(['access_token' => 'fictional-oauth-test', 'expires_in' => 3600]), 'fcm.googleapis.com/*' => Http::response(['name' => 'fictional-provider-acceptance'])]);
            $this->asUser($this->admin)->postJson('/api/v1/push-devices', ['token' => 'fictional-test-device-token-0001', 'platform' => 'android'])->assertOk();
            app(NotificationService::class)->user($this->admin->id, 'result', 'assessment', (string) Str::uuid(), 'fictional-result');
            $counts = app(PushDispatcher::class)->dispatch();
            $this->assertSame(1, $counts['sent']);
            $this->assertDatabaseHas('push_deliveries', ['status' => 'sent', 'attempts' => 1]);
            Http::assertSent(function ($r) {
                if (! str_contains($r->url(), 'fcm.googleapis.com')) {
                    return false;
                }$message = $r->data()['message'];
                $this->assertSame(['notification_id'], array_keys($message['data']));
                $this->assertSame('A school update is available. Sign in to view it.', $message['notification']['body']);

                return true;
            });
            $this->assertSame(0, app(PushDispatcher::class)->dispatch()['sent']);
        } finally {
            unlink($path);
        }
    }
}
