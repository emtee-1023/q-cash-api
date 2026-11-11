<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CountyResource;
use App\Http\Resources\SubCountyResource;
use App\Http\Resources\WardResource;
use App\Models\County;
use App\Models\SubCounty;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Locations
 */
class LocationController extends Controller
{
    public function counties(): AnonymousResourceCollection
    {
        return CountyResource::collection(
            County::query()->orderBy('name')->get(),
        );
    }

    public function subCounties(County $county): AnonymousResourceCollection
    {
        return SubCountyResource::collection(
            $county->subCounties()->orderBy('name')->get(),
        );
    }

    public function wards(SubCounty $subCounty): AnonymousResourceCollection
    {
        return WardResource::collection(
            $subCounty->wards()->orderBy('name')->get(),
        );
    }
}
