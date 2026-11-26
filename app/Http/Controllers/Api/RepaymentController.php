<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MpesaRepaymentRequest;
use App\Http\Requests\RecordRepaymentRequest;
use App\Http\Requests\WalletRepaymentRequest;
use App\Http\Resources\RepaymentCollectionResource;
use App\Http\Resources\StkPushRequestResource;
use App\Models\Business;
use App\Services\Repayments\ManualRepaymentService;
use App\Services\Repayments\RepaymentService;

/**
 * @tags Repayments
 */
class RepaymentController extends Controller
{
    public function __construct(
        private readonly RepaymentService $repayments,
        private readonly ManualRepaymentService $manualRepayments,
    ) {}

    /**
     * Record a repayment collection
     *
     * Records a confirmed repayment and allocates it across one or more
     * advances. Idempotent on the idempotency key to prevent duplicate callbacks.
     */
    public function store(RecordRepaymentRequest $request, Business $business)
    {
        $this->authorize('manageFinance', $business);

        $validated = $request->validated();

        $collection = $this->repayments->recordSuccessfulCollection([
            'business_id' => $business->id,
            'collection_method' => $validated['collection_method'],
            'provider' => $validated['provider'] ?? null,
            'provider_reference' => $validated['provider_reference'] ?? null,
            'amount_minor' => $validated['amount_minor'],
            'currency' => $validated['currency'],
            'idempotency_key' => $validated['idempotency_key'],
            'collected_at' => $validated['collected_at'] ?? null,
            'source_transaction_id' => $validated['source_transaction_id'] ?? null,
        ], $validated['allocations']);

        $collection->load('allocations');

        return (new RepaymentCollectionResource($collection))
            ->additional(['message' => 'Repayment recorded.'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Repay from the QCash wallet
     *
     * Debits the business wallet and applies the amount to outstanding advances,
     * oldest and overdue advances first. The wallet is only debited once per
     * idempotency key.
     */
    public function fromWallet(WalletRepaymentRequest $request, Business $business)
    {
        $this->authorize('manageFinance', $business);

        $collection = $this->manualRepayments->payFromWallet(
            $business,
            $request->integer('amount_minor'),
            $request->string('idempotency_key')->toString(),
        );

        return (new RepaymentCollectionResource($collection))
            ->additional(['message' => 'Repayment successful.'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Repay via M-Pesa
     *
     * Sends an STK push to the supplied phone number. On a successful callback
     * the payment is applied to outstanding advances, oldest and overdue first.
     */
    public function viaMpesa(MpesaRepaymentRequest $request, Business $business)
    {
        $this->authorize('manageFinance', $business);

        $stkPush = $this->manualRepayments->initiateMpesa(
            $business,
            $request->string('phone_number')->toString(),
            $request->integer('amount_minor'),
        );

        return (new StkPushRequestResource($stkPush))
            ->additional(['message' => 'STK push initiated.'])
            ->response()
            ->setStatusCode(201);
    }
}
