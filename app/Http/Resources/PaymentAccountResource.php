<?php

namespace App\Http\Resources;

use App\Models\PaymentAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PaymentAccount
 */
class PaymentAccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'business_id' => $this->business_id,
            'provider' => $this->provider,
            'channel_type' => $this->channel_type,
            'account_reference' => $this->account_reference,
            'account_name' => $this->account_name,
            'status' => $this->status?->value,
            'verified_at' => $this->verified_at,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
