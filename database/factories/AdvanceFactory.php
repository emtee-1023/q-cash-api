<?php

namespace Database\Factories;

use App\Enums\AdvanceStatus;
use App\Models\Advance;
use App\Models\AdvanceOffer;
use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Advance>
 */
class AdvanceFactory extends Factory
{
    public function definition(): array
    {
        $principal = fake()->numberBetween(100000, 5000000);
        $fee = 0;

        return [
            'offer_id' => AdvanceOffer::factory(),
            'business_id' => Business::factory(),
            'financing_partner_id' => null,
            'principal_amount_minor' => $principal,
            'fee_amount_minor' => $fee,
            'total_repayable_minor' => $principal + $fee,
            'amount_repaid_minor' => 0,
            'outstanding_balance_minor' => $principal + $fee,
            'recovery_rate_bps' => 1000,
            'maximum_repayment_days' => 30,
            'approved_at' => null,
            'disbursed_at' => null,
            'repayment_started_at' => null,
            'due_at' => null,
            'settled_at' => null,
            'status' => AdvanceStatus::Pending->value,
        ];
    }
}
