<?php

namespace App\Contracts;

interface WhatsAppProvider
{
    /** Integration boundary only. Core school workflows must not require WhatsApp. */
    public function available(): bool;
}
