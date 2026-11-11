<?php

namespace App\Models;

use App\Enums\PartnerType;
use App\Support\PhoneNumber;
use Database\Factories\FinancingPartnerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
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

    protected function contactPhone(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value === null || $value === ''
                ? null
                : PhoneNumber::normalize($value),
        );
    }

    /**
     * @return HasMany<Advance, $this>
     */
    public function advances(): HasMany
    {
        return $this->hasMany(Advance::class);
    }
}
