<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'uuid',
    'dairy_id',
    'user_id',
    'role',
    'status',
    'is_primary_owner',
    'joined_at',
    'created_by_user_id',
])]
class DairyUser extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_primary_owner' => 'boolean',
            'joined_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Dairy, $this> */
    public function dairy(): BelongsTo
    {
        return $this->belongsTo(Dairy::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
