<?php

namespace Tests\Feature\Contracts;

use App\Contracts\OtpDeliveryProvider;
use App\Mail\OtpCodeMail;
use App\Services\WhatsappGatewayOtpDeliveryProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use LogicException;
use RuntimeException;
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

    public function test_whatsapp_gateway_sends_the_otp_with_the_internal_token(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://gateway.example/api/send-otp' => Http::response(['otp' => '827401']),
        ]);
        $provider = new WhatsappGatewayOtpDeliveryProvider(
            'https://gateway.example',
            '/api/send-otp',
            'test-internal-token',
            10,
        );

        $otp = $provider->deliver(
            '+919876543210',
            '381204',
            now()->addMinutes(5),
            'whatsapp',
        );

        $this->assertSame('827401', $otp);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://gateway.example/api/send-otp'
                && $request->method() === 'POST'
                && $request->hasHeader('X-Internal-Token', 'test-internal-token')
                && $request['mobile'] === '+919876543210'
                && ! isset($request['otp']);
        });
    }

    public function test_whatsapp_gateway_rejects_a_failed_delivery_without_exposing_the_response(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://gateway.example/api/send-otp' => Http::response([
                'error' => 'sensitive gateway response',
            ], 401),
        ]);
        $provider = new WhatsappGatewayOtpDeliveryProvider(
            'https://gateway.example',
            '/api/send-otp',
            'test-internal-token',
            10,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('WhatsApp OTP delivery was rejected by the gateway.');

        $provider->deliver(
            '+919876543210',
            '381204',
            now()->addMinutes(5),
            'whatsapp',
        );
    }

    public function test_whatsapp_gateway_rejects_a_success_response_without_a_six_digit_otp(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://gateway.example/api/send-otp' => Http::response(['otp' => 'invalid']),
        ]);
        $provider = new WhatsappGatewayOtpDeliveryProvider(
            'https://gateway.example',
            '/api/send-otp',
            'test-internal-token',
            10,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('WhatsApp OTP gateway returned an invalid response.');

        $provider->deliver(
            '+919876543210',
            '381204',
            now()->addMinutes(5),
            'whatsapp',
        );
    }

    public function test_hybrid_delivery_routes_whatsapp_to_the_gateway(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://gateway.example/api/send-otp' => Http::response(['otp' => '827401']),
        ]);
        config([
            'app.env' => 'testing',
            'otp.delivery' => 'local_mail_with_whatsapp',
            'otp.local_mail_to' => 'otp-preview@dudho.test',
            'otp.whatsapp_gateway.url' => 'https://gateway.example',
            'otp.whatsapp_gateway.endpoint' => '/api/send-otp',
            'otp.whatsapp_gateway.internal_token' => 'test-internal-token',
            'otp.whatsapp_gateway.timeout_seconds' => 10,
        ]);

        $this->app->make(OtpDeliveryProvider::class)->deliver(
            '+919876543210',
            '381204',
            now()->addMinutes(5),
            'whatsapp',
        );

        Http::assertSentCount(1);
    }
}
