<?php

namespace Tests\Feature\Api;

use App\Models\Dairy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_and_logout_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'message', 'errors', 'meta']);

        $this->patchJson('/api/v1/auth/me', ['full_name' => 'Anita Verma'])
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'message', 'errors', 'meta']);

        $this->postJson('/api/v1/auth/logout')
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'message', 'errors', 'meta']);
    }

    public function test_profile_retrieval_exposes_only_contracted_public_fields(): void
    {
        $user = User::factory()->create([
            'name' => 'Anita Verma',
            'preferred_language' => 'hi',
        ]);
        $this->authenticate($user);

        $response = $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Profile retrieved successfully.')
            ->assertJsonPath('data.id', $user->uuid)
            ->assertJsonPath('data.full_name', 'Anita Verma')
            ->assertJsonPath('data.phone', '+91'.$user->mobile)
            ->assertJsonPath('data.preferred_language', 'hi')
            ->assertJsonPath('data.default_dairy_id', null)
            ->assertJsonPath('meta', []);

        $this->assertSame(
            ['id', 'full_name', 'phone', 'preferred_language', 'default_dairy_id'],
            array_keys($response->json('data')),
        );
    }

    public function test_profile_can_update_allowed_fields_and_active_default_dairy(): void
    {
        $user = User::factory()->create();
        $dairy = $this->createDairyWithMembership($user);
        $this->authenticate($user);

        $this->patchJson('/api/v1/auth/me', [
            'full_name' => '  Anita Verma  ',
            'preferred_language' => 'hi',
            'default_dairy_id' => $dairy->uuid,
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Profile updated successfully.')
            ->assertJsonPath('data.full_name', 'Anita Verma')
            ->assertJsonPath('data.preferred_language', 'hi')
            ->assertJsonPath('data.default_dairy_id', $dairy->uuid)
            ->assertJsonPath('meta', []);

        $this->assertDatabaseHas('users', [
            'id' => $user->getKey(),
            'name' => 'Anita Verma',
            'preferred_language' => 'hi',
            'default_dairy_id' => $dairy->getKey(),
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $user->getKey(),
            'uuid' => $user->uuid,
            'mobile' => $user->mobile,
            'status' => 'active',
        ]);
    }

    public function test_unsupported_and_prohibited_profile_fields_are_rejected(): void
    {
        $user = User::factory()->create();
        $this->authenticate($user);
        $originalUuid = $user->uuid;
        $originalPhone = $user->mobile;

        $this->patchJson('/api/v1/auth/me', [
            'full_name' => 'Changed Name',
            'phone' => '+919999999999',
            'uuid' => (string) Str::uuid(),
            'status' => 'suspended',
            'role' => 'admin',
            'membership_id' => 123,
            'unsupported' => true,
        ])->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['phone', 'uuid', 'status', 'role', 'membership_id', 'unsupported'],
                'meta',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->getKey(),
            'uuid' => $originalUuid,
            'mobile' => $originalPhone,
            'name' => $user->name,
            'status' => 'active',
        ]);
    }

    public function test_profile_rejects_invalid_language_and_empty_update(): void
    {
        $this->authenticate(User::factory()->create());

        $this->patchJson('/api/v1/auth/me', ['preferred_language' => 'fr'])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonStructure(['errors' => ['preferred_language']]);

        $this->patchJson('/api/v1/auth/me', [])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonPath('errors.profile.0', 'At least one supported profile field must be provided.');
    }

    public function test_default_dairy_requires_an_existing_dairy_with_active_membership(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $inactiveDairy = $this->createDairyWithMembership($user, 'suspended');
        $otherUserDairy = $this->createDairyWithMembership($otherUser);
        $this->authenticate($user);

        foreach ([
            'missing' => (string) Str::uuid(),
            'inactive membership' => $inactiveDairy->uuid,
            'another user dairy' => $otherUserDairy->uuid,
        ] as $case => $dairyUuid) {
            $this->patchJson('/api/v1/auth/me', ['default_dairy_id' => $dairyUuid])
                ->assertUnprocessable()
                ->assertJsonPath('success', false)
                ->assertJsonPath('message', 'Validation failed.')
                ->assertJsonPath(
                    'errors.default_dairy_id.0',
                    'The selected dairy must have an active membership.',
                );
        }

        $this->assertDatabaseHas('users', [
            'id' => $user->getKey(),
            'default_dairy_id' => null,
        ]);
    }

    public function test_default_dairy_can_be_cleared(): void
    {
        $user = User::factory()->create();
        $dairy = $this->createDairyWithMembership($user);
        $user->update(['default_dairy_id' => $dairy->getKey()]);
        $this->authenticate($user);

        $this->patchJson('/api/v1/auth/me', ['default_dairy_id' => null])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.default_dairy_id', null)
            ->assertJsonPath('meta', []);

        $this->assertDatabaseHas('users', [
            'id' => $user->getKey(),
            'default_dairy_id' => null,
        ]);
    }

    public function test_default_dairy_preference_does_not_grant_membership_access(): void
    {
        $user = User::factory()->create();
        $anotherUser = User::factory()->create();
        $dairy = $this->createDairyWithMembership($anotherUser);
        $user->update(['default_dairy_id' => $dairy->getKey()]);
        $this->authenticate($user);

        $this->getJson('/api/v1/dairies')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.total', 0);
    }

    public function test_repeating_the_same_profile_update_is_safe(): void
    {
        $user = User::factory()->create();
        $dairy = $this->createDairyWithMembership($user);
        $this->authenticate($user);
        $payload = [
            'full_name' => 'Anita Verma',
            'preferred_language' => 'hi',
            'default_dairy_id' => $dairy->uuid,
        ];

        $first = $this->patchJson('/api/v1/auth/me', $payload)->assertOk();
        $second = $this->patchJson('/api/v1/auth/me', $payload)->assertOk();

        $this->assertSame($first->json(), $second->json());
        $this->assertDatabaseCount('dairy_users', 1);
    }

    public function test_logout_revokes_only_the_current_token_and_preserves_other_device_tokens(): void
    {
        $user = User::factory()->create();
        $currentToken = $user->createToken('current-device');
        $otherToken = $user->createToken('other-device');
        $currentTokenId = $currentToken->accessToken->getKey();
        $otherTokenId = $otherToken->accessToken->getKey();

        $response = $this->withToken($currentToken->plainTextToken)
            ->postJson('/api/v1/auth/logout');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Signed out successfully.')
            ->assertJsonPath('data', null)
            ->assertJsonPath('meta', []);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $currentTokenId]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $otherTokenId]);

        Auth::forgetGuards();
        $this->withToken($currentToken->plainTextToken)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'message', 'errors', 'meta']);

        Auth::forgetGuards();
        $this->withToken($otherToken->plainTextToken)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->uuid);
    }

    private function authenticate(User $user): void
    {
        $this->withToken($user->createToken('profile-test')->plainTextToken);
    }

    private function createDairyWithMembership(User $user, string $membershipStatus = 'active'): Dairy
    {
        $dairy = Dairy::query()->create([
            'uuid' => (string) Str::uuid(),
            'code' => 'DUDHO-'.Str::upper(Str::random(24)),
            'name' => 'Profile Test Dairy',
            'created_by_user_id' => $user->getKey(),
        ]);

        $dairy->memberships()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->getKey(),
            'role' => 'owner',
            'status' => $membershipStatus,
            'is_primary_owner' => true,
            'joined_at' => now(),
            'created_by_user_id' => $user->getKey(),
        ]);

        return $dairy;
    }
}
