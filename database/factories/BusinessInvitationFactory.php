<?php

namespace Database\Factories;

use App\Enums\BusinessUserRole;
use App\Models\Business;
use App\Models\BusinessInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessInvitation>
 */
class BusinessInvitationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'invited_by_user_id' => User::factory(),
            'user_id' => null,
            'phone_number' => '254'.fake()->unique()->numerify('7########'),
            'name' => fake()->optional()->name(),
            'role' => BusinessUserRole::Staff->value,
            'accepted_at' => null,
        ];
    }
}
