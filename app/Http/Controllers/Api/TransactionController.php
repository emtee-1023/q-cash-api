<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Business;
use App\Services\Transactions\TransactionIngestionService;
use Illuminate\Http\Request;

/**
 * @tags Transactions
 */
class TransactionController extends Controller
{
    public function __construct(private readonly TransactionIngestionService $ingestion) {}

    /**
     * List transactions
     *
     * Returns a paginated list of raw business transactions, newest first.
     */
    public function index(Request $request, Business $business)
    {
        $this->authorize('view', $business);

        $transactions = $business->transactions()
            ->latest('transaction_time')
            ->paginate($request->integer('per_page', 50));

        return TransactionResource::collection($transactions);
    }

    /**
     * Ingest a transaction
     *
     * Stores a raw provider transaction. Idempotent on the provider and
     * provider transaction id, so duplicate ingests return the existing record.
     */
    public function store(StoreTransactionRequest $request, Business $business)
    {
        $this->authorize('manageFinance', $business);

        $transaction = $this->ingestion->ingest([
            ...$request->validated(),
            'business_id' => $business->id,
        ]);

        return (new TransactionResource($transaction))
            ->response()
            ->setStatusCode(201);
    }
}
