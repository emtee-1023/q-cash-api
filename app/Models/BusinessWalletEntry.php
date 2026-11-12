<?php

namespace App\Models;

use App\Enums\BusinessWalletEntryType;
use Database\Factories\BusinessWalletEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'business_wallet_id',
    'transaction_id',
    'withdrawal_id',
    'entry_type',
    'amount_minor',
    'balance_after_minor',
    'description',
    'reference',
])]
class BusinessWalletEntry extends Model
{
    /** @use HasFactory<BusinessWalletEntryFactory> */
    use HasFactory;

    /**
     * The wallet ledger is append-only; there is no updated_at column.
     */
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'entry_type' => BusinessWalletEntryType::class,
            'amount_minor' => 'integer',
            'balance_after_minor' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<BusinessWallet, $this>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(BusinessWallet::class, 'business_wallet_id');
    }

    /**
     * @return BelongsTo<Transaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * @return BelongsTo<BusinessWithdrawal, $this>
     */
    public function withdrawal(): BelongsTo
    {
        return $this->belongsTo(BusinessWithdrawal::class, 'withdrawal_id');
    }
}
