<?php

namespace Database\Factories;

use App\Enums\PolicyStatus;
use App\Models\DecisionPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DecisionPolicy>
 */
class DecisionPolicyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'policy_name' => 'era_default',
            'version' => fake()->unique()->numerify('v#.#'),
            'scoring_weights' => ['revenue' => 0.6, 'reliability' => 0.4],
            'scoring_bands' => null,
            'advance_rules' => ['max_advance_rate_bps' => 3000],
            'repayment_rules' => ['default_recovery_rate_bps' => 1000],
            'eligibility_rules' => null,
            'effective_from' => now(),
            'effective_until' => null,
            'status' => PolicyStatus::Draft->value,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PolicyStatus::Active->value,
        ]);
    }
}
