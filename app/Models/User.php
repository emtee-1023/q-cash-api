<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\IdentificationType;
use App\Support\PhoneNumber;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'phone_number', 'phone_verified_at', 'id_type', 'identification_number', 'date_of_birth'])]
#[Hidden(['password', 'remember_token', 'is_admin', 'admin_activated_at'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'id_type' => IdentificationType::class,
            'date_of_birth' => 'date',
            'is_admin' => 'boolean',
            'admin_activated_at' => 'datetime',
        ];
    }

    public function isActivatedAdmin(): bool
    {
        return $this->is_admin && $this->admin_activated_at !== null;
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
     * @return BelongsToMany<Business, $this>
     */
    public function businesses(): BelongsToMany
    {
        return $this->belongsToMany(Business::class, 'business_users')
            ->using(BusinessUser::class)
            ->withPivot('role', 'is_primary_contact', 'status')
            ->withTimestamps();
    }

    /**
     * @return HasMany<EmailVerificationCode, $this>
     */
    public function emailVerificationCodes(): HasMany
    {
        return $this->hasMany(EmailVerificationCode::class);
    }

    /**
     * @return HasMany<BusinessUser, $this>
     */
    public function businessUsers(): HasMany
    {
        return $this->hasMany(BusinessUser::class);
    }
}
