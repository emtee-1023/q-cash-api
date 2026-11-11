<?php

namespace Database\Factories;

use App\Enums\PartnerType;
use App\Models\FinancingPartner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancingPartner>
 */
class FinancingPartnerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'partner_type' => PartnerType::Bank->value,
            'contact_email' => fake()->safeEmail(),
            'contact_phone' => '254'.fake()->numerify('7########'),
            'status' => 'active',
            'integration_config' => [],
        ];
    }
}
