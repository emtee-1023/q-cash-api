<?php

namespace App\Http\Resources;

use App\Models\BusinessType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BusinessType
 */
class BusinessTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'category' => $this->category,
            'description' => $this->description,
            'average_monthly_revenue_minor' => $this->average_monthly_revenue_minor,
            'is_active' => $this->is_active,
        ];
    }
}
