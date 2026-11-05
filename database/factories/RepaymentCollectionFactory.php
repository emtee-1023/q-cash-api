<?php

namespace Database\Factories;

use App\Enums\CollectionMethod;
use App\Enums\CollectionStatus;
use App\Models\Business;
use App\Models\RepaymentCollection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RepaymentCollection>
 */
class RepaymentCollectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'source_transaction_id' => null,
            'collection_method' => CollectionMethod::RevenueDeduction->value,
            'provider' => 'Safaricom',
            'provider_reference' => fake()->unique()->uuid(),
            'amount_minor' => fake()->numberBetween(1000, 1000000),
            'currency' => 'KES',
            'status' => CollectionStatus::Pending->value,
            'idempotency_key' => fake()->unique()->uuid(),
            'collected_at' => null,
            'processed_at' => null,
            'provider_response' => [],
        ];
    }
}
