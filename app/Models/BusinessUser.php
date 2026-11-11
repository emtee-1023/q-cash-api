<?php

namespace App\Models;

use App\Enums\BusinessUserRole;
use App\Enums\BusinessUserStatus;
use Database\Factories\BusinessUserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Concerns\AsPivot;

#[Fillable(['business_id', 'user_id', 'role', 'is_primary_contact', 'status'])]
class BusinessUser extends Model
{
    /** @use HasFactory<BusinessUserFactory> */
    use AsPivot;

    use HasFactory;

    protected $table = 'business_users';

    protected function casts(): array
    {
        return [
            'role' => BusinessUserRole::class,
            'is_primary_contact' => 'boolean',
            'status' => BusinessUserStatus::class,
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
