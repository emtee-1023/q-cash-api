<?php

namespace App\Models;

use App\Enums\StkPushStatus;
use Database\Factories\StkPushRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'business_id',
    'merchant_request_id',
    'checkout_request_id',
    'phone_number',
    'amount_minor',
    'currency',
    'account_reference',
    'transaction_desc',
    'status',
    'result_code',
    'result_desc',
    'transaction_id',
    'raw_response',
    'raw_callback',
    'initiated_at',
    'completed_at',
])]
class StkPushRequest extends Model
{
    /** @use HasFactory<StkPushRequestFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'status' => StkPushStatus::class,
            'raw_response' => 'array',
            'raw_callback' => 'array',
            'initiated_at' => 'datetime',
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
     * @return BelongsTo<Transaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
