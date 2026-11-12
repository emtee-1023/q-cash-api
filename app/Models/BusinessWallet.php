<?php

namespace App\Models;

use Database\Factories\BusinessWalletFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['business_id', 'balance_minor', 'currency'])]
class BusinessWallet extends Model
{
    /** @use HasFactory<BusinessWalletFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'balance_minor' => 'integer',
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
    public function entries(): HasMany
    {
        return $this->hasMany(BusinessWalletEntry::class);
    }
}
