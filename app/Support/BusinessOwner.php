<?php

namespace App\Support;

use App\Enums\BusinessUserRole;
use App\Enums\BusinessUserStatus;
use App\Models\Business;
use App\Models\User;

final class BusinessOwner
{
    public static function for(Business $business): ?User
    {
        $owners = $business->businessUsers()
            ->where('role', BusinessUserRole::Owner->value)
            ->where('status', BusinessUserStatus::Active->value)
            ->with('user')
            ->get();

        $owner = $owners->firstWhere('is_primary_contact', true) ?? $owners->first();

        return $owner?->user;
    }
}
