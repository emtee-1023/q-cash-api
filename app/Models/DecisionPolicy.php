<?php

namespace App\Models;

use App\Enums\PolicyStatus;
use Database\Factories\DecisionPolicyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'policy_name',
    'version',
    'scoring_weights',
    'scoring_bands',
    'advance_rules',
    'repayment_rules',
    'eligibility_rules',
    'effective_from',
    'effective_until',
    'status',
])]
class DecisionPolicy extends Model
{
    /** @use HasFactory<DecisionPolicyFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'scoring_weights' => 'array',
            'scoring_bands' => 'array',
            'advance_rules' => 'array',
            'repayment_rules' => 'array',
            'eligibility_rules' => 'array',
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
            'status' => PolicyStatus::class,
        ];
    }

    /**
     * @return HasMany<EraAssessment, $this>
     */
    public function assessments(): HasMany
    {
        return $this->hasMany(EraAssessment::class);
    }
}
