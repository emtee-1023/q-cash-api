<?php

namespace App\Http\Resources;

use App\Models\Advance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Advance
 */
class AdvanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'offer_id' => $this->offer_id,
            'business_id' => $this->business_id,
            'financing_partner_id' => $this->financing_partner_id,
            'principal_amount_minor' => $this->principal_amount_minor,
            'fee_amount_minor' => $this->fee_amount_minor,
            'total_repayable_minor' => $this->total_repayable_minor,
            'amount_repaid_minor' => $this->amount_repaid_minor,
            'outstanding_balance_minor' => $this->outstanding_balance_minor,
            'recovery_rate_bps' => $this->recovery_rate_bps,
            'maximum_repayment_days' => $this->maximum_repayment_days,
            'approved_at' => $this->approved_at,
            'disbursed_at' => $this->disbursed_at,
            'repayment_started_at' => $this->repayment_started_at,
            'due_at' => $this->due_at,
            'settled_at' => $this->settled_at,
            'status' => $this->status?->value,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
