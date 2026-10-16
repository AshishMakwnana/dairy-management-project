<?php

namespace App\Services;

use App\Contracts\OtpDeliveryProvider;
use App\Exceptions\OtpIdempotencyConflictException;
use App\Exceptions\OtpRateLimitException;
use App\Models\OtpChallenge;
use App\Models\OtpLoginRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OtpAuthenticationService
{
    public function __construct(private readonly OtpDeliveryProvider $deliveryProvider) {}

    /**
     * @param  array{phone: string, channel?: string, locale?: string}  $data
     * @return array{delivery_channel: string, expires_at: string, retry_after_seconds: int}
     */
    public function send(array $data, Request $request): array
    {
        $phone = $data['phone'];
        $mobile = substr($phone, 3);
        $channel = $data['channel'] ?? 'sms';
        $locale = $data['locale'] ?? 'en';
        $this->hitLimits('send', $phone, $request);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $cooldownSeconds = (int) config('otp.resend_cooldown_seconds');
        $expiresAt = now()->addMinutes((int) config('otp.expires_minutes'));

        return DB::transaction(function () use (
            $request,
            $phone,
            $mobile,
            $channel,
            $locale,
            $code,
            $cooldownSeconds,
            $expiresAt,
        ): array {
            $challenge = OtpChallenge::query()
                ->where('mobile_country_code', '+91')
                ->where('mobile', $mobile)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            $canReuseChallenge = (
                $challenge !== null
                && $challenge->verified_at === null
                && $challenge->expires_at->isFuture()
            );

            if ($canReuseChallenge) {
                $retryAfterSeconds = $challenge->last_sent_at->getTimestamp()
                    + $cooldownSeconds
                    - now()->getTimestamp();

                if ($retryAfterSeconds > 0) {
                    throw new OtpRateLimitException($retryAfterSeconds);
                }
            }

            $deliveredCode = $this->deliveryProvider->deliver(
                $phone,
                $code,
                $expiresAt,
                $channel,
            );

            if ($canReuseChallenge) {
                $challenge->fill([
                    'channel' => $channel,
                    'locale' => $locale,
                    'otp_hash' => Hash::make($deliveredCode),
                    'expires_at' => $expiresAt,
                    'attempt_count' => 0,
                    'resend_count' => $challenge->resend_count + 1,
                    'last_sent_at' => now(),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ])->save();
            } else {
                $challenge = OtpChallenge::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'mobile_country_code' => '+91',
                    'mobile' => $mobile,
                    'channel' => $channel,
                    'locale' => $locale,
                    'otp_hash' => Hash::make($deliveredCode),
                    'expires_at' => $expiresAt,
                    'attempt_count' => 0,
                    'resend_count' => 0,
                    'last_sent_at' => now(),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            }

            return [
                'delivery_channel' => $channel,
                'expires_at' => $expiresAt->toIso8601String(),
                'retry_after_seconds' => $cooldownSeconds,
            ];
        });
    }

    /**
     * @param  array{phone: string, code: string, device_name?: string|null, client_uuid: string}  $data
     * @return array{
     *     access_token: string,
     *     token_type: string,
     *     expires_at: string|null,
     *     user: array{id: string, phone: string, full_name: string|null, preferred_language: string, status: string}
     * }
     */
    public function verify(array $data, Request $request): array
    {
        $phone = $data['phone'];
        $mobile = substr($phone, 3);
        $requestHash = $this->requestHash($data);

        $storedResponse = $this->findIdempotentResponse($data['client_uuid'], $requestHash);

        if ($storedResponse !== null) {
            return $storedResponse;
        }

        $this->hitLimits('verify', $phone, $request);

        $result = DB::transaction(function () use ($data, $phone, $mobile, $requestHash): array {
            $challenge = OtpChallenge::query()
                ->where('mobile_country_code', '+91')
                ->where('mobile', $mobile)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            $storedResponse = $this->findIdempotentResponse($data['client_uuid'], $requestHash);

            if ($storedResponse !== null) {
                return ['response' => $storedResponse];
            }

            if (
                $challenge === null
                || $challenge->verified_at !== null
                || $challenge->expires_at->isPast()
                || $challenge->attempt_count >= (int) config('otp.max_challenge_attempts')
            ) {
                return ['invalid_code' => true];
            }

            $challenge->increment('attempt_count');

            if (! Hash::check($data['code'], $challenge->otp_hash)) {
                return ['invalid_code' => true];
            }

            $user = User::query()
                ->where('mobile_country_code', '+91')
                ->where('mobile', $mobile)
                ->first();

            if ($user !== null && $user->status !== 'active') {
                return ['invalid_code' => true];
            }

            $challenge->forceFill(['verified_at' => now()])->save();

            if ($user === null) {
                $user = User::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'mobile_country_code' => '+91',
                    'mobile' => $mobile,
                    'mobile_verified_at' => now(),
                    'preferred_language' => $challenge->locale,
                    'status' => 'active',
                    'last_login_at' => now(),
                ]);
            } else {
                $user->forceFill([
                    'mobile_verified_at' => $user->mobile_verified_at ?? now(),
                    'preferred_language' => $challenge->locale,
                    'last_login_at' => now(),
                ])->save();
            }

            $expirationMinutes = config('sanctum.expiration');
            $expiresAt = is_numeric($expirationMinutes)
                ? now()->addMinutes((int) $expirationMinutes)
                : null;
            $token = $user->createToken(
                $data['device_name'] ?: 'mobile-app',
                ['*'],
                $expiresAt,
            );

            $response = [
                'access_token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $expiresAt?->toIso8601String(),
                'user' => [
                    'id' => $user->uuid,
                    'phone' => $phone,
                    'full_name' => $user->name,
                    'preferred_language' => $user->preferred_language,
                    'status' => $user->status,
                ],
            ];

            OtpLoginRequest::query()->create([
                'client_uuid' => $data['client_uuid'],
                'request_hash' => $requestHash,
                'user_id' => $user->getKey(),
                'response_payload' => $response,
            ]);

            return ['response' => $response];
        });

        if (isset($result['invalid_code'])) {
            $this->invalidCode();
        }

        return $result['response'];
    }

    private function hitLimits(string $action, string $phone, Request $request): void
    {
        $limits = [
            'phone' => config("otp.limits.{$action}_phone"),
            'source' => config("otp.limits.{$action}_source"),
        ];
        $keys = [
            'phone' => 'otp:'.$action.':phone:'.hash('sha256', $phone),
            'source' => 'otp:'.$action.':source:'.hash('sha256', $request->ip() ?? 'unknown'),
        ];

        foreach ($limits as $scope => $limit) {
            $key = $keys[$scope];

            if (RateLimiter::tooManyAttempts($key, $limit['attempts'])) {
                throw new OtpRateLimitException(RateLimiter::availableIn($key));
            }
        }

        foreach ($limits as $scope => $limit) {
            RateLimiter::hit($keys[$scope], $limit['decay_seconds']);
        }
    }

    private function requestHash(array $data): string
    {
        $canonicalRequest = [
            'phone' => $data['phone'],
            'code' => $data['code'],
            'device_name' => $data['device_name'] ?? null,
        ];

        return hash_hmac(
            'sha256',
            json_encode($canonicalRequest, JSON_THROW_ON_ERROR),
            (string) config('app.key'),
        );
    }

    /** @return array<string, mixed>|null */
    private function findIdempotentResponse(string $clientUuid, string $requestHash): ?array
    {
        $loginRequest = OtpLoginRequest::query()
            ->where('client_uuid', $clientUuid)
            ->first();

        if ($loginRequest === null) {
            return null;
        }

        if (! hash_equals($loginRequest->request_hash, $requestHash)) {
            throw new OtpIdempotencyConflictException;
        }

        return $loginRequest->response_payload;
    }

    private function invalidCode(): never
    {
        throw ValidationException::withMessages([
            'code' => ['The verification code is invalid or expired.'],
        ]);
    }
}
