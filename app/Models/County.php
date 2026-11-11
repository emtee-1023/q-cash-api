<?php

namespace App\Models;

use Database\Factories\CountyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'slug', 'capital', 'region', 'iso_code', 'postal_code'])]
class County extends Model
{
    /** @use HasFactory<CountyFactory> */
    use HasFactory;

    /**
     * @return HasMany<SubCounty, $this>
     */
    public function subCounties(): HasMany
    {
        return $this->hasMany(SubCounty::class);
    }
}
