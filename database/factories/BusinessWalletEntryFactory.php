<?php

namespace Database\Factories;

use App\Enums\BusinessWalletEntryType;
use App\Models\BusinessWallet;
use App\Models\BusinessWalletEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessWalletEntry>
 */
class BusinessWalletEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_wallet_id' => BusinessWallet::factory(),
            'transaction_id' => null,
            'withdrawal_id' => null,
            'entry_type' => BusinessWalletEntryType::Credit->value,
            'amount_minor' => 1000,
            'balance_after_minor' => 1000,
            'description' => 'Test wallet entry',
            'reference' => null,
        ];
    }
}
