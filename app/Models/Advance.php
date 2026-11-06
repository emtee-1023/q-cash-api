<?php

namespace App\Models;

use App\Enums\AdvanceStatus;
use App\Models\Concerns\HasPublicId;
use Database\Factories\AdvanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'public_id',
    'offer_id',
    'business_id',
    'financing_partner_id',
    'principal_amount_minor',
    'fee_amount_minor',
    'total_repayable_minor',
    'amount_repaid_minor',
    'outstanding_balance_minor',
    'recovery_rate_bps',
    'maximum_repayment_days',
    'approved_at',
    'disbursed_at',
    'repayment_started_at',
    'due_at',
    'settled_at',
    'status',
])]
class Advance extends Model
{
    /** @use HasFactory<AdvanceFactory> */
    use HasFactory;

    use HasPublicId;

    protected function casts(): array
    {
        return [
            'principal_amount_minor' => 'integer',
            'fee_amount_minor' => 'integer',
            'total_repayable_minor' => 'integer',
            'amount_repaid_minor' => 'integer',
            'outstanding_balance_minor' => 'integer',
            'recovery_rate_bps' => 'integer',
            'maximum_repayment_days' => 'integer',
            'approved_at' => 'datetime',
            'disbursed_at' => 'datetime',
            'repayment_started_at' => 'datetime',
            'due_at' => 'datetime',
            'settled_at' => 'datetime',
            'status' => AdvanceStatus::class,
        ];
    }

    /**
     * @return BelongsTo<AdvanceOffer, $this>
     */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(AdvanceOffer::class, 'offer_id');
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * @return BelongsTo<FinancingPartner, $this>
     */
    public function financingPartner(): BelongsTo
    {
        return $this->belongsTo(FinancingPartner::class);
    }

    /**
     * @return HasMany<Disbursement, $this>
     */
    public function disbursements(): HasMany
    {
        return $this->hasMany(Disbursement::class);
    }

    /**
     * @return HasMany<RepaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(RepaymentAllocation::class);
    }

    /**
     * @return HasMany<AdvanceLedgerEntry, $this>
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(AdvanceLedgerEntry::class);
    }

    /**
     * @return HasMany<AdvanceRepaymentTerm, $this>
     */
    public function repaymentTerms(): HasMany
    {
        return $this->hasMany(AdvanceRepaymentTerm::class);
    }

    /**
     * @return HasOne<AdvanceRepaymentTerm, $this>
     */
    public function currentRepaymentTerm(): HasOne
    {
        return $this->hasOne(AdvanceRepaymentTerm::class)
            ->whereNull('effective_until')
            ->latestOfMany('effective_from');
    }
}
