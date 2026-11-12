<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InitiateStkPushRequest;
use App\Http\Resources\StkPushRequestResource;
use App\Models\Business;
use App\Services\Transactions\MpesaStkPushService;

/**
 * @tags STK Push
 */
class StkPushController extends Controller
{
    public function __construct(private readonly MpesaStkPushService $stkPush) {}

    /**
     * Initiate an STK push
     *
     * Sends an M-Pesa payment prompt to the customer's phone. The asynchronous
     * callback records the transaction and notifies the business owner.
     */
    public function store(InitiateStkPushRequest $request, Business $business)
    {
        $this->authorize('manageFinance', $business);

        $validated = $request->validated();

        $stkPush = $this->stkPush->initiate(
            $business,
            $validated['phone_number'],
            $validated['amount_minor'],
            $validated['account_reference'] ?? null,
            $validated['description'] ?? null,
        );

        return (new StkPushRequestResource($stkPush))
            ->additional(['message' => 'STK push initiated.'])
            ->response()
            ->setStatusCode(201);
    }
}
