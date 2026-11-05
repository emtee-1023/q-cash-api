<?php

namespace App\Services\Advances;

use App\Enums\AssessmentStatus;
use App\Enums\OfferStatus;
use App\Models\AdvanceOffer;
use App\Models\EraAssessment;
use RuntimeException;

class OfferService
{
    /**
     * Create an advance offer from an eligible assessment. The complete set of
     * terms presented to the business owner is snapshotted into offer_terms.
     *
     * @param  array<string, mixed>  $options
     */
    public function createOffer(EraAssessment $assessment, array $options = []): AdvanceOffer
    {
        if ($assessment->assessment_status !== AssessmentStatus::Eligible) {
            throw new RuntimeException('Cannot create an offer for an assessment that is not eligible.');
        }

        $offeredAmount = $options['offered_amount_minor'] ?? $assessment->recommended_advance_amount_minor;
        $feeAmount = $options['fee_amount_minor'] ?? 0;
        $recoveryRateBps = $options['recovery_rate_bps'] ?? $assessment->recommended_recovery_rate_bps;
        $maximumRepaymentDays = $options['maximum_repayment_days'] ?? $assessment->maximum_repayment_days;

        if ($offeredAmount === null || $recoveryRateBps === null || $maximumRepaymentDays === null) {
            throw new RuntimeException('Assessment is missing recommended terms required to build an offer.');
        }

        $expiresInDays = $options['expires_in_days'] ?? 7;

        $terms = [
            'offered_amount_minor' => $offeredAmount,
            'fee_amount_minor' => $feeAmount,
            'recovery_rate_bps' => $recoveryRateBps,
            'maximum_repayment_days' => $maximumRepaymentDays,
            'assessment_public_id' => $assessment->public_id,
            'expires_in_days' => $expiresInDays,
            'fallback_terms' => $options['fallback_terms'] ?? [],
            'policy_version' => $options['policy_version'] ?? null,
        ];

        return AdvanceOffer::create([
            'assessment_id' => $assessment->id,
            'business_id' => $assessment->business_id,
            'offered_amount_minor' => $offeredAmount,
            'fee_amount_minor' => $feeAmount,
            'total_repayable_minor' => $offeredAmount + $feeAmount,
            'recovery_rate_bps' => $recoveryRateBps,
            'maximum_repayment_days' => $maximumRepaymentDays,
            'offer_terms' => $terms,
            'status' => OfferStatus::Pending,
            'offered_at' => now(),
            'expires_at' => now()->addDays($expiresInDays),
        ]);
    }
}
