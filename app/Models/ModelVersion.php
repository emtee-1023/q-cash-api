<?php

namespace App\Models;

use App\Enums\ModelType;
use Database\Factories\ModelVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'model_name',
    'model_type',
    'version',
    'artifact_uri',
    'evaluation_metrics',
    'training_data_description',
    'trained_at',
    'is_active',
])]
class ModelVersion extends Model
{
    /** @use HasFactory<ModelVersionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'model_type' => ModelType::class,
            'evaluation_metrics' => 'array',
            'training_data_description' => 'array',
            'trained_at' => 'datetime',
            'is_active' => 'boolean',
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
