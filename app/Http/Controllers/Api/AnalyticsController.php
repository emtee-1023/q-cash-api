<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BusinessAnalyticsResource;
use App\Models\Business;
use App\Services\Analytics\BusinessAnalyticsService;

/**
 * @tags Analytics
 */
class AnalyticsController extends Controller
{
    /**
     * Show business analytics
     *
     * Returns today's and last month's earnings, balances, and the latest advance.
     */
    public function show(Business $business, BusinessAnalyticsService $analytics): BusinessAnalyticsResource
    {
        $this->authorize('view', $business);

        return new BusinessAnalyticsResource($analytics->for($business));
    }
}
