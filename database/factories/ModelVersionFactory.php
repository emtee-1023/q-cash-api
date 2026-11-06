<?php

namespace Database\Factories;

use App\Enums\ModelType;
use App\Models\ModelVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModelVersion>
 */
class ModelVersionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'model_name' => 'era_revenue_regressor',
            'model_type' => ModelType::RevenueRegressor->value,
            'version' => fake()->unique()->semver(),
            'artifact_uri' => fake()->optional()->url(),
            'evaluation_metrics' => [],
            'training_data_description' => [],
            'trained_at' => now(),
            'is_active' => false,
        ];
    }
}
