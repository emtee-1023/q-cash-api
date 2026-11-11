<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBusinessRequest;
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
        $businesses = $request->user()->businesses()->with('businessType')->get();

        return BusinessResource::collection($businesses);
    }

    /**
     * Register a business
     *
     * Creates a new business and attaches the authenticated user as its owner.
     */
    public function store(StoreBusinessRequest $request)
    {
        $business = Business::create($request->validated());

        $business->businessUsers()->create([
            'user_id' => $request->user()->id,
            'role' => 'owner',
            'is_primary_contact' => true,
            'status' => 'active',
        ]);

        $business->load('businessType');

        return (new BusinessResource($business))
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

        $business->load('businessType');

        return new BusinessResource($business);
    }
}
