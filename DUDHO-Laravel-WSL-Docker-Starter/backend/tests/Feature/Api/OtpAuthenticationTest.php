<?php

namespace Tests\Feature\Api;

use App\Mail\OtpCodeMail;
use App\Models\OtpChallenge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class OtpAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        static $sourceAddress = 1;
        $sourceAddress++;
        config(['cache.default' => 'array']);

        $this->withServerVariables([
            'REMOTE_ADDR' => '192.0.2.'.$sourceAddress,
        ]);
    }

    public function test_send_endpoint_returns_the_same_200_envelope_without_revealing_account_or_code(): void
    {
        Mail::fake();
        $this->freezeTime();
        User::factory()->create(['mobile' => '9876543210']);

        $registeredResponse = $this->postJson('/api/v1/auth/otp/send', [
            'phone' => '+919876543210',
            'channel' => 'sms',
            'locale' => 'en',
        ]);
        $unknownResponse = $this->postJson('/api/v1/auth/otp/send', [
            'phone' => '+919876543211',
            'channel' => 'sms',
            'locale' => 'en',
        ]);

        $registeredResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.delivery_channel', 'sms')
            ->assertJsonPath('data.retry_after_seconds', 60)
            ->assertJsonMissingPath('data.code');
        $unknownResponse->assertOk();
        $this->assertSame($registeredResponse->json(), $unknownResponse->json());
        $this->assertDatabaseCount('otp_challenges', 2);

        $codesByPhoneSuffix = [];
        Mail::assertSent(OtpCodeMail::class, function (OtpCodeMail $mail) use (&$codesByPhoneSuffix): bool {
            $codesByPhoneSuffix[substr($mail->maskedPhone, -4)] = $mail->code;

            return true;
        });

        foreach (OtpChallenge::query()->get() as $challenge) {
            $code = $codesByPhoneSuffix[substr($challenge->mobile, -4)];
            $this->assertTrue(Hash::check($code, $challenge->otp_hash));
            $this->assertNotSame($code, $challenge->otp_hash);
        }

        Mail::assertSentTimes(OtpCodeMail::class, 2);
    }

    public function test_send_endpoint_returns_422_for_a_non_indian_mobile_number(): void
    {
        $this->postJson('/api/v1/auth/otp/send', [
            'phone' => '+12025550123',
        ])->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonStructure(['errors' => ['phone']]);

        $this->assertDatabaseCount('otp_challenges', 0);
    }

    public function test_send_endpoint_routes_a_whatsapp_request_to_the_configured_gateway(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://gateway.example/api/send-otp' => Http::response(['otp' => '827401']),
        ]);
        config([
            'otp.delivery' => 'local_mail_with_whatsapp',
            'otp.whatsapp_gateway.url' => 'https://gateway.example',
            'otp.whatsapp_gateway.endpoint' => '/api/send-otp',
            'otp.whatsapp_gateway.internal_token' => 'test-internal-token',
            'otp.whatsapp_gateway.timeout_seconds' => 10,
        ]);

        $this->postJson('/api/v1/auth/otp/send', [
            'phone' => '+919876543210',
            'channel' => 'whatsapp',
            'locale' => 'en',
        ])->assertOk()
            ->assertJsonPath('data.delivery_channel', 'whatsapp');

        Http::assertSentCount(1);
        $this->assertDatabaseHas('otp_challenges', [
            'mobile' => '9876543210',
            'channel' => 'whatsapp',
        ]);
        $this->assertTrue(Hash::check(
            '827401',
            OtpChallenge::query()->where('mobile', '9876543210')->value('otp_hash'),
        ));
    }

    public function test_send_endpoint_returns_429_and_retry_after_during_resend_cooldown(): void
    {
        Mail::fake();
        $this->postJson('/api/v1/auth/otp/send', ['phone' => '+919876543212'])
            ->assertOk();

        $this->postJson('/api/v1/auth/otp/send', ['phone' => '+919876543212'])
            ->assertTooManyRequests()
            ->assertHeader('Retry-After', '60')
            ->assertJsonPath('meta.retry_after_seconds', 60);

        Mail::assertSentTimes(OtpCodeMail::class, 1);
        $this->assertDatabaseCount('otp_challenges', 1);
    }

    public function test_send_endpoint_returns_429_after_the_phone_hourly_limit(): void
    {
        Mail::fake();
        $phone = '+919876543213';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            if ($attempt > 0) {
                $this->travel(60)->seconds();
            }

            $this->postJson('/api/v1/auth/otp/send', ['phone' => $phone])
                ->assertOk();
        }

        $this->travel(60)->seconds();
        $this->postJson('/api/v1/auth/otp/send', ['phone' => $phone])
            ->assertTooManyRequests();

        Mail::assertSentTimes(OtpCodeMail::class, 5);
        $this->assertSame(4, OtpChallenge::query()->value('resend_count'));
    }

    public function test_send_endpoint_returns_429_after_the_source_hourly_limit(): void
    {
        Mail::fake();

        for ($attempt = 0; $attempt < 30; $attempt++) {
            $mobile = '987654'.str_pad((string) (3000 + $attempt), 4, '0', STR_PAD_LEFT);

            $this->postJson('/api/v1/auth/otp/send', ['phone' => '+91'.$mobile])
                ->assertOk();
        }

        $this->postJson('/api/v1/auth/otp/send', ['phone' => '+919876543030'])
            ->assertTooManyRequests();

        Mail::assertSentTimes(OtpCodeMail::class, 30);
        $this->assertDatabaseCount('otp_challenges', 30);
    }

    public function test_verify_endpoint_creates_a_verified_identity_and_a_hashed_sanctum_token(): void
    {
        Mail::fake();
        $code = $this->sendOtpAndReadCode('+919876543214', 'hi');
        $clientUuid = (string) Str::uuid();

        $response = $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '+919876543214',
            'code' => $code,
            'device_name' => 'Motorola Edge',
            'client_uuid' => $clientUuid,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.phone', '+919876543214')
            ->assertJsonPath('data.user.preferred_language', 'hi')
            ->assertJsonMissingPath('data.user.role');

        $user = User::query()->where('mobile', '9876543214')->firstOrFail();
        $this->assertNotNull($user->mobile_verified_at);
        $this->assertDatabaseCount('dairy_users', 0);
        $this->assertDatabaseHas('otp_challenges', [
            'mobile' => '9876543214',
            'verified_at' => $user->mobile_verified_at,
            'attempt_count' => 1,
        ]);

        $plainTextToken = $response->json('data.access_token');
        [$tokenId, $secret] = explode('|', $plainTextToken, 2);
        $storedToken = DB::table('personal_access_tokens')->where('id', $tokenId)->first();
        $this->assertSame(hash('sha256', $secret), $storedToken->token);

        $storedResponse = DB::table('otp_login_requests')->value('response_payload');
        $this->assertStringNotContainsString($plainTextToken, $storedResponse);
    }

    public function test_verify_endpoint_replays_the_same_token_for_an_idempotent_request_and_conflicts_on_changed_body(): void
    {
        Mail::fake();
        $code = $this->sendOtpAndReadCode('+919876543215');
        $payload = [
            'phone' => '+919876543215',
            'code' => $code,
            'device_name' => 'Motorola Edge',
            'client_uuid' => (string) Str::uuid(),
        ];

        $firstResponse = $this->postJson('/api/v1/auth/otp/verify', $payload);
        $replayedResponse = $this->postJson('/api/v1/auth/otp/verify', $payload);
        $conflictingResponse = $this->postJson('/api/v1/auth/otp/verify', [
            ...$payload,
            'device_name' => 'Different device',
        ]);

        $firstResponse->assertOk();
        $replayedResponse->assertOk();
        $this->assertSame($firstResponse->json(), $replayedResponse->json());
        $conflictingResponse->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['client_uuid']]);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_verify_endpoint_returns_422_and_persists_failed_attempts(): void
    {
        Mail::fake();
        $code = $this->sendOtpAndReadCode('+919876543216');
        $wrongCode = $code === '000000' ? '000001' : '000000';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/otp/verify', [
                'phone' => '+919876543216',
                'code' => $wrongCode,
                'client_uuid' => (string) Str::uuid(),
            ])->assertUnprocessable()
                ->assertJsonPath('errors.code.0', 'The verification code is invalid or expired.');
        }

        $this->assertDatabaseHas('otp_challenges', [
            'mobile' => '9876543216',
            'attempt_count' => 5,
            'verified_at' => null,
        ]);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_verify_endpoint_returns_429_after_the_phone_attempt_limit(): void
    {
        Mail::fake();
        $phone = '+919876543219';
        $validCode = $this->sendOtpAndReadCode($phone);
        $wrongCode = $validCode === '000000' ? '000001' : '000000';

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postJson('/api/v1/auth/otp/verify', [
                'phone' => $phone,
                'code' => $wrongCode,
                'client_uuid' => (string) Str::uuid(),
            ])->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => $phone,
            'code' => $wrongCode,
            'client_uuid' => (string) Str::uuid(),
        ])->assertTooManyRequests();
    }

    public function test_verify_endpoint_returns_429_after_the_source_attempt_limit(): void
    {
        Mail::fake();
        $lastPhone = '';
        $wrongCode = '000000';

        for ($phoneIndex = 0; $phoneIndex < 6; $phoneIndex++) {
            $phone = '+919876543'.str_pad((string) (300 + $phoneIndex), 3, '0', STR_PAD_LEFT);
            $validCode = $this->sendOtpAndReadCode($phone);
            $wrongCode = $validCode === '000000' ? '000001' : '000000';
            $lastPhone = $phone;

            for ($attempt = 0; $attempt < 10; $attempt++) {
                $this->postJson('/api/v1/auth/otp/verify', [
                    'phone' => $phone,
                    'code' => $wrongCode,
                    'client_uuid' => (string) Str::uuid(),
                ])->assertUnprocessable();
            }
        }

        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => $lastPhone,
            'code' => $wrongCode,
            'client_uuid' => (string) Str::uuid(),
        ])->assertTooManyRequests();
    }

    public function test_verify_endpoint_returns_422_for_an_expired_challenge(): void
    {
        Mail::fake();
        $code = $this->sendOtpAndReadCode('+919876543217');
        $this->travel(6)->minutes();

        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '+919876543217',
            'code' => $code,
            'client_uuid' => (string) Str::uuid(),
        ])->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'The verification code is invalid or expired.');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_verify_endpoint_returns_422_when_required_idempotency_key_is_missing(): void
    {
        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '+919876543218',
            'code' => '123456',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonStructure(['errors' => ['client_uuid']]);
    }

    private function sendOtpAndReadCode(string $phone, string $locale = 'en'): string
    {
        $this->postJson('/api/v1/auth/otp/send', [
            'phone' => $phone,
            'locale' => $locale,
        ])->assertOk();
        $code = null;

        Mail::assertSent(OtpCodeMail::class, function (OtpCodeMail $mail) use (&$code, $phone): bool {
            if ($mail->hasTo('otp-preview@dudho.test')) {
                $code = $mail->code;
            }

            return $mail->hasTo('otp-preview@dudho.test')
                && str_ends_with($mail->maskedPhone, substr($phone, -4));
        });

        $this->assertNotNull($code);

        return $code;
    }
}
