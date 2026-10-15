<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'uuid',
    'mobile_country_code',
    'mobile',
    'channel',
    'locale',
    'otp_hash',
    'expires_at',
    'verified_at',
    'attempt_count',
    'resend_count',
    'last_sent_at',
    'ip_address',
    'user_agent',
])]
#[Hidden(['otp_hash', 'ip_address', 'user_agent'])]
class OtpChallenge extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'last_sent_at' => 'datetime',
        ];
    }
}
