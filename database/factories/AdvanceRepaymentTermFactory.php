<?php

namespace Database\Factories;

use App\Models\Advance;
use App\Models\AdvanceRepaymentTerm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdvanceRepaymentTerm>
 */
class AdvanceRepaymentTermFactory extends Factory
{
    public function definition(): array
    {
        return [
            'advance_id' => Advance::factory(),
            'recovery_rate_bps' => 1000,
            'maximum_repayment_days' => 30,
            'fallback_terms' => [],
            'effective_from' => now(),
            'effective_until' => null,
            'adjustment_reason' => null,
            'policy_version' => null,
            'changed_by_user_id' => null,
        ];
    }
}
