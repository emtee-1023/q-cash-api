<?php

namespace App\Http\Resources;

use App\Models\BusinessWithdrawal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BusinessWithdrawal
 */
class BusinessWithdrawalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'business_id' => $this->business_id,
            'amount_minor' => $this->amount_minor,
            'currency' => $this->currency,
            'recipient_phone_number' => $this->recipient_phone_number,
            'status' => $this->status?->value,
            'provider' => $this->provider,
            'provider_reference' => $this->provider_reference,
            'originator_conversation_id' => $this->originator_conversation_id,
            'result_code' => $this->result_code,
            'result_desc' => $this->result_desc,
            'failure_reason' => $this->failure_reason,
            'requested_at' => $this->requested_at,
            'completed_at' => $this->completed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
