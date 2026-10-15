<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ApiErrorEnvelopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withServerVariables([
            'REMOTE_ADDR' => '198.51.100.'.random_int(1, 254),
        ]);
    }

    public function test_unauthenticated_api_requests_use_the_error_envelope(): void
    {
        $this->assertErrorEnvelope(
            $this->getJson('/api/v1/dairies'),
            401,
            'Unauthenticated.',
        )->assertJsonPath('errors.token.0', 'The provided token is invalid or expired.');

        $this->assertErrorEnvelope(
            $this->withToken('invalid-token')->getJson('/api/v1/dairies'),
            401,
            'Unauthenticated.',
        )->assertJsonPath('errors.token.0', 'The provided token is invalid or expired.');
    }

    public function test_forbidden_api_requests_use_the_error_envelope(): void
    {
        $this->actingAs(User::factory()->create(['status' => 'suspended']), 'sanctum');

        $this->assertErrorEnvelope(
            $this->getJson('/api/v1/dairies'),
            403,
            'Forbidden.',
        )->assertJsonPath('errors.access.0', 'You are not authorized to perform this action.');
    }

    public function test_not_found_api_requests_use_the_error_envelope(): void
    {
        $this->assertErrorEnvelope(
            $this->getJson('/api/v1/unknown-resource'),
            404,
            'Not found.',
        )->assertJsonPath('errors.resource.0', 'The requested resource was not found.');
    }

    public function test_validation_api_errors_use_the_error_envelope(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum');

        $this->assertErrorEnvelope(
            $this->postJson('/api/v1/dairies', []),
            422,
            'Validation failed.',
        )->assertJsonStructure(['errors' => ['name', 'location', 'client_uuid']]);
    }

    public function test_conflict_api_errors_use_the_error_envelope(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');
        $payload = [
            'name' => 'Green Valley Dairy',
            'location' => [
                'city' => 'Bhopal',
                'state' => 'Madhya Pradesh',
                'country' => 'IN',
                'postal_code' => '462001',
            ],
            'client_uuid' => '212f48a4-8cba-48e4-b0a6-5a1c9f4bd3d0',
        ];

        $this->postJson('/api/v1/dairies', $payload)->assertCreated();

        $this->assertErrorEnvelope(
            $this->postJson('/api/v1/dairies', [...$payload, 'name' => 'Changed Dairy']),
            409,
            'The request conflicts with a previous use of this client UUID.',
        )->assertJsonPath('errors.client_uuid.0', 'Use a new client UUID for a different request.');
    }

    public function test_rate_limited_api_errors_use_the_error_envelope_and_retry_metadata(): void
    {
        Mail::fake();
        $payload = ['phone' => '+919'.random_int(100000000, 999999999)];

        $this->postJson('/api/v1/auth/otp/send', $payload)->assertOk();

        $response = $this->assertErrorEnvelope(
            $this->postJson('/api/v1/auth/otp/send', $payload),
            429,
            'Too many requests.',
        )->assertJsonPath('errors.rate_limit.0', 'Too many requests.');

        $this->assertSame(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());
        $retryAfter = (int) $response->headers->get('Retry-After');
        $this->assertGreaterThan(0, $retryAfter);
        $this->assertLessThanOrEqual(60, $retryAfter);
        $this->assertSame($retryAfter, $response->json('meta.retry_after_seconds'));
    }

    public function test_unexpected_api_errors_are_sanitized(): void
    {
        Route::get('/api/v1/test-server-error', static function (): never {
            throw new RuntimeException('SQLSTATE secret OTP token hash');
        });

        $response = $this->getJson('/api/v1/test-server-error');

        $this->assertErrorEnvelope($response, 500, 'An unexpected error occurred.');
        $response->assertDontSee('SQLSTATE')
            ->assertDontSee('secret')
            ->assertDontSee('OTP')
            ->assertDontSee('token hash');
    }

    public function test_non_api_validation_keeps_laravels_redirect_behavior(): void
    {
        Route::get('/test-validation', static function (): never {
            throw ValidationException::withMessages(['name' => 'The name field is required.']);
        })->middleware('web');

        $response = $this->get('/test-validation');

        $response->assertRedirect()
            ->assertSessionHasErrors('name');
        $this->assertStringNotContainsString(
            'application/json',
            (string) $response->headers->get('Content-Type'),
        );
    }

    private function assertErrorEnvelope(TestResponse $response, int $status, string $message): TestResponse
    {
        $response->assertStatus($status)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', $message)
            ->assertJsonStructure(['success', 'message', 'errors', 'meta']);

        $body = json_decode($response->getContent());
        $this->assertInstanceOf(\stdClass::class, $body->errors);
        $this->assertInstanceOf(\stdClass::class, $body->meta);

        return $response;
    }
}
