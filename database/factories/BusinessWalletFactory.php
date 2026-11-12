<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\BusinessWallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessWallet>
 */
class BusinessWalletFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'balance_minor' => 0,
            'currency' => 'KES',
        ];
    }
}
