<?php

namespace App\Models;

use App\Enums\BusinessKycStatus;
use App\Enums\BusinessStatus;
use App\Models\Concerns\HasPublicId;
use App\Support\PhoneNumber;
use Database\Factories\BusinessFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'public_id',
    'business_name',
    'business_type_id',
    'registration_number',
    'phone_number',
    'email',
    'has_physical_location',
    'county_id',
    'sub_county_id',
    'ward_id',
    'physical_address',
    'operating_since',
    'status',
    'kyc_status',
])]
class Business extends Model
{
    /** @use HasFactory<BusinessFactory> */
    use HasFactory;

    use HasPublicId;
    use SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'kyc_status' => 'unverified',
    ];

    protected static function booted(): void
    {
        static::creating(function (Business $business): void {
            if (blank($business->collection_account_number)) {
                $business->collection_account_number = static::generateCollectionAccountNumber();
            }
        });
    }

    protected static function generateCollectionAccountNumber(): string
    {
        do {
            $accountNumber = (string) random_int(100000, 999999);
        } while (static::withTrashed()->where('collection_account_number', $accountNumber)->exists());

        return $accountNumber;
    }

    protected function casts(): array
    {
        return [
            'operating_since' => 'date',
            'status' => BusinessStatus::class,
            'kyc_status' => BusinessKycStatus::class,
            'has_physical_location' => 'boolean',
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
     * @return BelongsTo<BusinessType, $this>
     */
    public function businessType(): BelongsTo
    {
        return $this->belongsTo(BusinessType::class);
    }

    /**
     * @return BelongsTo<County, $this>
     */
    public function countyLocation(): BelongsTo
    {
        return $this->belongsTo(County::class, 'county_id');
    }

    /**
     * @return BelongsTo<SubCounty, $this>
     */
    public function subCounty(): BelongsTo
    {
        return $this->belongsTo(SubCounty::class, 'sub_county_id');
    }

    /**
     * @return BelongsTo<Ward, $this>
     */
    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class, 'ward_id');
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
