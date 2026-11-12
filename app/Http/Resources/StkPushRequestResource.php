<?php

namespace App\Http\Resources;

use App\Models\StkPushRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StkPushRequest
 */
class StkPushRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'business_id' => $this->business_id,
            'merchant_request_id' => $this->merchant_request_id,
            'checkout_request_id' => $this->checkout_request_id,
            'phone_number' => $this->phone_number,
            'amount_minor' => $this->amount_minor,
            'currency' => $this->currency,
            'account_reference' => $this->account_reference,
            'transaction_desc' => $this->transaction_desc,
            'status' => $this->status?->value,
            'result_code' => $this->result_code,
            'result_desc' => $this->result_desc,
            'transaction_id' => $this->transaction_id,
            'initiated_at' => $this->initiated_at,
            'completed_at' => $this->completed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
