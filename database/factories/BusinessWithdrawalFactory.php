<?php

namespace Database\Factories;

use App\Enums\BusinessWithdrawalStatus;
use App\Models\Business;
use App\Models\BusinessWithdrawal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessWithdrawal>
 */
class BusinessWithdrawalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'amount_minor' => 1000,
            'currency' => 'KES',
            'recipient_phone_number' => '254712345678',
            'status' => BusinessWithdrawalStatus::Processing->value,
            'provider' => 'mpesa_b2c',
            'provider_reference' => fake()->unique()->uuid(),
            'originator_conversation_id' => fake()->unique()->uuid(),
            'requested_at' => now(),
        ];
    }

    public function successful(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BusinessWithdrawalStatus::Successful->value,
            'result_code' => '0',
            'result_desc' => 'The service request is processed successfully.',
            'completed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BusinessWithdrawalStatus::Failed->value,
            'result_code' => '1',
            'result_desc' => 'The balance is insufficient for the transaction.',
            'failure_reason' => 'The balance is insufficient for the transaction.',
            'completed_at' => now(),
        ]);
    }
}
