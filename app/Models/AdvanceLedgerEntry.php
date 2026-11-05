<?php

namespace App\Models;

use App\Enums\LedgerEntryType;
use Database\Factories\AdvanceLedgerEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'advance_id',
    'repayment_allocation_id',
    'disbursement_id',
    'entry_type',
    'amount_minor',
    'description',
    'reference',
])]
class AdvanceLedgerEntry extends Model
{
    /** @use HasFactory<AdvanceLedgerEntryFactory> */
    use HasFactory;

    /**
     * The ledger is append-only; there is no updated_at column.
     */
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'entry_type' => LedgerEntryType::class,
            'amount_minor' => 'integer',
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
     * @return BelongsTo<RepaymentAllocation, $this>
     */
    public function repaymentAllocation(): BelongsTo
    {
        return $this->belongsTo(RepaymentAllocation::class);
    }

    /**
     * @return BelongsTo<Disbursement, $this>
     */
    public function disbursement(): BelongsTo
    {
        return $this->belongsTo(Disbursement::class);
    }
}
