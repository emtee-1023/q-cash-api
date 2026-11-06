<?php

use App\Models\Business;
use App\Models\Transaction;
use App\Services\Transactions\TransactionIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a raw transaction is ingested with its payload preserved', function () {
    $business = Business::factory()->create();

    $payload = [
        'business_id' => $business->id,
        'provider' => 'Safaricom',
        'provider_transaction_id' => 'ST-123',
        'direction' => 'inflow',
        'amount_minor' => 10050,
        'currency' => 'KES',
        'transaction_time' => now()->toDateTimeString(),
        'raw_payload' => ['original' => ['foo' => 'bar']],
    ];

    $transaction = app(TransactionIngestionService::class)->ingest($payload);

    expect($transaction->amount_minor)->toBe(10050);
    expect($transaction->raw_payload)->toBe(['original' => ['foo' => 'bar']]);
    expect($transaction->ingested_at)->not->toBeNull();
});

test('ingesting a duplicate provider transaction returns the existing record', function () {
    $business = Business::factory()->create();

    $payload = [
        'business_id' => $business->id,
        'provider' => 'Safaricom',
        'provider_transaction_id' => 'ST-456',
        'direction' => 'inflow',
        'amount_minor' => 20000,
        'currency' => 'KES',
        'transaction_time' => now()->toDateTimeString(),
    ];

    $service = app(TransactionIngestionService::class);
    $first = $service->ingest($payload);
    $second = $service->ingest($payload);

    expect($second->id)->toBe($first->id);
    expect(Transaction::query()->count())->toBe(1);
});
