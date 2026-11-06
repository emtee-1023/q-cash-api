<?php

namespace Database\Factories;

use App\Enums\DisbursementStatus;
use App\Models\Advance;
use App\Models\Disbursement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Disbursement>
 */
class DisbursementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'advance_id' => Advance::factory(),
            'provider' => 'Safaricom',
            'provider_reference' => fake()->unique()->uuid(),
            'amount_minor' => fake()->numberBetween(100000, 5000000),
            'status' => DisbursementStatus::Pending->value,
            'requested_at' => null,
            'completed_at' => null,
            'failure_reason' => null,
            'provider_response' => [],
        ];
    }
}
