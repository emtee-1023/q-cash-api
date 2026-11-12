<?php

namespace App\Models;

use App\Enums\BusinessWithdrawalStatus;
use Database\Factories\BusinessWithdrawalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'business_id',
    'amount_minor',
    'currency',
    'recipient_phone_number',
    'status',
    'provider',
    'provider_reference',
    'originator_conversation_id',
    'result_code',
    'result_desc',
    'failure_reason',
    'provider_response',
    'requested_at',
    'completed_at',
])]
class BusinessWithdrawal extends Model
{
    /** @use HasFactory<BusinessWithdrawalFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'status' => BusinessWithdrawalStatus::class,
            'provider_response' => 'array',
            'requested_at' => 'datetime',
            'completed_at' => 'datetime',
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
     * @return HasMany<BusinessWalletEntry, $this>
     */
    public function walletEntries(): HasMany
    {
        return $this->hasMany(BusinessWalletEntry::class, 'withdrawal_id');
    }
}
