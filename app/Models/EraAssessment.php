<?php

namespace App\Models;

use App\Enums\AssessmentStatus;
use App\Models\Concerns\HasPublicId;
use Database\Factories\EraAssessmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'public_id',
    'business_id',
    'model_version_id',
    'decision_policy_id',
    'assessment_status',
    'assessment_date',
    'forecast_period_days',
    'predicted_revenue_minor',
    'reliability_score',
    'recommended_advance_rate_bps',
    'recommended_advance_amount_minor',
    'recommended_recovery_rate_bps',
    'maximum_repayment_days',
    'score_breakdown',
    'reason_codes',
    'warnings',
    'input_snapshot',
    'output_snapshot',
    'requested_by_user_id',
])]
class EraAssessment extends Model
{
    /** @use HasFactory<EraAssessmentFactory> */
    use HasFactory;

    use HasPublicId;

    protected function casts(): array
    {
        return [
            'assessment_status' => AssessmentStatus::class,
            'assessment_date' => 'datetime',
            'forecast_period_days' => 'integer',
            'predicted_revenue_minor' => 'integer',
            'reliability_score' => 'decimal:4',
            'recommended_advance_rate_bps' => 'integer',
            'recommended_advance_amount_minor' => 'integer',
            'recommended_recovery_rate_bps' => 'integer',
            'maximum_repayment_days' => 'integer',
            'score_breakdown' => 'array',
            'reason_codes' => 'array',
            'warnings' => 'array',
            'input_snapshot' => 'array',
            'output_snapshot' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * @return BelongsTo<ModelVersion, $this>
     */
    public function modelVersion(): BelongsTo
    {
        return $this->belongsTo(ModelVersion::class);
    }

    /**
     * @return BelongsTo<DecisionPolicy, $this>
     */
    public function decisionPolicy(): BelongsTo
    {
        return $this->belongsTo(DecisionPolicy::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    /**
     * @return HasOne<AdvanceOffer, $this>
     */
    public function offer(): HasOne
    {
        return $this->hasOne(AdvanceOffer::class, 'assessment_id');
    }

    /**
     * @return HasMany<AdvanceOffer, $this>
     */
    public function offers(): HasMany
    {
        return $this->hasMany(AdvanceOffer::class, 'assessment_id');
    }
}
