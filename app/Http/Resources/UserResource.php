<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at,
            'phone_number' => $this->phone_number,
            'phone_verified_at' => $this->phone_verified_at,
            'id_type' => $this->id_type?->value,
            'identification_number' => $this->identification_number,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
