<?php

namespace App\Services;

use App\Exceptions\DairyIdempotencyConflictException;
use App\Models\Dairy;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class DairyOnboardingService
{
    /** @return Collection<int, Dairy> */
    public function listFor(User $user): Collection
    {
        return Dairy::query()
            ->whereHas('memberships', function (Builder $query) use ($user): void {
                $query->where('user_id', $user->getKey())
                    ->where('status', 'active');
            })
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array{
     *     name: string,
     *     legal_name?: string|null,
     *     location: array{city: string, state: string, country: string, postal_code?: string|null},
     *     client_uuid: string
     * }  $data
     * @return array{dairy: Dairy, created: bool}
     */
    public function create(User $creator, array $data): array
    {
        $canonicalRequest = $this->canonicalRequest($data);
        $requestHash = hash('sha256', json_encode($canonicalRequest, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($creator, $data, $canonicalRequest, $requestHash): array {
            $lockedCreator = User::query()
                ->whereKey($creator->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($lockedCreator)->authorize('create', Dairy::class);

            $existingDairy = Dairy::query()
                ->where('created_by_user_id', $lockedCreator->getKey())
                ->where('creation_request_uuid', $data['client_uuid'])
                ->first();

            if ($existingDairy !== null) {
                if (! hash_equals($existingDairy->creation_request_hash, $requestHash)) {
                    throw new DairyIdempotencyConflictException;
                }

                return ['dairy' => $existingDairy, 'created' => false];
            }

            $dairyUuid = (string) Str::uuid();
            $dairy = Dairy::query()->create([
                'uuid' => $dairyUuid,
                'code' => 'DUDHO-'.Str::upper(Str::random(24)),
                'name' => $canonicalRequest['name'],
                'legal_name' => $canonicalRequest['legal_name'],
                'city' => $canonicalRequest['location']['city'],
                'state' => $canonicalRequest['location']['state'],
                'country' => 'India',
                'country_code' => 'IN',
                'postal_code' => $canonicalRequest['location']['postal_code'],
                'status' => 'active',
                'created_by_user_id' => $lockedCreator->getKey(),
                'creation_request_uuid' => $data['client_uuid'],
                'creation_request_hash' => $requestHash,
            ]);

            $now = now();

            $dairy->memberships()->create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $lockedCreator->getKey(),
                'role' => 'owner',
                'status' => 'active',
                'is_primary_owner' => true,
                'joined_at' => $now,
                'created_by_user_id' => $lockedCreator->getKey(),
            ]);

            DB::table('dairy_settings')->insert([
                'dairy_id' => $dairy->getKey(),
                'measurement_unit' => 'litre',
                'currency' => 'INR',
                'language' => $lockedCreator->preferred_language,
                'settings_version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $milkTypeIds = DB::table('milk_types')
                ->where('status', 'active')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->pluck('id')
                ->all();

            if ($milkTypeIds !== []) {
                DB::table('dairy_milk_types')->insert(array_map(
                    fn (int $milkTypeId): array => [
                        'dairy_id' => $dairy->getKey(),
                        'milk_type_id' => $milkTypeId,
                        'is_enabled' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    $milkTypeIds,
                ));
            }

            $qualityParameters = DB::table('quality_parameters')
                ->where('status', 'active')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'sort_order']);

            if ($qualityParameters->isNotEmpty()) {
                DB::table('dairy_quality_parameters')->insert(
                    $qualityParameters->map(fn (object $parameter): array => [
                        'dairy_id' => $dairy->getKey(),
                        'quality_parameter_id' => $parameter->id,
                        'is_enabled' => true,
                        'usage' => 'record_only',
                        'is_required' => false,
                        'sort_order' => $parameter->sort_order,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all(),
                );
            }

            foreach ([
                ['name' => 'Morning', 'code' => 'morning', 'sort_order' => 10],
                ['name' => 'Evening', 'code' => 'evening', 'sort_order' => 20],
            ] as $session) {
                DB::table('collection_sessions')->insert([
                    ...$session,
                    'uuid' => (string) Str::uuid(),
                    'dairy_id' => $dairy->getKey(),
                    'is_enabled' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            return ['dairy' => $dairy, 'created' => true];
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     name: string,
     *     legal_name: string|null,
     *     location: array{city: string, state: string, country: string, postal_code: string|null}
     * }
     */
    private function canonicalRequest(array $data): array
    {
        $location = $data['location'];

        return [
            'name' => trim($data['name']),
            'legal_name' => isset($data['legal_name']) ? trim($data['legal_name']) : null,
            'location' => [
                'city' => trim($location['city']),
                'state' => trim($location['state']),
                'country' => strtoupper(trim($location['country'])),
                'postal_code' => isset($location['postal_code']) ? trim($location['postal_code']) : null,
            ],
        ];
    }
}
