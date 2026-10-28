<?php

namespace App\Services;

use App\Support\Domain;

final class GuardianIdentity
{
    public function normalize(string $cnic): string
    {
        if (! preg_match('/^[0-9 -]+$/D', $cnic)) {
            Domain::fail('cnic', 'Enter a valid 13-digit CNIC.');
        }
        $value = preg_replace('/[ -]/', '', $cnic);
        if (! preg_match('/^[0-9]{13}$/D', $value)) {
            Domain::fail('cnic', 'Enter a valid 13-digit CNIC.');
        }

        return $value;
    }

    public function lookup(string $cnic): string
    {
        // Only OTP challenge correlation/rate limiting and legacy conversion use this
        // keyed digest. Guardian records and login queries use normalized cnic directly.
        $key = config('school.cnic_lookup_key') ?: hash_hkdf('sha256', (string) config('app.key'), 32, 'infotech-cnic-lookup-v1');

        return hash_hmac('sha256', $this->normalize($cnic), $key);
    }

    public function mask(string $cnic): string
    {
        return '*****-*******-'.substr($cnic, -1);
    }
}
