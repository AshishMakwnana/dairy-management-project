<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'uuid',
    'name',
    'mobile_country_code',
    'mobile',
    'mobile_verified_at',
    'email',
    'email_verified_at',
    'preferred_language',
    'status',
    'last_login_at',
    'default_dairy_id',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'mobile_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'default_dairy_id' => 'integer',
            'password' => 'hashed',
        ];
    }

    /** @return BelongsTo<Dairy, $this> */
    public function defaultDairy(): BelongsTo
    {
        return $this->belongsTo(Dairy::class, 'default_dairy_id');
    }

    /** @return HasMany<DairyUser, $this> */
    public function dairyMemberships(): HasMany
    {
        return $this->hasMany(DairyUser::class);
    }
}
