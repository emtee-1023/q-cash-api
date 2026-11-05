<?php

namespace App\Http\Resources;

use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Business
 */
class BusinessResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'business_name' => $this->business_name,
            'business_type' => $this->business_type,
            'business_category' => $this->business_category,
            'registration_number' => $this->registration_number,
            'phone_number' => $this->phone_number,
            'email' => $this->email,
            'county' => $this->county,
            'town' => $this->town,
            'physical_address' => $this->physical_address,
            'operating_since' => $this->operating_since?->toDateString(),
            'status' => $this->status?->value,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
