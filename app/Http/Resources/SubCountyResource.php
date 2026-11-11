<?php

namespace App\Http\Resources;

use App\Models\SubCounty;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SubCounty
 */
class SubCountyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'county_id' => $this->county_id,
            'code' => $this->code,
            'name' => $this->name,
            'slug' => $this->slug,
        ];
    }
}
