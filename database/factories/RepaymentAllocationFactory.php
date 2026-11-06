<?php

namespace Database\Factories;

use App\Models\Advance;
use App\Models\RepaymentAllocation;
use App\Models\RepaymentCollection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RepaymentAllocation>
 */
class RepaymentAllocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'repayment_collection_id' => RepaymentCollection::factory(),
            'advance_id' => Advance::factory(),
            'amount_minor' => fake()->numberBetween(1000, 1000000),
        ];
    }
}
