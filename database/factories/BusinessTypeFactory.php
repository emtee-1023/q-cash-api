<?php

namespace Database\Factories;

use App\Models\BusinessType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BusinessType>
 */
class BusinessTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(2, true));

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'category' => fake()->randomElement(['retail', 'food', 'transport', 'services', 'ecommerce', 'agriculture', 'wholesale', 'other']),
            'description' => fake()->sentence(),
            'average_monthly_revenue_minor' => fake()->numberBetween(4000000, 80000000),
            'is_active' => true,
        ];
    }
}
