<?php

namespace App\Providers;

use App\Contracts\HealthService;
use App\Contracts\OtpDeliveryProvider;
use App\Services\LocalMailOtpDeliveryProvider;
use App\Services\SystemHealthService;
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

            if ($driver !== 'local_mail' || ! in_array(config('app.env'), ['local', 'testing'], true)) {
                throw new LogicException(
                    "OTP delivery driver [{$driver}] is not configured for environment [".config('app.env').'].',
                );
            }

            $recipient = config('otp.local_mail_to');

            if (! is_string($recipient) || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
                throw new LogicException('A valid OTP_LOCAL_MAIL_TO address is required for local OTP delivery.');
            }

            return new LocalMailOtpDeliveryProvider($recipient);
        });
    }
}
