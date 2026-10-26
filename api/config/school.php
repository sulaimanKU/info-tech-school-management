<?php

return [
    'cnic_lookup_key' => env('CNIC_LOOKUP_KEY'),
    'sms_driver' => env('SMS_DRIVER', 'disabled'),
    'otp_lifetime_seconds' => 300, 'otp_attempts' => 5, 'otp_cooldown_seconds' => 60,
    'upload_max_kb' => (int) env('SCHOOL_UPLOAD_MAX_KB', 5120),
    'firebase_credentials' => env('FIREBASE_CREDENTIALS'),
    'push_driver' => env('PUSH_DRIVER', 'disabled'),
    'whatsapp_driver' => env('WHATSAPP_DRIVER', 'disabled'),
];
