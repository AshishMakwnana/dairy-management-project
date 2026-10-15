<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'uuid',
    'code',
    'name',
    'legal_name',
    'city',
    'state',
    'country',
    'country_code',
    'postal_code',
    'status',
    'onboarding_completed_at',
    'created_by_user_id',
    'creation_request_uuid',
    'creation_request_hash',
])]
class Dairy extends Model
{
    /** @return HasMany<DairyUser, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(DairyUser::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
