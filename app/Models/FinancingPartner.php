<?php

namespace App\Models;

use App\Enums\PartnerType;
use Database\Factories\FinancingPartnerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'partner_type', 'contact_email', 'contact_phone', 'status', 'integration_config'])]
class FinancingPartner extends Model
{
    /** @use HasFactory<FinancingPartnerFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'partner_type' => PartnerType::class,
            'integration_config' => 'array',
        ];
    }

    /**
     * @return HasMany<Advance, $this>
     */
    public function advances(): HasMany
    {
        return $this->hasMany(Advance::class);
    }
}
