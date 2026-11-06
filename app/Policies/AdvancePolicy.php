<?php

namespace App\Policies;

use App\Models\Advance;
use App\Models\User;

class AdvancePolicy
{
    public function view(User $user, Advance $advance): bool
    {
        return $advance->business->businessUsers()->where('user_id', $user->id)->exists();
    }

    public function manage(User $user, Advance $advance): bool
    {
        return $advance->business->businessUsers()
            ->where('user_id', $user->id)
            ->where('role', 'owner')
            ->exists();
    }
}
