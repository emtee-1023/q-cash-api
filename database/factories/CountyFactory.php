<?php

namespace Database\Factories;

use App\Models\County;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<County>
 */
class CountyFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->city().' County';

        return [
            'code' => fake()->unique()->numerify('9###'),
            'name' => $name,
            'slug' => Str::slug($name),
            'capital' => fake()->city(),
            'region' => fake()->randomElement(['Central', 'Coast', 'Eastern', 'Nairobi', 'Rift Valley', 'Western']),
            'iso_code' => fake()->unique()->bothify('XX-##'),
            'postal_code' => fake()->numerify('#####'),
        ];
    }
}
