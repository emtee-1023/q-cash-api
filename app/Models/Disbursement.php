<?php

namespace App\Models;

use App\Enums\DisbursementStatus;
use Database\Factories\DisbursementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'advance_id',
    'provider',
    'provider_reference',
    'amount_minor',
    'status',
    'requested_at',
    'completed_at',
    'failure_reason',
    'provider_response',
])]
class Disbursement extends Model
{
    /** @use HasFactory<DisbursementFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'status' => DisbursementStatus::class,
            'requested_at' => 'datetime',
            'completed_at' => 'datetime',
            'provider_response' => 'array',
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
     * @return HasMany<AdvanceLedgerEntry, $this>
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(AdvanceLedgerEntry::class);
    }
}
