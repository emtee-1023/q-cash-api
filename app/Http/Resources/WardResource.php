<?php

namespace App\Http\Resources;

use App\Models\Ward;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Ward
 */
class WardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sub_county_id' => $this->sub_county_id,
            'code' => $this->code,
            'name' => $this->name,
            'slug' => $this->slug,
        ];
    }
}
