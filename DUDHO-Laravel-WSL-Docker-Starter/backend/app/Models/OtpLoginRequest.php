<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'client_uuid',
    'request_hash',
    'user_id',
    'response_payload',
])]
class OtpLoginRequest extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'response_payload' => 'encrypted:array',
        ];
    }
}
