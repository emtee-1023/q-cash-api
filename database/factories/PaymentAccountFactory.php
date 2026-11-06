<?php

namespace Database\Factories;

use App\Enums\PaymentAccountStatus;
use App\Models\Business;
use App\Models\PaymentAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentAccount>
 */
class PaymentAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'provider' => 'Safaricom',
            'channel_type' => 'paybill',
            'account_number' => fake()->unique()->numerify('######'),
            'account_reference' => null,
            'account_name' => fake()->company(),
            'status' => PaymentAccountStatus::Pending->value,
            'verified_at' => null,
            'metadata' => [],
        ];
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentAccountStatus::Active->value,
            'verified_at' => now(),
        ]);
    }
}
