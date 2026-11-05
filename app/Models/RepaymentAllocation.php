<?php

namespace App\Models;

use Database\Factories\RepaymentAllocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['repayment_collection_id', 'advance_id', 'amount_minor'])]
class RepaymentAllocation extends Model
{
    /** @use HasFactory<RepaymentAllocationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<RepaymentCollection, $this>
     */
    public function collection(): BelongsTo
    {
        return $this->belongsTo(RepaymentCollection::class, 'repayment_collection_id');
    }

    /**
     * @return BelongsTo<Advance, $this>
     */
    public function advance(): BelongsTo
    {
        return $this->belongsTo(Advance::class);
    }

    /**
     * @return HasMany<AdvanceLedgerEntry, $this>
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(AdvanceLedgerEntry::class);
    }
}
