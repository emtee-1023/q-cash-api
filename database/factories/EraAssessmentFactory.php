<?php

namespace Database\Factories;

use App\Enums\AssessmentStatus;
use App\Models\Business;
use App\Models\EraAssessment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EraAssessment>
 */
class EraAssessmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'model_version_id' => null,
            'decision_policy_id' => null,
            'assessment_status' => AssessmentStatus::Eligible->value,
            'assessment_date' => now(),
            'forecast_period_days' => 30,
            'predicted_revenue_minor' => 10000000,
            'reliability_score' => '69.7400',
            'recommended_advance_rate_bps' => 3000,
            'recommended_advance_amount_minor' => 3000000,
            'recommended_recovery_rate_bps' => 1000,
            'maximum_repayment_days' => 30,
            'score_breakdown' => [],
            'reason_codes' => [],
            'warnings' => [],
            'input_snapshot' => [],
            'output_snapshot' => [],
            'requested_by_user_id' => null,
        ];
    }
}
