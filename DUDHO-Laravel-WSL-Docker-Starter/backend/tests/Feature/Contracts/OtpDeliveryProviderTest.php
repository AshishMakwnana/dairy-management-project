<?php

namespace Tests\Feature\Contracts;

use App\Contracts\OtpDeliveryProvider;
use App\Mail\OtpCodeMail;
use Illuminate\Support\Facades\Mail;
use LogicException;
use Tests\TestCase;

class OtpDeliveryProviderTest extends TestCase
{
    public function test_local_mail_delivery_sends_the_code_only_to_the_configured_development_inbox(): void
    {
        Mail::fake();
        config([
            'app.env' => 'local',
            'otp.delivery' => 'local_mail',
            'otp.local_mail_to' => 'otp-preview@dudho.test',
        ]);

        $provider = $this->app->make(OtpDeliveryProvider::class);
        $provider->deliver(
            '+919876543210',
            '381204',
            now()->addMinutes(5),
            'sms',
        );

        Mail::assertSent(OtpCodeMail::class, function (OtpCodeMail $mail): bool {
            $rendered = $mail->render();

            return $mail->hasTo('otp-preview@dudho.test')
                && str_contains($rendered, '381204')
                && str_contains($rendered, '***3210')
                && ! str_contains($rendered, '+919876543210')
                && str_contains($rendered, 'SMS (delivered to the local development inbox)');
        });
    }

    public function test_local_mail_delivery_is_rejected_outside_local_and_testing_environments(): void
    {
        config([
            'app.env' => 'production',
            'otp.delivery' => 'local_mail',
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('is not configured for environment [production]');

        $this->app->make(OtpDeliveryProvider::class);
    }

    public function test_an_unconfigured_production_provider_is_rejected_explicitly(): void
    {
        config([
            'app.env' => 'production',
            'otp.delivery' => 'sms',
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('OTP delivery driver [sms] is not configured');

        $this->app->make(OtpDeliveryProvider::class);
    }
}
