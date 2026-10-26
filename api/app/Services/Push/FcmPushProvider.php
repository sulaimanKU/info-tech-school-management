<?php

namespace App\Services\Push;

use App\Contracts\PushProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

final class FcmPushProvider implements PushProvider
{
    private function b64(string $v): string
    {
        return rtrim(strtr(base64_encode($v), '+/', '-_'), '=');
    }

    public function send(string $token, string $notificationId): string
    {
        $path = config('school.firebase_credentials');
        if (! $path || ! is_file($path)) {
            return 'disabled';
        }
        try {
            $c = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
            if (! preg_match('/^[a-z0-9-]{4,100}$/', $c['project_id'] ?? '') || empty($c['private_key']) || empty($c['client_email'])) {
                return 'disabled';
            }
            $key = 'fcm-oauth:'.hash('sha256', $c['project_id'].$c['client_email'].hash('sha256', $c['private_key']));
            $cached = Cache::get($key);
            $access = $cached ? Crypt::decryptString($cached) : null;
            if (! $access) {
                $now = time();
                $jwt = $this->b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])).'.'.$this->b64(json_encode(['iss' => $c['client_email'], 'scope' => 'https://www.googleapis.com/auth/firebase.messaging', 'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $now, 'exp' => $now + 3600]));
                if (! openssl_sign($jwt, $sig, $c['private_key'], OPENSSL_ALGO_SHA256)) {
                    return 'disabled';
                }$assertion = $jwt.'.'.$this->b64($sig);
                $response = Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', ['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $assertion]);
                if (! $response->successful()) {
                    return 'retry';
                }$access = $response->json('access_token');
                if (! $access) {
                    return 'retry';
                }Cache::put($key, Crypt::encryptString($access), max(1, min(3300, (int) $response->json('expires_in', 3600) - 60)));
            }
            $response = Http::withToken($access)->timeout(15)->post('https://fcm.googleapis.com/v1/projects/'.$c['project_id'].'/messages:send', ['message' => ['token' => $token, 'notification' => ['title' => 'INFO-TECH School', 'body' => 'A school update is available. Sign in to view it.'], 'data' => ['notification_id' => $notificationId], 'android' => ['priority' => 'normal', 'ttl' => '3600s', 'notification' => ['tag' => $notificationId]]]]);
            if ($response->successful()) {
                return 'sent';
            }
            foreach ($response->json('error.details', []) as $detail) {
                if (($detail['errorCode'] ?? null) === 'UNREGISTERED') {
                    return 'invalid_token';
                }
            }
            if (in_array($response->status(), [401, 403])) {
                Cache::forget($key);
            }

            return 'retry';
        } catch (\Throwable) {
            return 'retry';
        }
    }
}
