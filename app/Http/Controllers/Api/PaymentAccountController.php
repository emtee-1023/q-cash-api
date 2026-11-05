<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentAccountRequest;
use App\Http\Resources\PaymentAccountResource;
use App\Models\Business;

/**
 * @tags Payment Accounts
 */
class PaymentAccountController extends Controller
{
    /**
     * Add a payment account
     *
     * Links a payment channel (e.g. a Safaricom paybill) to a business.
     */
    public function store(StorePaymentAccountRequest $request, Business $business)
    {
        $this->authorize('manageFinance', $business);

        $account = $business->paymentAccounts()->create($request->validated());

        return (new PaymentAccountResource($account))
            ->response()
            ->setStatusCode(201);
    }
}
