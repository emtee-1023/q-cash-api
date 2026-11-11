<?php

namespace Database\Factories;

use App\Models\SubCounty;
use App\Models\Ward;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Ward>
 */
class WardFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->city().' Ward';

        return [
            'sub_county_id' => SubCounty::factory(),
            'code' => fake()->unique()->numerify('9###'),
            'name' => $name,
            'slug' => Str::slug($name),
        ];
    }
}
