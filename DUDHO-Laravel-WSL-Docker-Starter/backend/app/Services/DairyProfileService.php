<?php

namespace App\Services;

use App\Models\Dairy;
use Illuminate\Support\Facades\DB;

class DairyProfileService
{
    /**
     * @param  array{
     *     name?: string,
     *     location?: array{
     *         city?: string,
     *         state?: string,
     *         country?: string,
     *         postal_code?: string|null
     *     }
     * }  $data
     */
    public function update(Dairy $dairy, array $data): Dairy
    {
        return DB::transaction(function () use ($dairy, $data): Dairy {
            $lockedDairy = Dairy::query()
                ->whereKey($dairy->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (array_key_exists('name', $data)) {
                $lockedDairy->name = trim($data['name']);
            }

            $location = $data['location'] ?? [];
            foreach ([
                'city' => 'city',
                'state' => 'state',
                'postal_code' => 'postal_code',
            ] as $input => $column) {
                if (array_key_exists($input, $location)) {
                    $lockedDairy->{$column} = $location[$input] === null
                        ? null
                        : trim($location[$input]);
                }
            }

            if (array_key_exists('country', $location)) {
                $lockedDairy->country = 'India';
                $lockedDairy->country_code = 'IN';
            }

            if ($lockedDairy->isDirty()) {
                $lockedDairy->save();
            }

            return $lockedDairy->load('creator');
        });
    }
}
