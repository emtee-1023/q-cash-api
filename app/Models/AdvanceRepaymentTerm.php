<?php

namespace App\Models;

use Database\Factories\AdvanceRepaymentTermFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'advance_id',
    'recovery_rate_bps',
    'maximum_repayment_days',
    'fallback_terms',
    'effective_from',
    'effective_until',
    'adjustment_reason',
    'policy_version',
    'changed_by_user_id',
])]
class AdvanceRepaymentTerm extends Model
{
    /** @use HasFactory<AdvanceRepaymentTermFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'recovery_rate_bps' => 'integer',
            'maximum_repayment_days' => 'integer',
            'fallback_terms' => 'array',
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Advance, $this>
     */
    public function advance(): BelongsTo
    {
        return $this->belongsTo(Advance::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
