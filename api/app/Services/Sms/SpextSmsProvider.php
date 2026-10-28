<?php

namespace App\Services\Sms;

use App\Contracts\SmsProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

final class SpextSmsProvider implements SmsProvider
{
    public function sendOtp(string $challengeId, string $mobile, string $otp): void
    {
        $url = (string) config('school.spext.api_url');
        $key = (string) config('school.spext.api_key');
        $parts = parse_url($url);
        abort_unless(filter_var($url, FILTER_VALIDATE_URL) && ($parts['scheme'] ?? null) === 'https'
            && ! isset($parts['user']) && ! isset($parts['pass']) && ! isset($parts['query']) && ! isset($parts['fragment'])
            && trim($key) !== '' && preg_match('/^\+923[0-9]{9}$/D', $mobile) && preg_match('/^[0-9]{6}$/D', $otp), 503);

        try {
            // VeevoTech /sendsms JSON contract. Keep credentials out of URLs and logs.
            // No redirects or automatic retries: a timeout may follow provider acceptance.
            $response = Http::asJson()->acceptJson()->connectTimeout(5)->timeout(15)
                ->withOptions(['allow_redirects' => false])->post($url, [
                    'apikey' => $key,
                    'receivernum' => $mobile,
                    'textmessage' => 'Your INFO-TECH parent login code is '.$otp.'. It expires in '
                        .(int) config('school.otp_lifetime_seconds').' seconds. Do not share this code.',
                ]);
        } catch (ConnectionException|RequestException $e) {
            // Do not chain or report the transport exception: it may contain secrets.
            abort(503);
        }

        // HTTP 200 alone is not acceptance; reject gateway errors and malformed bodies.
        // Never return or log the gateway body, which can echo credentials or OTPs.
        abort_unless($response->successful() && $response->json('STATUS') === 'SUCCESSFUL'
            && in_array($response->json('ERROR_CODE'), [null, ''], true), 503);
    }
}
