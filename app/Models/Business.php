<?php

namespace App\Models;

use App\Enums\BusinessStatus;
use App\Models\Concerns\HasPublicId;
use App\Support\PhoneNumber;
use Database\Factories\BusinessFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'public_id',
    'business_name',
    'business_type',
    'business_category',
    'registration_number',
    'phone_number',
    'email',
    'county',
    'town',
    'physical_address',
    'operating_since',
    'status',
])]
class Business extends Model
{
    /** @use HasFactory<BusinessFactory> */
    use HasFactory;

    use HasPublicId;
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'operating_since' => 'date',
            'status' => BusinessStatus::class,
        ];
    }

    protected function phoneNumber(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value === null || $value === ''
                ? null
                : PhoneNumber::normalize($value),
        );
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'business_users')
            ->using(BusinessUser::class)
            ->withPivot('role', 'is_primary_contact', 'status')
            ->withTimestamps();
    }

    /**
     * @return HasMany<BusinessUser, $this>
     */
    public function businessUsers(): HasMany
    {
        return $this->hasMany(BusinessUser::class);
    }

    /**
     * @return HasMany<PaymentAccount, $this>
     */
    public function paymentAccounts(): HasMany
    {
        return $this->hasMany(PaymentAccount::class);
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * @return HasMany<EraAssessment, $this>
     */
    public function assessments(): HasMany
    {
        return $this->hasMany(EraAssessment::class);
    }

    /**
     * @return HasMany<AdvanceOffer, $this>
     */
    public function offers(): HasMany
    {
        return $this->hasMany(AdvanceOffer::class);
    }

    /**
     * @return HasMany<Advance, $this>
     */
    public function advances(): HasMany
    {
        return $this->hasMany(Advance::class);
    }

    /**
     * @return HasMany<RepaymentCollection, $this>
     */
    public function repaymentCollections(): HasMany
    {
        return $this->hasMany(RepaymentCollection::class);
    }
}
