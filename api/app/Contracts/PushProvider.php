<?php

namespace App\Contracts;

interface PushProvider
{
    /** Returns sent, invalid_token, retry, or disabled. Never returns simulated success. */
    public function send(string $token, string $notificationId): string;
}
