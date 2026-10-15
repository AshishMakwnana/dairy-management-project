<?php

namespace App\Exceptions;

use RuntimeException;

class OtpIdempotencyConflictException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This client UUID was already used with a different request.');
    }
}
