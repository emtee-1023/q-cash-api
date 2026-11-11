<?php

namespace App\Http\Resources;

use App\Models\Business;
use Illuminate\Http\Request;

/**
 * @mixin Business
 */
class AdminBusinessDetailResource extends BusinessDetailResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'business_users' => $this->whenLoaded(
                'businessUsers',
                fn () => $this->businessUsers->map(fn ($businessUser) => [
                    'id' => $businessUser->id,
                    'user_id' => $businessUser->user_id,
                    'name' => $businessUser->user?->name,
                    'email' => $businessUser->user?->email,
                    'phone_number' => $businessUser->user?->phone_number,
                    'role' => $businessUser->role?->value,
                    'is_primary_contact' => $businessUser->is_primary_contact,
                    'status' => $businessUser->status,
                ])->values(),
            ),
            'payment_accounts' => $this->whenLoaded(
                'paymentAccounts',
                fn () => PaymentAccountResource::collection($this->paymentAccounts),
            ),
        ];
    }
}
