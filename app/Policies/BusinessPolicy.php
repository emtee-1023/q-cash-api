<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\User;

class BusinessPolicy
{
    public function view(User $user, Business $business): bool
    {
        return $business->businessUsers()->where('user_id', $user->id)->exists();
    }

    public function update(User $user, Business $business): bool
    {
        return $business->businessUsers()
            ->where('user_id', $user->id)
            ->where('role', 'owner')
            ->exists();
    }

    public function manageProfile(User $user, Business $business): bool
    {
        return $business->businessUsers()
            ->where('user_id', $user->id)
            ->whereIn('role', ['owner', 'administrator'])
            ->exists();
    }

    public function manageFinance(User $user, Business $business): bool
    {
        return $this->update($user, $business);
    }
}
