<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SanctumAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('auth:sanctum')->get(
            '/api/v1/testing/authenticated-user',
            static function (Request $request): JsonResponse {
                return response()->json([
                    'user_id' => $request->user()->getAuthIdentifier(),
                ]);
            },
        );
    }

    public function test_a_valid_bearer_token_authenticates_its_user(): void
    {
        $user = User::factory()->create();
        $plainTextToken = $user->createToken('android-device')->plainTextToken;

        $this->withToken($plainTextToken)
            ->getJson('/api/v1/testing/authenticated-user')
            ->assertOk()
            ->assertJsonPath('user_id', $user->getKey());

        $this->assertDatabaseMissing('personal_access_tokens', [
            'token' => $plainTextToken,
        ]);
    }

    public function test_an_invalid_bearer_token_is_rejected(): void
    {
        $this->withToken('invalid-token')
            ->getJson('/api/v1/testing/authenticated-user')
            ->assertUnauthorized();
    }
}
