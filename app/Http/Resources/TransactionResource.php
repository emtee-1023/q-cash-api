<?php

namespace App\Http\Resources;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Transaction
 */
class TransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'business_id' => $this->business_id,
            'payment_account_id' => $this->payment_account_id,
            'provider' => $this->provider,
            'provider_transaction_id' => $this->provider_transaction_id,
            'transaction_type' => $this->transaction_type,
            'direction' => $this->direction?->value,
            'amount_minor' => $this->amount_minor,
            'currency' => $this->currency,
            'transaction_time' => $this->transaction_time,
            'provider_status' => $this->provider_status,
            'counterparty_reference' => $this->counterparty_reference,
            'counterparty_name' => $this->counterparty_name,
            'raw_payload' => $this->raw_payload,
            'ingested_at' => $this->ingested_at,
            'classification' => $this->whenLoaded('currentClassification', fn () => [
                'category' => $this->currentClassification->category?->value,
                'revenue_eligible' => $this->currentClassification->revenue_eligible,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
