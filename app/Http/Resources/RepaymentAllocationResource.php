<?php

namespace App\Http\Resources;

use App\Models\RepaymentAllocation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RepaymentAllocation
 */
class RepaymentAllocationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'repayment_collection_id' => $this->repayment_collection_id,
            'advance_id' => $this->advance_id,
            'amount_minor' => $this->amount_minor,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
