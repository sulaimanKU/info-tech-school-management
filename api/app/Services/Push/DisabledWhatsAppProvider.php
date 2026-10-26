<?php

namespace App\Services\Push;

use App\Contracts\WhatsAppProvider;

final class DisabledWhatsAppProvider implements WhatsAppProvider
{
    public function available(): bool
    {
        return false;
    }
}
