<?php

namespace App\Http\Resources;

use App\Models\AdvanceLedgerEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AdvanceLedgerEntry
 */
class AdvanceLedgerEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'advance_id' => $this->advance_id,
            'repayment_allocation_id' => $this->repayment_allocation_id,
            'disbursement_id' => $this->disbursement_id,
            'entry_type' => $this->entry_type?->value,
            'amount_minor' => $this->amount_minor,
            'description' => $this->description,
            'reference' => $this->reference,
            'created_at' => $this->created_at,
        ];
    }
}
