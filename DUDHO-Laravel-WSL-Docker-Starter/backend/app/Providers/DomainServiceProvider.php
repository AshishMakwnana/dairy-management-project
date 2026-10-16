<?php

namespace App\Providers;

use App\Contracts\HealthService;
use App\Contracts\OtpDeliveryProvider;
use App\Services\ChannelRoutingOtpDeliveryProvider;
use App\Services\LocalMailOtpDeliveryProvider;
use App\Services\SystemHealthService;
use App\Services\WhatsappGatewayOtpDeliveryProvider;
use Illuminate\Support\ServiceProvider;
use LogicException;

class DomainServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(HealthService::class, SystemHealthService::class);
        $this->app->bind(OtpDeliveryProvider::class, function (): OtpDeliveryProvider {
            $driver = config('otp.delivery');

            if ($driver === 'local_mail') {
                return $this->localMailOtpDeliveryProvider();
            }

            if ($driver === 'whatsapp_gateway') {
                return $this->whatsappGatewayOtpDeliveryProvider();
            }

            if ($driver === 'local_mail_with_whatsapp') {
                return new ChannelRoutingOtpDeliveryProvider([
                    'sms' => $this->localMailOtpDeliveryProvider(),
                    'whatsapp' => $this->whatsappGatewayOtpDeliveryProvider(),
                ]);
            }

            throw new LogicException(
                "OTP delivery driver [{$driver}] is not configured for environment [".config('app.env').'].',
            );
        });
    }

    private function localMailOtpDeliveryProvider(): LocalMailOtpDeliveryProvider
    {
        if (! in_array(config('app.env'), ['local', 'testing'], true)) {
            throw new LogicException(
                'Local mail OTP delivery is not configured for environment ['.config('app.env').'].',
            );
        }

        $recipient = config('otp.local_mail_to');

        if (! is_string($recipient) || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            throw new LogicException('A valid OTP_LOCAL_MAIL_TO address is required for local OTP delivery.');
        }

        return new LocalMailOtpDeliveryProvider($recipient);
    }

    private function whatsappGatewayOtpDeliveryProvider(): WhatsappGatewayOtpDeliveryProvider
    {
        $gatewayUrl = config('otp.whatsapp_gateway.url');
        $endpoint = config('otp.whatsapp_gateway.endpoint');
        $internalToken = config('otp.whatsapp_gateway.internal_token');
        $timeoutSeconds = config('otp.whatsapp_gateway.timeout_seconds');

        if (! is_string($gatewayUrl) || filter_var($gatewayUrl, FILTER_VALIDATE_URL) === false) {
            throw new LogicException('A valid WHATSAPP_GATEWAY_URL is required for WhatsApp OTP delivery.');
        }

        if (! is_string($endpoint) || ! str_starts_with($endpoint, '/')) {
            throw new LogicException('WHATSAPP_GATEWAY_ENDPOINT must be an absolute URL path.');
        }

        if (! is_string($internalToken) || trim($internalToken) === '') {
            throw new LogicException('WHATSAPP_INTERNAL_TOKEN is required for WhatsApp OTP delivery.');
        }

        if (! is_int($timeoutSeconds) || $timeoutSeconds < 1 || $timeoutSeconds > 30) {
            throw new LogicException('WHATSAPP_GATEWAY_TIMEOUT_SECONDS must be between 1 and 30.');
        }

        return new WhatsappGatewayOtpDeliveryProvider(
            $gatewayUrl,
            $endpoint,
            $internalToken,
            $timeoutSeconds,
        );
    }
}
