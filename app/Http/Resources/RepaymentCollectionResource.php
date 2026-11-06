<?php

namespace App\Http\Resources;

use App\Models\RepaymentCollection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RepaymentCollection
 */
class RepaymentCollectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'business_id' => $this->business_id,
            'source_transaction_id' => $this->source_transaction_id,
            'collection_method' => $this->collection_method?->value,
            'provider' => $this->provider,
            'provider_reference' => $this->provider_reference,
            'amount_minor' => $this->amount_minor,
            'currency' => $this->currency,
            'status' => $this->status?->value,
            'idempotency_key' => $this->idempotency_key,
            'collected_at' => $this->collected_at,
            'processed_at' => $this->processed_at,
            'allocations' => RepaymentAllocationResource::collection($this->whenLoaded('allocations')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
