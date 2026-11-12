<?php

namespace Database\Factories;

use App\Enums\StkPushStatus;
use App\Models\Business;
use App\Models\StkPushRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StkPushRequest>
 */
class StkPushRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'merchant_request_id' => fake()->uuid(),
            'checkout_request_id' => fake()->unique()->uuid(),
            'phone_number' => '254712345678',
            'amount_minor' => 1000,
            'currency' => 'KES',
            'account_reference' => fake()->numerify('######'),
            'transaction_desc' => 'QCash payment',
            'status' => StkPushStatus::Pending->value,
            'initiated_at' => now(),
        ];
    }

    public function successful(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StkPushStatus::Successful->value,
            'result_code' => '0',
            'result_desc' => 'The service request is processed successfully.',
            'completed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StkPushStatus::Failed->value,
            'result_code' => '1',
            'result_desc' => 'The balance is insufficient for the transaction.',
            'completed_at' => now(),
        ]);
    }
}
