<?php

namespace App\Http\Resources\Admin;

use App\Models\Advance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Advance
 */
class AdvanceLendingResource extends JsonResource
{
    public function __construct(Advance $resource, private readonly int $aprBps)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'business_id' => $this->business_id,
            'principal_amount_minor' => $this->principal_amount_minor,
            'fee_amount_minor' => $this->fee_amount_minor,
            'total_repayable_minor' => $this->total_repayable_minor,
            'amount_repaid_minor' => $this->amount_repaid_minor,
            'fee_repaid_minor' => $this->fee_repaid_minor,
            'principal_repaid_minor' => $this->principal_repaid_minor,
            'outstanding_balance_minor' => $this->outstanding_balance_minor,
            'recovery_rate_bps' => $this->recovery_rate_bps,
            'maximum_repayment_days' => $this->maximum_repayment_days,
            'apr_bps' => $this->aprBps,
            'status' => $this->status?->value,
            'disbursed_at' => $this->disbursed_at,
            'due_at' => $this->due_at,
            'settled_at' => $this->settled_at,
            'created_at' => $this->created_at,
        ];
    }
}
