<?php

namespace App\Services;

use App\Contracts\OtpDeliveryProvider;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use LogicException;
use RuntimeException;

class WhatsappGatewayOtpDeliveryProvider implements OtpDeliveryProvider
{
    public function __construct(
        private readonly string $gatewayUrl,
        private readonly string $endpoint,
        private readonly string $internalToken,
        private readonly int $timeoutSeconds,
    ) {}

    public function deliver(
        string $phone,
        string $code,
        CarbonInterface $expiresAt,
        string $channel,
    ): string {
        if ($channel !== 'whatsapp') {
            throw new LogicException('The WhatsApp gateway can only deliver WhatsApp OTP requests.');
        }

        try {
            $response = Http::withHeaders([
                'X-Internal-Token' => $this->internalToken,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
                ->connectTimeout(min(5, $this->timeoutSeconds))
                ->timeout($this->timeoutSeconds)
                ->post($this->requestUrl(), [
                    'mobile' => $phone,
                ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('WhatsApp OTP delivery could not reach the gateway.', 0, $exception);
        }

        if (! $response->successful()) {
            throw new RuntimeException('WhatsApp OTP delivery was rejected by the gateway.');
        }

        $otp = $response->json('otp');

        if (! is_string($otp) || preg_match('/^\d{6}$/', $otp) !== 1) {
            throw new RuntimeException('WhatsApp OTP gateway returned an invalid response.');
        }

        return $otp;
    }

    private function requestUrl(): string
    {
        return rtrim($this->gatewayUrl, '/').'/'.ltrim($this->endpoint, '/');
    }
}
