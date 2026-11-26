<?php

namespace App\Services\Advances;

use App\Enums\AssessmentStatus;
use App\Enums\OfferStatus;
use App\Models\AdvanceOffer;
use App\Models\EraAssessment;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class OfferService
{
    public function __construct(private readonly AdvanceExposureService $exposure) {}

    /**
     * Create an advance offer from an eligible assessment. The complete set of
     * terms presented to the business owner is snapshotted into offer_terms.
     *
     * The requested amount is the amount the borrower repays. A configured
     * access fee is deducted from it, so the disbursed principal is lower.
     *
     * @param  array<string, mixed>  $options
     */
    public function createOffer(EraAssessment $assessment, array $options = []): AdvanceOffer
    {
        if ($assessment->assessment_status !== AssessmentStatus::Eligible) {
            throw new RuntimeException('Cannot create an offer for an assessment that is not eligible.');
        }

        $recommended = $assessment->recommended_advance_amount_minor === null
            ? null
            : (int) $assessment->recommended_advance_amount_minor;

        $requestedAmount = isset($options['requested_amount_minor'])
            ? (int) $options['requested_amount_minor']
            : null;

        $offeredAmount = isset($options['offered_amount_minor'])
            ? (int) $options['offered_amount_minor']
            : $recommended;

        $hasExplicitFee = array_key_exists('fee_amount_minor', $options)
            && $options['fee_amount_minor'] !== null;

        if ($requestedAmount !== null
            && $recommended !== null
            && $requestedAmount > $recommended) {
            throw ValidationException::withMessages([
                'requested_amount_minor' => 'The requested amount exceeds the available advance.',
            ]);
        }

        if ($requestedAmount !== null) {
            $totalRepayable = $requestedAmount;
            $feeAmount = $this->accessFeeMinor($totalRepayable);
            $offeredAmount = $totalRepayable - $feeAmount;
        } elseif ($hasExplicitFee) {
            $feeAmount = (int) $options['fee_amount_minor'];
            $totalRepayable = (int) $offeredAmount + $feeAmount;
        } else {
            $totalRepayable = (int) $offeredAmount;
            $feeAmount = $this->accessFeeMinor($totalRepayable);
            $offeredAmount = $totalRepayable - $feeAmount;
        }

        $recoveryRateBps = $options['recovery_rate_bps'] ?? $assessment->recommended_recovery_rate_bps;
        $maximumRepaymentDays = $this->repaymentWindowDays(
            isset($options['maximum_repayment_days'])
                ? (int) $options['maximum_repayment_days']
                : $assessment->maximum_repayment_days,
        );

        if ($offeredAmount === null || $recoveryRateBps === null) {
            throw new RuntimeException('Assessment is missing recommended terms required to build an offer.');
        }

        $expiresInDays = $options['expires_in_days'] ?? 7;

        $terms = [
            'requested_amount_minor' => $totalRepayable,
            'offered_amount_minor' => $offeredAmount,
            'fee_amount_minor' => $feeAmount,
            'access_fee_bps' => $this->accessFeeBps(),
            'recovery_rate_bps' => $recoveryRateBps,
            'maximum_repayment_days' => $maximumRepaymentDays,
            'assessment_public_id' => $assessment->public_id,
            'expires_in_days' => $expiresInDays,
            'fallback_terms' => $options['fallback_terms'] ?? [],
            'policy_version' => $options['policy_version'] ?? null,
        ];

        return DB::transaction(function () use (
            $assessment,
            $offeredAmount,
            $feeAmount,
            $totalRepayable,
            $recoveryRateBps,
            $maximumRepaymentDays,
            $terms,
            $expiresInDays,
        ) {
            $this->exposure->ensureWithinExposure(
                $assessment->business,
                (int) $assessment->recommended_advance_amount_minor,
                $totalRepayable,
            );

            return AdvanceOffer::create([
                'assessment_id' => $assessment->id,
                'business_id' => $assessment->business_id,
                'offered_amount_minor' => $offeredAmount,
                'fee_amount_minor' => $feeAmount,
                'total_repayable_minor' => $totalRepayable,
                'recovery_rate_bps' => $recoveryRateBps,
                'maximum_repayment_days' => $maximumRepaymentDays,
                'offer_terms' => $terms,
                'status' => OfferStatus::Pending,
                'offered_at' => now(),
                'expires_at' => now()->addDays($expiresInDays),
            ]);
        });
    }

    public function accessFeeMinor(int $requestedAmountMinor): int
    {
        return Money::applyBasisPoints($requestedAmountMinor, $this->accessFeeBps());
    }

    public function accessFeeBps(): int
    {
        return max(0, min(10000, (int) config('repayment.access_fee_bps', 200)));
    }

    public function repaymentWindowDays(?int $recommendedDays = null): int
    {
        $maximum = max(1, (int) config('repayment.maximum_days', 30));

        return $recommendedDays === null
            ? $maximum
            : min($recommendedDays, $maximum);
    }
}
