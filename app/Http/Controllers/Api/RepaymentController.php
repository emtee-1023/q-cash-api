<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RecordRepaymentRequest;
use App\Http\Resources\RepaymentCollectionResource;
use App\Models\Business;
use App\Services\Repayments\RepaymentService;

/**
 * @tags Repayments
 */
class RepaymentController extends Controller
{
    public function __construct(private readonly RepaymentService $repayments) {}

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
}
