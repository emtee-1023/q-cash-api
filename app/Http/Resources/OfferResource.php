<?php

namespace App\Http\Resources;

use App\Models\AdvanceOffer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AdvanceOffer
 */
class OfferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'assessment_id' => $this->assessment_id,
            'business_id' => $this->business_id,
            'offered_amount_minor' => $this->offered_amount_minor,
            'fee_amount_minor' => $this->fee_amount_minor,
            'total_repayable_minor' => $this->total_repayable_minor,
            'recovery_rate_bps' => $this->recovery_rate_bps,
            'maximum_repayment_days' => $this->maximum_repayment_days,
            'offer_terms' => $this->offer_terms,
            'status' => $this->status?->value,
            'offered_at' => $this->offered_at,
            'expires_at' => $this->expires_at,
            'accepted_at' => $this->accepted_at,
            'rejected_at' => $this->rejected_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
