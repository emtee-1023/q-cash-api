<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBusinessRequest;
use App\Http\Requests\UpdateBusinessRequest;
use App\Http\Resources\BusinessDetailResource;
use App\Http\Resources\BusinessResource;
use App\Models\Business;
use App\Models\County;
use App\Models\SubCounty;
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

        if ($hasPhysicalLocation) {
            if (isset($attributes['county_id'])) {
                $attributes['county'] = County::find($attributes['county_id'])?->name;
            }

            if (isset($attributes['sub_county_id'])) {
                $attributes['town'] = SubCounty::find($attributes['sub_county_id'])?->name;
            }
        } else {
            $attributes['county_id'] = null;
            $attributes['sub_county_id'] = null;
            $attributes['ward_id'] = null;
            $attributes['county'] = null;
            $attributes['town'] = null;
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

    public function update(UpdateBusinessRequest $request, Business $business): BusinessDetailResource
    {
        $this->authorize('manageProfile', $business);

        $attributes = [];

        foreach (['business_name', 'business_type_id', 'phone_number', 'email', 'physical_address'] as $field) {
            if ($request->has($field)) {
                $attributes[$field] = $request->input($field);
            }
        }

        $hasPhysicalLocation = $request->has('has_physical_location')
            ? $request->boolean('has_physical_location')
            : null;

        if ($hasPhysicalLocation === false) {
            $attributes['has_physical_location'] = false;
            $attributes['county_id'] = null;
            $attributes['sub_county_id'] = null;
            $attributes['ward_id'] = null;
            $attributes['county'] = null;
            $attributes['town'] = null;
            $attributes['physical_address'] = null;
        } else {
            if ($hasPhysicalLocation === true) {
                $attributes['has_physical_location'] = true;
            }

            if ($request->has('county_id')) {
                $countyId = $request->input('county_id');
                $attributes['county_id'] = $countyId;
                $attributes['county'] = $countyId !== null ? County::find($countyId)?->name : null;

                if ((int) $countyId !== (int) $business->county_id && ! $request->has('sub_county_id')) {
                    $attributes['sub_county_id'] = null;
                    $attributes['town'] = null;
                    $attributes['ward_id'] = null;
                }
            }

            if ($request->has('sub_county_id')) {
                $subCountyId = $request->input('sub_county_id');
                $attributes['sub_county_id'] = $subCountyId;
                $attributes['town'] = $subCountyId !== null ? SubCounty::find($subCountyId)?->name : null;

                if ((int) $subCountyId !== (int) $business->sub_county_id && ! $request->has('ward_id')) {
                    $attributes['ward_id'] = null;
                }
            }

            if ($request->has('ward_id')) {
                $attributes['ward_id'] = $request->input('ward_id');
            }
        }

        $business->update($attributes);

        $business->load(['businessType', 'countyLocation', 'subCounty', 'ward']);

        return (new BusinessDetailResource($business))
            ->additional(['message' => 'Business updated.']);
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
