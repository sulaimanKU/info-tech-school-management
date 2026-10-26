<?php

namespace App\Services\Sms;

use App\Contracts\SmsProvider;

final class DisabledSmsProvider implements SmsProvider
{
    public function sendOtp(string $challengeId, string $mobile, string $otp): void
    {
        abort(503);
    }
}
