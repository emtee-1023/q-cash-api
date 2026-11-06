<?php

namespace Database\Factories;

use App\Enums\BusinessUserRole;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessUser>
 */
class BusinessUserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'user_id' => User::factory(),
            'role' => BusinessUserRole::Owner->value,
            'is_primary_contact' => true,
            'status' => 'active',
        ];
    }

    public function staff(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => BusinessUserRole::Staff->value,
            'is_primary_contact' => false,
        ]);
    }
}
