<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class PhaseOneSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_phase_one_tables_exist_without_a_global_user_role(): void
    {
        foreach ([
            'otp_challenges',
            'dairies',
            'dairy_users',
            'dairy_settings',
            'milk_types',
            'dairy_milk_types',
            'collection_sessions',
            'quality_parameters',
            'dairy_quality_parameters',
            'personal_access_tokens',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Expected {$table} to exist.");
        }

        $this->assertFalse(Schema::hasColumn('users', 'role'));
        $this->assertTrue(Schema::hasColumn('users', 'mobile_country_code'));
        $this->assertTrue(Schema::hasColumn('users', 'mobile'));
        $this->assertTrue(Schema::hasColumn('users', 'preferred_language'));
        $this->assertTrue(Schema::hasColumn('users', 'default_dairy_id'));
        $this->assertTrue(Schema::hasColumn('otp_challenges', 'otp_hash'));
        $this->assertFalse(Schema::hasColumn('otp_challenges', 'code'));
    }

    public function test_user_factory_creates_a_public_uuid_and_normalized_mobile_identity(): void
    {
        $user = User::factory()->create();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $user->uuid,
        );
        $this->assertSame('+91', $user->mobile_country_code);
        $this->assertMatchesRegularExpression('/^9\d{9}$/', $user->mobile);
        $this->assertNotNull($user->mobile_verified_at);
    }

    public function test_public_user_uuid_is_unique(): void
    {
        $user = User::factory()->create();

        $this->expectException(QueryException::class);

        User::factory()->create([
            'uuid' => $user->uuid,
        ]);
    }

    public function test_master_milk_and_quality_catalogs_are_seeded_idempotently(): void
    {
        $this->seed(MasterDataSeeder::class);
        $this->seed(MasterDataSeeder::class);

        $this->assertDatabaseCount('milk_types', 2);
        $this->assertDatabaseHas('milk_types', [
            'code' => 'cow',
            'name' => 'Cow Milk',
            'status' => 'active',
        ]);
        $this->assertDatabaseCount('quality_parameters', 5);
        $this->assertDatabaseHas('quality_parameters', [
            'code' => 'fat',
            'name' => 'Fat',
            'value_type' => 'decimal',
            'status' => 'active',
        ]);
    }

    public function test_normalized_mobile_identity_is_unique(): void
    {
        $user = User::factory()->create();

        $this->expectException(QueryException::class);

        User::factory()->create([
            'mobile_country_code' => $user->mobile_country_code,
            'mobile' => $user->mobile,
        ]);
    }

    public function test_one_user_can_have_owner_memberships_in_multiple_dairies(): void
    {
        $user = User::factory()->create();
        $firstDairyId = $this->createDairy($user, (string) Str::uuid());
        $secondDairyId = $this->createDairy($user, (string) Str::uuid());

        $this->createOwnerMembership($user, $firstDairyId);
        $this->createOwnerMembership($user, $secondDairyId);

        $this->assertDatabaseCount('dairy_users', 2);
        $this->assertDatabaseCount('dairies', 2);
    }

    public function test_dairy_creation_request_keys_are_unique_per_creator(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $requestUuid = (string) Str::uuid();

        $this->createDairy($firstUser, $requestUuid);
        $this->createDairy($secondUser, $requestUuid);

        $this->expectException(QueryException::class);

        $this->createDairy($firstUser, $requestUuid);
    }

    private function createDairy(User $user, string $requestUuid): int
    {
        return DB::table('dairies')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'code' => 'DRY-'.Str::random(12),
            'name' => 'Test Dairy',
            'created_by_user_id' => $user->getKey(),
            'creation_request_uuid' => $requestUuid,
            'creation_request_hash' => hash('sha256', 'test-request'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createOwnerMembership(User $user, int $dairyId): void
    {
        DB::table('dairy_users')->insert([
            'uuid' => (string) Str::uuid(),
            'dairy_id' => $dairyId,
            'user_id' => $user->getKey(),
            'role' => 'owner',
            'status' => 'active',
            'is_primary_owner' => true,
            'joined_at' => now(),
            'created_by_user_id' => $user->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
