<?php

return [
    'delivery' => env('OTP_DELIVERY', 'local_mail'),
    'local_mail_to' => env('OTP_LOCAL_MAIL_TO', 'otp-preview@dudho.test'),
    'whatsapp_gateway' => [
        'url' => env('WHATSAPP_GATEWAY_URL'),
        'endpoint' => env('WHATSAPP_GATEWAY_ENDPOINT', '/api/send-otp'),
        'internal_token' => env('WHATSAPP_INTERNAL_TOKEN'),
        'timeout_seconds' => (int) env('WHATSAPP_GATEWAY_TIMEOUT_SECONDS', 10),
    ],
    'expires_minutes' => 5,
    'resend_cooldown_seconds' => 60,
    'max_challenge_attempts' => 5,
    'limits' => [
        'send_phone' => ['attempts' => 5, 'decay_seconds' => 3600],
        'send_source' => ['attempts' => 30, 'decay_seconds' => 3600],
        'verify_phone' => ['attempts' => 10, 'decay_seconds' => 900],
        'verify_source' => ['attempts' => 60, 'decay_seconds' => 900],
    ],
];
