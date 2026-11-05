<?php

namespace App\Services\Transactions;

use App\Models\Transaction;

class TransactionIngestionService
{
    /**
     * Ingest a raw provider transaction. Raw payloads are preserved verbatim and
     * the provider/provider_transaction_id pair is used to prevent duplicates.
     *
     * @param  array<string, mixed>  $payload
     */
    public function ingest(array $payload): Transaction
    {
        $payload['amount_minor'] = (int) $payload['amount_minor'];
        $payload['ingested_at'] ??= now();

        return Transaction::firstOrCreate(
            [
                'provider' => $payload['provider'],
                'provider_transaction_id' => $payload['provider_transaction_id'],
            ],
            $payload,
        );
    }
}
