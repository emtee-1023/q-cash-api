<?php

namespace Database\Factories;

use App\Enums\OfferStatus;
use App\Models\AdvanceOffer;
use App\Models\Business;
use App\Models\EraAssessment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdvanceOffer>
 */
class AdvanceOfferFactory extends Factory
{
    public function definition(): array
    {
        $offered = fake()->numberBetween(100000, 5000000);
        $fee = 0;

        return [
            'assessment_id' => EraAssessment::factory(),
            'business_id' => Business::factory(),
            'offered_amount_minor' => $offered,
            'fee_amount_minor' => $fee,
            'total_repayable_minor' => $offered + $fee,
            'recovery_rate_bps' => 1000,
            'maximum_repayment_days' => 30,
            'offer_terms' => [],
            'status' => OfferStatus::Pending->value,
            'offered_at' => now(),
            'expires_at' => now()->addDays(7),
            'accepted_at' => null,
            'rejected_at' => null,
        ];
    }
}
