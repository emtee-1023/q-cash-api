<?php

namespace App\Policies;

use App\Enums\BusinessUserRole;
use App\Enums\BusinessUserStatus;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessPolicy
{
    public function view(User $user, Business $business): bool
    {
        return $this->membership($user, $business)->exists();
    }

    public function update(User $user, Business $business): bool
    {
        return $this->membership($user, $business)
            ->where('role', BusinessUserRole::Owner->value)
            ->exists();
    }

    public function manageProfile(User $user, Business $business): bool
    {
        return $this->membership($user, $business)
            ->whereIn('role', [
                BusinessUserRole::Owner->value,
                BusinessUserRole::Administrator->value,
            ])
            ->exists();
    }

    public function manageFinance(User $user, Business $business): bool
    {
        return $this->update($user, $business);
    }

    public function manageMembers(User $user, Business $business): bool
    {
        return $this->membership($user, $business)
            ->whereIn('role', [
                BusinessUserRole::Owner->value,
                BusinessUserRole::Administrator->value,
            ])
            ->exists();
    }

    /**
     * @return HasMany<BusinessUser, Business>
     */
    private function membership(User $user, Business $business): HasMany
    {
        return $business->businessUsers()
            ->where('user_id', $user->id)
            ->where('status', BusinessUserStatus::Active->value);
    }
}
