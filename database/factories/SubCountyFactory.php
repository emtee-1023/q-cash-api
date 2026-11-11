<?php

namespace Database\Factories;

use App\Models\County;
use App\Models\SubCounty;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SubCounty>
 */
class SubCountyFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->city().' Sub County';

        return [
            'county_id' => County::factory(),
            'code' => fake()->unique()->numerify('9####'),
            'name' => $name,
            'slug' => Str::slug($name),
        ];
    }
}
