<?php

namespace App\Models;

use App\Enums\AnomalyStatus;
use App\Enums\TransactionCategory;
use Database\Factories\TransactionClassificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'transaction_id',
    'classifier_version',
    'category',
    'revenue_eligible',
    'anomaly_status',
    'confidence',
    'reason_codes',
    'is_current',
    'classified_at',
])]
class TransactionClassification extends Model
{
    /** @use HasFactory<TransactionClassificationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'category' => TransactionCategory::class,
            'revenue_eligible' => 'boolean',
            'anomaly_status' => AnomalyStatus::class,
            'confidence' => 'decimal:5',
            'reason_codes' => 'array',
            'is_current' => 'boolean',
            'classified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Transaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
