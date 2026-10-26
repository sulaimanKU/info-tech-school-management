<?php

namespace App\Services\Sms;

use App\Contracts\SmsProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

final class LocalSmsProvider implements SmsProvider
{
    public function sendOtp(string $challengeId, string $mobile, string $otp): void
    {
        abort_unless(app()->environment(['local', 'testing']), 503);
        // Private local-only encrypted simulator, no logs/API response/real outbound SMS.
        abort_unless(Cache::put('local-otp:'.$challengeId, Crypt::encryptString($otp), now()->addSeconds(config('school.otp_lifetime_seconds'))), 503);
    }

    public function consumeForDevelopment(string $id): ?string
    {
        abort_unless(app()->environment(['local', 'testing']), 404);
        $value = Cache::pull('local-otp:'.$id);

        return $value ? Crypt::decryptString($value) : null;
    }
}
