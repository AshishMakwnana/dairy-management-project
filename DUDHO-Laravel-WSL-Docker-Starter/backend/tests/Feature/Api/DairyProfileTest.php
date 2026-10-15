<?php

namespace Tests\Feature\Api;

use App\Models\Dairy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DairyProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_detail_and_update_require_authentication(): void
    {
        $owner = User::factory()->create();
        $dairy = $this->createDairy($owner);

        $this->getJson("/api/v1/dairies/{$dairy->uuid}")->assertUnauthorized();
        $this->patchJson("/api/v1/dairies/{$dairy->uuid}", ['name' => 'Updated Dairy'])
            ->assertUnauthorized();
    }

    public function test_active_member_can_retrieve_dairy_detail_with_public_owner_fields(): void
    {
        $owner = User::factory()->create(['name' => 'Anita Verma']);
        $dairy = $this->createDairy($owner, 'owner');
        Sanctum::actingAs($owner);

        $this->getJson("/api/v1/dairies/{$dairy->uuid}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Dairy retrieved successfully.')
            ->assertJsonPath('data.id', $dairy->uuid)
            ->assertJsonPath('data.name', 'Green Valley Dairy')
            ->assertJsonPath('data.state', 'draft')
            ->assertJsonPath('data.onboarding_step', 'basic_profile')
            ->assertJsonPath('data.location.city', 'Bhopal')
            ->assertJsonPath('data.location.postal_code', '462001')
            ->assertJsonPath('data.owner.id', $owner->uuid)
            ->assertJsonPath('data.owner.full_name', 'Anita Verma')
            ->assertJsonPath('meta', [])
            ->assertJsonMissingPath('data.created_by_user_id');
    }

    public function test_access_without_membership_returns_not_found(): void
    {
        $dairy = $this->createDairy(User::factory()->create());
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/dairies/{$dairy->uuid}")
            ->assertNotFound()
            ->assertJsonPath('success', false);
        $this->patchJson("/api/v1/dairies/{$dairy->uuid}", ['name' => 'Updated Dairy'])
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_absent_dairy_returns_not_found(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $unknownUuid = (string) Str::uuid();

        $this->getJson("/api/v1/dairies/{$unknownUuid}")->assertNotFound();
        $this->patchJson("/api/v1/dairies/{$unknownUuid}", ['name' => 'Updated Dairy'])
            ->assertNotFound();
    }

    public function test_inactive_membership_and_inactive_dairy_return_not_found(): void
    {
        $owner = User::factory()->create();
        $inactiveMembershipDairy = $this->createDairy($owner, 'owner', 'suspended');
        $inactiveDairy = $this->createDairy($owner, 'owner', 'active', 'inactive');
        Sanctum::actingAs($owner);

        $this->getJson("/api/v1/dairies/{$inactiveMembershipDairy->uuid}")->assertNotFound();
        $this->patchJson("/api/v1/dairies/{$inactiveMembershipDairy->uuid}", ['name' => 'Updated'])
            ->assertNotFound();
        $this->getJson("/api/v1/dairies/{$inactiveDairy->uuid}")->assertNotFound();
        $this->patchJson("/api/v1/dairies/{$inactiveDairy->uuid}", ['name' => 'Updated'])
            ->assertNotFound();
    }

    public function test_cross_dairy_access_is_not_found(): void
    {
        $dairy = $this->createDairy(User::factory()->create());
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/dairies/{$dairy->uuid}")->assertNotFound();
        $this->patchJson("/api/v1/dairies/{$dairy->uuid}", ['name' => 'Updated'])
            ->assertNotFound();
    }

    public function test_owner_can_update_approved_profile_fields(): void
    {
        $owner = User::factory()->create();
        $dairy = $this->createDairy($owner);
        Sanctum::actingAs($owner);

        $this->patchJson("/api/v1/dairies/{$dairy->uuid}", [
            'name' => 'Green Valley Cooperative',
            'location' => [
                'city' => 'Indore',
                'state' => 'Madhya Pradesh',
                'country' => 'IN',
                'postal_code' => '452001',
            ],
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Dairy updated successfully.')
            ->assertJsonPath('data.name', 'Green Valley Cooperative')
            ->assertJsonPath('data.location.city', 'Indore')
            ->assertJsonPath('data.location.postal_code', '452001')
            ->assertJsonPath('data.owner.id', $owner->uuid)
            ->assertJsonPath('meta', []);

        $this->assertDatabaseHas('dairies', [
            'id' => $dairy->getKey(),
            'name' => 'Green Valley Cooperative',
            'city' => 'Indore',
            'state' => 'Madhya Pradesh',
            'country' => 'India',
            'country_code' => 'IN',
            'postal_code' => '452001',
        ]);
    }

    public function test_active_member_without_update_capability_is_forbidden(): void
    {
        $member = User::factory()->create();
        $dairy = $this->createDairy($member, 'operator');
        Sanctum::actingAs($member);

        $this->getJson("/api/v1/dairies/{$dairy->uuid}")->assertOk();
        $this->patchJson("/api/v1/dairies/{$dairy->uuid}", ['name' => 'Updated Dairy'])
            ->assertForbidden()
            ->assertJsonPath('success', false);
        $this->assertDatabaseHas('dairies', [
            'id' => $dairy->getKey(),
            'name' => 'Green Valley Dairy',
        ]);
    }

    public function test_invalid_profile_fields_return_validation_errors_without_mutation(): void
    {
        $owner = User::factory()->create();
        $dairy = $this->createDairy($owner);
        Sanctum::actingAs($owner);

        $this->patchJson("/api/v1/dairies/{$dairy->uuid}", [
            'name' => '  ',
            'location' => ['country' => 'US'],
        ])->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonStructure(['errors' => ['name', 'location.country']]);

        $this->patchJson("/api/v1/dairies/{$dairy->uuid}", [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'At least one dairy profile field is required.');
        $this->assertDatabaseHas('dairies', [
            'id' => $dairy->getKey(),
            'name' => 'Green Valley Dairy',
            'country_code' => 'IN',
        ]);
    }

    public function test_owner_membership_identity_and_role_fields_are_rejected(): void
    {
        $owner = User::factory()->create();
        $dairy = $this->createDairy($owner);
        Sanctum::actingAs($owner);

        foreach ([
            'owner_id' => 999,
            'created_by_user_id' => 999,
            'user_id' => 999,
            'role' => 'admin',
            'status' => 'inactive',
            'uuid' => (string) Str::uuid(),
            'membership' => ['role' => 'admin'],
            'unsupported_profile_field' => 'value',
        ] as $field => $value) {
            $this->patchJson("/api/v1/dairies/{$dairy->uuid}", [$field => $value])
                ->assertUnprocessable()
                ->assertJsonPath("errors.{$field}.0", 'This field cannot be updated.');
        }

        $this->assertDatabaseHas('dairies', [
            'id' => $dairy->getKey(),
            'name' => 'Green Valley Dairy',
            'created_by_user_id' => $owner->getKey(),
        ]);
    }

    public function test_repeating_identical_updates_is_safe_and_does_not_touch_the_dairy_again(): void
    {
        $owner = User::factory()->create();
        $dairy = $this->createDairy($owner);
        Sanctum::actingAs($owner);
        $payload = [
            'name' => 'Green Valley Dairy',
            'location' => [
                'city' => 'Bhopal',
                'state' => 'Madhya Pradesh',
                'country' => 'IN',
                'postal_code' => '462001',
            ],
        ];

        $first = $this->patchJson("/api/v1/dairies/{$dairy->uuid}", $payload)->assertOk();
        $originalUpdatedAt = $dairy->fresh()->updated_at;
        $second = $this->patchJson("/api/v1/dairies/{$dairy->uuid}", $payload)->assertOk();

        $this->assertSame($first->json(), $second->json());
        $this->assertSame(
            $originalUpdatedAt->toISOString(),
            $dairy->fresh()->updated_at->toISOString(),
        );
        $this->assertDatabaseCount('dairies', 1);
    }

    private function createDairy(
        User $owner,
        string $role = 'owner',
        string $membershipStatus = 'active',
        string $dairyStatus = 'active',
    ): Dairy {
        $dairy = Dairy::query()->create([
            'uuid' => (string) Str::uuid(),
            'code' => 'DUDHO-'.Str::upper(Str::random(24)),
            'name' => 'Green Valley Dairy',
            'city' => 'Bhopal',
            'state' => 'Madhya Pradesh',
            'country' => 'India',
            'country_code' => 'IN',
            'postal_code' => '462001',
            'status' => $dairyStatus,
            'created_by_user_id' => $owner->getKey(),
        ]);

        $dairy->memberships()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $owner->getKey(),
            'role' => $role,
            'status' => $membershipStatus,
            'is_primary_owner' => $role === 'owner',
            'created_by_user_id' => $owner->getKey(),
        ]);

        return $dairy;
    }
}
