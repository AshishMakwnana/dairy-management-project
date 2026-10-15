<?php

namespace App\Policies;

use App\Models\Dairy;
use App\Models\User;

class DairyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->status === 'active';
    }

    public function view(User $user, Dairy $dairy): bool
    {
        return $user->status === 'active'
            && $dairy->status === 'active'
            && $dairy->memberships()
                ->where('user_id', $user->getKey())
                ->where('status', 'active')
                ->exists();
    }

    public function create(User $user): bool
    {
        return $user->status === 'active';
    }

    public function update(User $user, Dairy $dairy): bool
    {
        return $user->status === 'active'
            && $dairy->status === 'active'
            && $dairy->memberships()
                ->where('user_id', $user->getKey())
                ->where('role', 'owner')
                ->where('status', 'active')
                ->exists();
    }

    public function delete(User $user, Dairy $dairy): bool
    {
        return false;
    }
}
