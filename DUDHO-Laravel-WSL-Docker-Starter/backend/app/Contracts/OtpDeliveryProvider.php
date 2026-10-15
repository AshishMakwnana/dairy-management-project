<?php

namespace App\Contracts;

use Carbon\CarbonInterface;

interface OtpDeliveryProvider
{
    public function deliver(
        string $phone,
        string $code,
        CarbonInterface $expiresAt,
        string $channel,
    ): void;
}
