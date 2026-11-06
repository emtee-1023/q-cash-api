<?php

namespace App\Services\Advances;

use App\Enums\AdvanceStatus;
use App\Enums\DisbursementStatus;
use App\Enums\OfferStatus;
use App\Models\Advance;
use App\Models\AdvanceOffer;
use App\Models\AdvanceRepaymentTerm;
use App\Models\Disbursement;
use App\Models\FinancingPartner;
use App\Models\User;
use App\Services\Ledger\AdvanceLedger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AdvanceService
{
    public function __construct(private readonly AdvanceLedger $ledger) {}

    /**
     * Accept an offer and create the corresponding advance, recording the
     * original accepted terms as the initial repayment-terms record.
     */
    public function acceptOffer(AdvanceOffer $offer, ?FinancingPartner $partner = null): Advance
    {
        if ($offer->status !== OfferStatus::Pending) {
            throw new RuntimeException('Only pending offers can be accepted.');
        }

        return DB::transaction(function () use ($offer, $partner) {
            $advance = Advance::create([
                'offer_id' => $offer->id,
                'business_id' => $offer->business_id,
                'financing_partner_id' => $partner?->id,
                'principal_amount_minor' => $offer->offered_amount_minor,
                'fee_amount_minor' => $offer->fee_amount_minor,
                'total_repayable_minor' => $offer->total_repayable_minor,
                'amount_repaid_minor' => 0,
                'outstanding_balance_minor' => $offer->total_repayable_minor,
                'recovery_rate_bps' => $offer->recovery_rate_bps,
                'maximum_repayment_days' => $offer->maximum_repayment_days,
                'status' => AdvanceStatus::Pending,
            ]);

            $advance->repaymentTerms()->create([
                'recovery_rate_bps' => $offer->recovery_rate_bps,
                'maximum_repayment_days' => $offer->maximum_repayment_days,
                'fallback_terms' => $offer->offer_terms['fallback_terms'] ?? [],
                'effective_from' => now(),
                'adjustment_reason' => 'Original accepted terms',
                'policy_version' => $offer->offer_terms['policy_version'] ?? null,
            ]);

            $offer->update([
                'status' => OfferStatus::Accepted,
                'accepted_at' => now(),
            ]);

            return $advance;
        });
    }

    public function approve(Advance $advance): Advance
    {
        $advance->update([
            'status' => AdvanceStatus::Approved,
            'approved_at' => now(),
        ]);

        return $advance;
    }

    public function requestDisbursement(Advance $advance, ?string $provider = null): Disbursement
    {
        return DB::transaction(function () use ($advance, $provider) {
            $advance->update(['status' => AdvanceStatus::Disbursing]);

            return $advance->disbursements()->create([
                'provider' => $provider,
                'amount_minor' => $advance->principal_amount_minor,
                'status' => DisbursementStatus::Processing,
                'requested_at' => now(),
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $providerResponse
     */
    public function markDisbursementSuccessful(
        Disbursement $disbursement,
        ?string $providerReference = null,
        array $providerResponse = [],
    ): Disbursement {
        return DB::transaction(function () use ($disbursement, $providerReference, $providerResponse) {
            $disbursement->update([
                'provider_reference' => $providerReference,
                'status' => DisbursementStatus::Successful,
                'completed_at' => now(),
                'provider_response' => $providerResponse,
            ]);

            $advance = $disbursement->advance;

            $advance->update([
                'status' => AdvanceStatus::Active,
                'disbursed_at' => now(),
                'repayment_started_at' => now(),
                'due_at' => now()->addDays($advance->maximum_repayment_days),
            ]);

            $this->ledger->principalDisbursed($advance, $disbursement, $advance->principal_amount_minor);

            if ($advance->fee_amount_minor > 0) {
                $this->ledger->feeAssessed($advance, $advance->fee_amount_minor);
            }

            return $disbursement;
        });
    }

    public function markDisbursementFailed(Disbursement $disbursement, string $reason): Disbursement
    {
        return DB::transaction(function () use ($disbursement, $reason) {
            $disbursement->update([
                'status' => DisbursementStatus::Failed,
                'failure_reason' => $reason,
            ]);

            $disbursement->advance->update(['status' => AdvanceStatus::Pending]);

            return $disbursement;
        });
    }

    /**
     * Adjust the repayment terms of an advance. The prior terms are preserved
     * and a new terms record is appended with an effective date and reason.
     *
     * @param  array<string, mixed>  $fallbackTerms
     */
    public function adjustRepaymentTerms(
        Advance $advance,
        int $recoveryRateBps,
        int $maximumRepaymentDays,
        string $reason,
        ?string $policyVersion = null,
        ?User $changedBy = null,
        array $fallbackTerms = [],
    ): AdvanceRepaymentTerm {
        return DB::transaction(function () use (
            $advance,
            $recoveryRateBps,
            $maximumRepaymentDays,
            $reason,
            $policyVersion,
            $changedBy,
            $fallbackTerms,
        ) {
            $advance->repaymentTerms()
                ->whereNull('effective_until')
                ->update(['effective_until' => now()]);

            $advance->update([
                'recovery_rate_bps' => $recoveryRateBps,
                'maximum_repayment_days' => $maximumRepaymentDays,
            ]);

            return $advance->repaymentTerms()->create([
                'recovery_rate_bps' => $recoveryRateBps,
                'maximum_repayment_days' => $maximumRepaymentDays,
                'fallback_terms' => $fallbackTerms,
                'effective_from' => now(),
                'adjustment_reason' => $reason,
                'policy_version' => $policyVersion,
                'changed_by_user_id' => $changedBy?->id,
            ]);
        });
    }
}
