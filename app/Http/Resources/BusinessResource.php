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
            'business_type_id' => $this->business_type_id,
            'business_type' => $this->whenLoaded(
                'businessType',
                fn () => $this->businessType ? new BusinessTypeResource($this->businessType) : null,
            ),
            'business_category' => $this->whenLoaded(
                'businessType',
                fn () => $this->businessType?->category ?? $this->business_category,
                $this->business_category,
            ),
            'registration_number' => $this->registration_number,
            'phone_number' => $this->phone_number,
            'email' => $this->email,
            'county' => $this->county,
            'town' => $this->town,
            'physical_address' => $this->physical_address,
            'operating_since' => $this->operating_since?->toDateString(),
            'status' => $this->status?->value,
            'kyc_status' => $this->kyc_status?->value,
            'collection_account_number' => $this->collection_account_number,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
