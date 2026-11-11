<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BusinessTypeResource;
use App\Models\BusinessType;

/**
 * @tags Businesses
 */
class BusinessTypeController extends Controller
{
    /**
     * List business types
     *
     * Returns the active business types available when registering a business.
     */
    public function index()
    {
        $businessTypes = BusinessType::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return BusinessTypeResource::collection($businessTypes);
    }
}
