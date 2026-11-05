<?php

namespace App\Http\Resources;

use App\Models\EraAssessment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EraAssessment
 */
class AssessmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'business_id' => $this->business_id,
            'model_version_id' => $this->model_version_id,
            'decision_policy_id' => $this->decision_policy_id,
            'assessment_status' => $this->assessment_status?->value,
            'assessment_date' => $this->assessment_date,
            'forecast_period_days' => $this->forecast_period_days,
            'predicted_revenue_minor' => $this->predicted_revenue_minor,
            'reliability_score' => $this->reliability_score,
            'recommended_advance_rate_bps' => $this->recommended_advance_rate_bps,
            'recommended_advance_amount_minor' => $this->recommended_advance_amount_minor,
            'recommended_recovery_rate_bps' => $this->recommended_recovery_rate_bps,
            'maximum_repayment_days' => $this->maximum_repayment_days,
            'score_breakdown' => $this->score_breakdown,
            'reason_codes' => $this->reason_codes,
            'warnings' => $this->warnings,
            'requested_by_user_id' => $this->requested_by_user_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
