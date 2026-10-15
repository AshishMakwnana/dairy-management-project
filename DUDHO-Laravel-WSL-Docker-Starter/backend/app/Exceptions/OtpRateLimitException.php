<?php

namespace App\Exceptions;

use RuntimeException;

class OtpRateLimitException extends RuntimeException
{
    public function __construct(public readonly int $retryAfterSeconds)
    {
        parent::__construct('Too many OTP requests.');
    }
}
