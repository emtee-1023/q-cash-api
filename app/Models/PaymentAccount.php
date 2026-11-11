<?php

namespace App\Models;

use App\Enums\PaymentAccountStatus;
use Database\Factories\PaymentAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'business_id',
    'provider',
    'channel_type',
    'account_reference',
    'account_name',
    'status',
    'verified_at',
    'metadata',
])]
class PaymentAccount extends Model
{
    /** @use HasFactory<PaymentAccountFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => PaymentAccountStatus::class,
            'verified_at' => 'datetime',
            'metadata' => 'array',
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
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
