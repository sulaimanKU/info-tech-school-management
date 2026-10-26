<?php

namespace App\Contracts;

interface SmsProvider
{
    public function sendOtp(string $challengeId, string $mobile, string $otp): void;
}
