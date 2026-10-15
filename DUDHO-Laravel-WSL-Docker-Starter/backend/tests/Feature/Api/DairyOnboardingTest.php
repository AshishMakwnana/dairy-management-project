<?php

namespace Tests\Feature\Api;

use App\Models\Dairy;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DairyOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_dairy_routes_require_authentication(): void
    {
        $payload = $this->dairyPayload();

        $this->getJson('/api/v1/dairies')->assertUnauthorized();
        $this->postJson('/api/v1/dairies', $payload)->assertUnauthorized();
        $this->assertDatabaseCount('dairies', 0);
    }

    public function test_index_returns_only_the_authenticated_users_active_memberships(): void
    {
        $this->seed(MasterDataSeeder::class);
        $owner = User::factory()->create();
        $anotherOwner = User::factory()->create();

        Sanctum::actingAs($owner);
        $ownerDairy = $this->postJson('/api/v1/dairies', $this->dairyPayload())
            ->assertCreated()
            ->json('data');

        Sanctum::actingAs($anotherOwner);
        $anotherDairy = $this->postJson('/api/v1/dairies', $this->dairyPayload('Another Dairy'))
            ->assertCreated()
            ->json('data');

        $inactiveDairy = $this->createDairyMembership($owner, 'inactive dairy', 'suspended');

        Sanctum::actingAs($owner);
        $response = $this->getJson('/api/v1/dairies');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $ownerDairy['id'])
            ->assertJsonPath('data.0.name', 'Green Valley Dairy');
        $this->assertNotSame($anotherDairy['id'], $ownerDairy['id']);
        $this->assertDatabaseHas('dairies', ['uuid' => $inactiveDairy->uuid]);
    }

    public function test_index_returns_an_empty_list_when_the_user_has_no_active_memberships(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/dairies')
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.total', 0);
    }

    public function test_store_creates_a_draft_dairy_and_all_initial_owner_setup_atomically(): void
    {
        $this->seed(MasterDataSeeder::class);
        $owner = User::factory()->create(['preferred_language' => 'hi']);
        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/v1/dairies', $this->dairyPayload());

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Dairy created successfully.')
            ->assertJsonPath('data.name', 'Green Valley Dairy')
            ->assertJsonPath('data.state', 'draft')
            ->assertJsonPath('data.location.city', 'Bhopal')
            ->assertJsonPath('data.location.state', 'Madhya Pradesh')
            ->assertJsonPath('data.location.country', 'IN')
            ->assertJsonStructure(['data' => ['id', 'name', 'state']]);

        $dairy = Dairy::query()->where('uuid', $response->json('data.id'))->firstOrFail();
        $this->assertSame($owner->getKey(), $dairy->created_by_user_id);
        $this->assertSame('India', $dairy->country);
        $this->assertDatabaseHas('dairy_users', [
            'dairy_id' => $dairy->getKey(),
            'user_id' => $owner->getKey(),
            'role' => 'owner',
            'status' => 'active',
            'is_primary_owner' => true,
            'created_by_user_id' => $owner->getKey(),
        ]);
        $this->assertDatabaseHas('dairy_settings', [
            'dairy_id' => $dairy->getKey(),
            'measurement_unit' => 'litre',
            'currency' => 'INR',
            'language' => 'hi',
        ]);
        $this->assertDatabaseCount('dairy_milk_types', 2);
        $this->assertDatabaseCount('dairy_quality_parameters', 5);
        $this->assertDatabaseCount('collection_sessions', 2);
        $this->assertDatabaseHas('collection_sessions', [
            'dairy_id' => $dairy->getKey(),
            'name' => 'Morning',
            'code' => 'morning',
            'is_enabled' => true,
        ]);
        $this->assertDatabaseHas('collection_sessions', [
            'dairy_id' => $dairy->getKey(),
            'name' => 'Evening',
            'code' => 'evening',
            'is_enabled' => true,
        ]);
        $this->assertDatabaseCount('dairies', 1);
        $this->assertDatabaseCount('dairy_users', 1);
    }

    public function test_store_replays_the_same_dairy_for_the_same_idempotency_key_and_body(): void
    {
        $this->seed(MasterDataSeeder::class);
        Sanctum::actingAs(User::factory()->create());
        $payload = $this->dairyPayload();

        $firstResponse = $this->postJson('/api/v1/dairies', $payload);
        $retryResponse = $this->postJson('/api/v1/dairies', $payload);

        $firstResponse->assertCreated();
        $retryResponse->assertOk();
        $this->assertSame($firstResponse->json(), $retryResponse->json());
        $this->assertDatabaseCount('dairies', 1);
        $this->assertDatabaseCount('dairy_users', 1);
        $this->assertDatabaseCount('dairy_settings', 1);
        $this->assertDatabaseCount('collection_sessions', 2);
    }

    public function test_store_returns_conflict_when_the_same_key_is_reused_with_different_data(): void
    {
        $this->seed(MasterDataSeeder::class);
        Sanctum::actingAs(User::factory()->create());
        $payload = $this->dairyPayload();

        $this->postJson('/api/v1/dairies', $payload)->assertCreated();
        $this->postJson('/api/v1/dairies', [
            ...$payload,
            'name' => 'Changed Dairy Name',
        ])->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['client_uuid']]);

        $this->assertDatabaseCount('dairies', 1);
        $this->assertDatabaseCount('dairy_users', 1);
    }

    public function test_same_idempotency_key_is_scoped_to_the_authenticated_creator(): void
    {
        $this->seed(MasterDataSeeder::class);
        $payload = $this->dairyPayload();

        Sanctum::actingAs(User::factory()->create());
        $firstResponse = $this->postJson('/api/v1/dairies', $payload)->assertCreated();

        Sanctum::actingAs(User::factory()->create());
        $secondResponse = $this->postJson('/api/v1/dairies', $payload)->assertCreated();

        $this->assertNotSame($firstResponse->json('data.id'), $secondResponse->json('data.id'));
        $this->assertDatabaseCount('dairies', 2);
        $this->assertDatabaseCount('dairy_users', 2);
    }

    public function test_store_does_not_accept_client_supplied_owner_or_role(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/dairies', [
            ...$this->dairyPayload(),
            'created_by_user_id' => 999,
            'owner_id' => 999,
            'user_id' => 999,
            'role' => 'admin',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.created_by_user_id.0', 'The dairy creator is determined by the authenticated user.')
            ->assertJsonPath('errors.owner_id.0', 'The dairy owner is determined by the authenticated user.')
            ->assertJsonPath('errors.user_id.0', 'The dairy owner is determined by the authenticated user.')
            ->assertJsonPath('errors.role.0', 'The dairy role is assigned by the server.');

        $this->assertDatabaseCount('dairies', 0);
        $this->assertDatabaseCount('dairy_users', 0);
    }

    public function test_store_rejects_invalid_required_fields_and_non_indian_country(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/dairies', [])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonStructure(['errors' => ['name', 'location', 'client_uuid']]);

        $this->postJson('/api/v1/dairies', [
            ...$this->dairyPayload(),
            'location' => [
                ...$this->dairyPayload()['location'],
                'country' => 'US',
            ],
        ])->assertUnprocessable()
            ->assertJsonFragment([
                'location.country' => ['The location country must be IN for the initial rollout.'],
            ]);

        $this->assertDatabaseCount('dairies', 0);
    }

    public function test_inactive_users_cannot_list_or_create_dairies(): void
    {
        $user = User::factory()->create(['status' => 'suspended']);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/dairies')->assertForbidden();
        $this->postJson('/api/v1/dairies', $this->dairyPayload())->assertForbidden();

        $this->assertDatabaseCount('dairies', 0);
    }

    /**
     * @return array{
     *     name: string,
     *     legal_name: string,
     *     location: array{city: string, state: string, country: string, postal_code: string},
     *     client_uuid: string
     * }
     */
    private function dairyPayload(string $name = 'Green Valley Dairy'): array
    {
        return [
            'name' => $name,
            'legal_name' => 'Green Valley Dairy Private Limited',
            'location' => [
                'city' => 'Bhopal',
                'state' => 'Madhya Pradesh',
                'country' => 'IN',
                'postal_code' => '462001',
            ],
            'client_uuid' => (string) Str::uuid(),
        ];
    }

    private function createDairyMembership(User $user, string $name, string $status): Dairy
    {
        $dairy = Dairy::query()->create([
            'uuid' => (string) Str::uuid(),
            'code' => 'DUDHO-'.Str::upper(Str::random(24)),
            'name' => $name,
            'created_by_user_id' => $user->getKey(),
        ]);

        $dairy->memberships()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->getKey(),
            'role' => 'owner',
            'status' => $status,
            'is_primary_owner' => true,
            'created_by_user_id' => $user->getKey(),
        ]);

        return $dairy;
    }
}
