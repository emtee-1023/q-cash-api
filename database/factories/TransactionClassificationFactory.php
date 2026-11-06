<?php

namespace Database\Factories;

use App\Enums\AnomalyStatus;
use App\Enums\TransactionCategory;
use App\Models\Transaction;
use App\Models\TransactionClassification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransactionClassification>
 */
class TransactionClassificationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'transaction_id' => Transaction::factory(),
            'classifier_version' => 'v1.0.0',
            'category' => TransactionCategory::Sale->value,
            'revenue_eligible' => true,
            'anomaly_status' => AnomalyStatus::Normal->value,
            'confidence' => '0.98000',
            'reason_codes' => [],
            'is_current' => true,
            'classified_at' => now(),
        ];
    }
}
