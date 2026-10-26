<?php

namespace App\Services\Push;

use App\Contracts\PushProvider;

final class DisabledPushProvider implements PushProvider
{
    public function send(string $token, string $notificationId): string
    {
        return 'disabled';
    }
}
