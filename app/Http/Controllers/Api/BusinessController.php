<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBusinessRequest;
use App\Http\Resources\BusinessDetailResource;
use App\Http\Resources\BusinessResource;
use App\Models\Business;
use Illuminate\Http\Request;

/**
 * @tags Businesses
 */
class BusinessController extends Controller
{
    /**
     * List businesses
     *
     * Returns the businesses the authenticated user is a member of.
     */
    public function index(Request $request)
    {
        $businesses = $request->user()->businesses()
            ->with(['businessType', 'countyLocation', 'subCounty', 'ward'])
            ->get();

        return BusinessResource::collection($businesses);
    }

    /**
     * Register a business
     *
     * Creates a new business and attaches the authenticated user as its owner.
     */
    public function store(StoreBusinessRequest $request)
    {
        $attributes = $request->validated();
        $hasPhysicalLocation = $request->boolean('has_physical_location');

        if (! $hasPhysicalLocation) {
            $attributes['county_id'] = null;
            $attributes['sub_county_id'] = null;
            $attributes['ward_id'] = null;
            $attributes['physical_address'] = null;
        }

        $attributes['has_physical_location'] = $hasPhysicalLocation;

        $business = Business::create($attributes);

        $business->businessUsers()->create([
            'user_id' => $request->user()->id,
            'role' => 'owner',
            'is_primary_contact' => true,
            'status' => 'active',
        ]);

        $business->load(['businessType', 'countyLocation', 'subCounty', 'ward']);

        return (new BusinessDetailResource($business))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a business
     *
     * Returns a single business the authenticated user is a member of.
     */
    public function show(Business $business)
    {
        $this->authorize('view', $business);

        $business->load(['businessType', 'countyLocation', 'subCounty', 'ward']);

        return new BusinessDetailResource($business);
    }
}
