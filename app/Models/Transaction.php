<?php

namespace App\Models;

use App\Enums\TransactionDirection;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'business_id',
    'payment_account_id',
    'provider',
    'provider_transaction_id',
    'transaction_type',
    'direction',
    'amount_minor',
    'currency',
    'transaction_time',
    'provider_status',
    'counterparty_reference',
    'counterparty_name',
    'raw_payload',
    'ingested_at',
])]
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'direction' => TransactionDirection::class,
            'amount_minor' => 'integer',
            'transaction_time' => 'datetime',
            'ingested_at' => 'datetime',
            'raw_payload' => 'array',
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
     * @return BelongsTo<PaymentAccount, $this>
     */
    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class);
    }

    /**
     * @return HasMany<TransactionClassification, $this>
     */
    public function classifications(): HasMany
    {
        return $this->hasMany(TransactionClassification::class);
    }

    /**
     * @return HasOne<TransactionClassification, $this>
     */
    public function currentClassification(): HasOne
    {
        return $this->hasOne(TransactionClassification::class)->where('is_current', true);
    }

    /**
     * @return HasMany<RepaymentCollection, $this>
     */
    public function repaymentCollections(): HasMany
    {
        return $this->hasMany(RepaymentCollection::class, 'source_transaction_id');
    }
}
