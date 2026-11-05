<?php

namespace App\Models;

use App\Enums\CollectionMethod;
use App\Enums\CollectionStatus;
use App\Models\Concerns\HasPublicId;
use Database\Factories\RepaymentCollectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'public_id',
    'business_id',
    'source_transaction_id',
    'collection_method',
    'provider',
    'provider_reference',
    'amount_minor',
    'currency',
    'status',
    'idempotency_key',
    'collected_at',
    'processed_at',
    'provider_response',
])]
class RepaymentCollection extends Model
{
    /** @use HasFactory<RepaymentCollectionFactory> */
    use HasFactory;

    use HasPublicId;

    protected function casts(): array
    {
        return [
            'collection_method' => CollectionMethod::class,
            'amount_minor' => 'integer',
            'status' => CollectionStatus::class,
            'collected_at' => 'datetime',
            'processed_at' => 'datetime',
            'provider_response' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * @return BelongsTo<Transaction, $this>
     */
    public function sourceTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'source_transaction_id');
    }

    /**
     * @return HasMany<RepaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(RepaymentAllocation::class);
    }
}
