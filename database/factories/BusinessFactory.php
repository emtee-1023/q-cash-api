<?php

namespace Database\Factories;

use App\Enums\BusinessStatus;
use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Business>
 */
class BusinessFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_name' => fake()->company(),
            'business_type' => fake()->randomElement(['retail', 'wholesale', 'service']),
            'business_category' => fake()->randomElement(['shop', 'food', 'transport']),
            'registration_number' => fake()->optional()->numerify('REG-#####'),
            'phone_number' => '254'.fake()->numerify('7########'),
            'email' => fake()->unique()->safeEmail(),
            'county' => fake()->city(),
            'town' => fake()->city(),
            'physical_address' => fake()->optional()->address(),
            'operating_since' => fake()->optional()->date(),
            'status' => BusinessStatus::Pending->value,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BusinessStatus::Active->value,
        ]);
    }
}
