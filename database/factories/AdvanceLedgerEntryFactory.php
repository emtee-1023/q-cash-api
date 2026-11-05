<?php

namespace Database\Factories;

use App\Enums\LedgerEntryType;
use App\Models\Advance;
use App\Models\AdvanceLedgerEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdvanceLedgerEntry>
 */
class AdvanceLedgerEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'advance_id' => Advance::factory(),
            'repayment_allocation_id' => null,
            'disbursement_id' => null,
            'entry_type' => LedgerEntryType::Repayment->value,
            'amount_minor' => fake()->numberBetween(1000, 1000000),
            'description' => null,
            'reference' => null,
        ];
    }
}
