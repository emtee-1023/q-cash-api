<?php

namespace App\Http\Resources;

use App\Models\County;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin County
 */
class CountyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'slug' => $this->slug,
            'capital' => $this->capital,
            'region' => $this->region,
            'iso_code' => $this->iso_code,
            'postal_code' => $this->postal_code,
        ];
    }
}
