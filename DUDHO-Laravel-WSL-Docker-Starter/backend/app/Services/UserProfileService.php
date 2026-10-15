<?php

namespace App\Services;

use App\Models\Dairy;
use App\Models\DairyUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserProfileService
{
    /**
     * @param  array{
     *     full_name?: string,
     *     preferred_language?: string,
     *     default_dairy_id?: string|null
     * }  $data
     */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $profile = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (array_key_exists('full_name', $data)) {
                $profile->name = trim($data['full_name']);
            }

            if (array_key_exists('preferred_language', $data)) {
                $profile->preferred_language = $data['preferred_language'];
            }

            if (array_key_exists('default_dairy_id', $data)) {
                $dairyUuid = $data['default_dairy_id'];

                if ($dairyUuid === null) {
                    $profile->default_dairy_id = null;
                } else {
                    $dairyId = Dairy::query()
                        ->where('uuid', $dairyUuid)
                        ->whereIn(
                            'id',
                            DairyUser::query()
                                ->select('dairy_id')
                                ->where('user_id', $profile->getKey())
                                ->where('status', 'active'),
                        )
                        ->value('id');

                    if ($dairyId === null) {
                        throw ValidationException::withMessages([
                            'default_dairy_id' => [
                                'The selected dairy must have an active membership.',
                            ],
                        ]);
                    }

                    $profile->default_dairy_id = $dairyId;
                }
            }

            $profile->save();

            return $profile->load('defaultDairy');
        });
    }
}
