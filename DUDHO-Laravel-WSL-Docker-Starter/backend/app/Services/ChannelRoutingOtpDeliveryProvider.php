<?php

namespace App\Services;

use App\Contracts\OtpDeliveryProvider;
use Carbon\CarbonInterface;
use LogicException;

class ChannelRoutingOtpDeliveryProvider implements OtpDeliveryProvider
{
    /** @param array<string, OtpDeliveryProvider> $providers */
    public function __construct(private readonly array $providers) {}

    public function deliver(
        string $phone,
        string $code,
        CarbonInterface $expiresAt,
        string $channel,
    ): string {
        $provider = $this->providers[$channel] ?? null;

        if ($provider === null) {
            throw new LogicException("OTP delivery channel [{$channel}] is not configured.");
        }

        return $provider->deliver($phone, $code, $expiresAt, $channel);
    }
}
