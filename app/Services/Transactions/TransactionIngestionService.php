<?php

namespace App\Services\Transactions;

use App\Enums\TransactionDirection;
use App\Models\Transaction;
use App\Services\Wallets\BusinessWalletService;
use Illuminate\Support\Facades\DB;

class TransactionIngestionService
{
    public function __construct(private readonly BusinessWalletService $wallets) {}

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

        return DB::transaction(function () use ($payload) {
            $transaction = Transaction::firstOrCreate(
                [
                    'provider' => $payload['provider'],
                    'provider_transaction_id' => $payload['provider_transaction_id'],
                ],
                $payload,
            );

            if ($transaction->direction === TransactionDirection::Inflow) {
                $this->wallets->creditForTransaction($transaction);
            }

            return $transaction;
        });
    }
}
