<?php

namespace Database\Factories;

use App\Enums\TransactionDirection;
use App\Models\Business;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'payment_account_id' => null,
            'provider' => 'Safaricom',
            'provider_transaction_id' => fake()->unique()->uuid(),
            'transaction_type' => null,
            'direction' => TransactionDirection::Inflow->value,
            'amount_minor' => fake()->numberBetween(1000, 10000000),
            'currency' => 'KES',
            'transaction_time' => now(),
            'provider_status' => 'completed',
            'counterparty_reference' => fake()->optional()->numerify('REF-#####'),
            'counterparty_name' => fake()->optional()->name(),
            'raw_payload' => [],
            'ingested_at' => now(),
        ];
    }
}
