<?php

namespace App\Http\Resources;

use App\Models\Business;
use Illuminate\Http\Request;

/**
 * @mixin Business
 */
class BusinessDetailResource extends BusinessResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'paybill_number' => config('services.mpesa.paybill_number'),
        ];
    }
}
