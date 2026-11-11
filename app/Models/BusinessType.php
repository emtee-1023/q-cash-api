<?php

namespace App\Models;

use Database\Factories\BusinessTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'category', 'description', 'average_monthly_revenue_minor', 'is_active'])]
class BusinessType extends Model
{
    /** @use HasFactory<BusinessTypeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'average_monthly_revenue_minor' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Business, $this>
     */
    public function businesses(): HasMany
    {
        return $this->hasMany(Business::class);
    }
}
