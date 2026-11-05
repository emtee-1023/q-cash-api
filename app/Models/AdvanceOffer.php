<?php

namespace App\Models;

use App\Enums\OfferStatus;
use App\Models\Concerns\HasPublicId;
use Database\Factories\AdvanceOfferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'public_id',
    'assessment_id',
    'business_id',
    'offered_amount_minor',
    'fee_amount_minor',
    'total_repayable_minor',
    'recovery_rate_bps',
    'maximum_repayment_days',
    'offer_terms',
    'status',
    'offered_at',
    'expires_at',
    'accepted_at',
    'rejected_at',
])]
class AdvanceOffer extends Model
{
    /** @use HasFactory<AdvanceOfferFactory> */
    use HasFactory;

    use HasPublicId;

    protected function casts(): array
    {
        return [
            'offered_amount_minor' => 'integer',
            'fee_amount_minor' => 'integer',
            'total_repayable_minor' => 'integer',
            'recovery_rate_bps' => 'integer',
            'maximum_repayment_days' => 'integer',
            'offer_terms' => 'array',
            'status' => OfferStatus::class,
            'offered_at' => 'datetime',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<EraAssessment, $this>
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(EraAssessment::class, 'assessment_id');
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * @return HasOne<Advance, $this>
     */
    public function advance(): HasOne
    {
        return $this->hasOne(Advance::class, 'offer_id');
    }
}
