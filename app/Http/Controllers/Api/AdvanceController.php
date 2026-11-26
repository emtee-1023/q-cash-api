<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdvanceResource;
use App\Models\Advance;
use App\Models\Business;
use App\Services\Advances\AdvanceService;
use App\Services\Disbursements\WalletDisbursementService;
use Illuminate\Http\Request;

/**
 * @tags Advances
 */
class AdvanceController extends Controller
{
    public function __construct(
        private readonly AdvanceService $advances,
        private readonly WalletDisbursementService $walletDisbursements,
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

        return (new AdvanceResource($advance))
            ->additional(['message' => 'Advance approved.']);
    }

    /**
     * Disburse an advance
     *
     * Credits the advance principal to the business QCash wallet and notifies
     * the owner by SMS and in-app notification.
     */
    public function disburse(Advance $advance)
    {
        $this->authorize('manage', $advance);

        $this->walletDisbursements->disburse($advance);

        return (new AdvanceResource($advance->refresh()))
            ->additional(['message' => 'Advance credited to wallet.']);
    }
}
