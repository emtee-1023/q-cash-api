<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdvanceResource;
use App\Models\Advance;
use App\Models\Business;
use App\Services\Advances\AdvanceService;
use App\Services\Disbursements\MpesaB2cDisbursementService;
use Illuminate\Http\Request;

/**
 * @tags Advances
 */
class AdvanceController extends Controller
{
    public function __construct(
        private readonly AdvanceService $advances,
        private readonly MpesaB2cDisbursementService $b2cDisbursements,
    ) {}

    /**
     * List advances
     *
     * Returns the advances belonging to a business, newest first.
     */
    public function index(Request $request, Business $business)
    {
        $this->authorize('view', $business);

        return AdvanceResource::collection($business->advances()->latest()->get());
    }

    /**
     * Show an advance
     *
     * Returns a single advance including its outstanding balance and status.
     */
    public function show(Advance $advance)
    {
        $this->authorize('view', $advance);

        return new AdvanceResource($advance);
    }

    /**
     * Approve an advance
     *
     * Marks a pending advance as approved, subject to any financing-partner
     * approval requirements.
     */
    public function approve(Advance $advance)
    {
        $this->authorize('manage', $advance);

        $advance = $this->advances->approve($advance);

        return new AdvanceResource($advance);
    }

    /**
     * Request disbursement
     *
     * Sends the advance principal to the business owner's M-Pesa number via
     * Safaricom B2C and transitions the advance to the disbursing state.
     */
    public function disburse(Advance $advance)
    {
        $this->authorize('manage', $advance);

        $this->b2cDisbursements->disburse($advance);

        return new AdvanceResource($advance->refresh());
    }
}
