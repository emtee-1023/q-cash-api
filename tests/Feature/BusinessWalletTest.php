<?php

use App\Enums\BusinessWalletEntryType;
use App\Exceptions\InsufficientWalletBalanceException;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\BusinessWithdrawal;
use App\Models\User;
use App\Services\Transactions\TransactionIngestionService;
use App\Services\Wallets\BusinessWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * @return array{0: Business, 1: User}
 */
function walletBusinessOwner(): array
{
    $business = Business::factory()->create();
    $owner = User::factory()->create();

    BusinessUser::factory()->for($business)->for($owner)->create();

    return [$business, $owner];
}

test('an inflow transaction credits the business wallet once', function () {
    $business = Business::factory()->create();
    $ingestion = app(TransactionIngestionService::class);

    $payload = [
        'business_id' => $business->id,
        'provider' => 'Safaricom',
        'provider_transaction_id' => 'WALLET-INFLOW-1',
        'direction' => 'inflow',
        'amount_minor' => 1500,
        'currency' => 'KES',
        'transaction_time' => now(),
    ];

    $transaction = $ingestion->ingest($payload);
    $ingestion->ingest($payload);

    $wallet = $business->wallet()->firstOrFail();
    $entry = $wallet->entries()->sole();

    expect($wallet->balance_minor)->toBe(1500)
        ->and($entry->transaction_id)->toBe($transaction->id)
        ->and($entry->entry_type)->toBe(BusinessWalletEntryType::Credit)
        ->and($entry->amount_minor)->toBe(1500)
        ->and($entry->balance_after_minor)->toBe(1500);
});

test('an outflow transaction does not credit the business wallet', function () {
    $business = Business::factory()->create();
    $ingestion = app(TransactionIngestionService::class);

    $ingestion->ingest([
        'business_id' => $business->id,
        'provider' => 'Safaricom',
        'provider_transaction_id' => 'WALLET-OUTFLOW-1',
        'direction' => 'outflow',
        'amount_minor' => 1500,
        'currency' => 'KES',
        'transaction_time' => now(),
    ]);

    expect(app(BusinessWalletService::class)->balance($business))->toBe(0)
        ->and($business->wallet()->first()?->entries()->count() ?? 0)->toBe(0);
});

test('wallet debits and refunds are idempotent', function () {
    $business = Business::factory()->create();
    $wallets = app(BusinessWalletService::class);
    $wallets->credit($business, 1000);

    $withdrawal = BusinessWithdrawal::factory()->for($business)->create([
        'amount_minor' => 400,
    ]);

    $debit = $wallets->debit($business, 400, $withdrawal);
    $wallets->debit($business, 400, $withdrawal);

    expect($wallets->balance($business))->toBe(600)
        ->and($debit->amount_minor)->toBe(-400)
        ->and($debit->entry_type)->toBe(BusinessWalletEntryType::Debit);

    $refund = $wallets->refund($business, 400, $withdrawal);
    $wallets->refund($business, 400, $withdrawal);

    expect($wallets->balance($business))->toBe(1000)
        ->and($refund->amount_minor)->toBe(400)
        ->and($refund->entry_type)->toBe(BusinessWalletEntryType::Refund);
});

test('a wallet debit rejects an insufficient balance', function () {
    $business = Business::factory()->create();
    $wallets = app(BusinessWalletService::class);
    $wallets->credit($business, 500);

    expect(fn () => $wallets->debit($business, 600))
        ->toThrow(InsufficientWalletBalanceException::class);

    expect($wallets->balance($business))->toBe(500);
});

test('a business member can view the wallet balance', function () {
    [$business, $owner] = walletBusinessOwner();

    app(BusinessWalletService::class)->credit($business, 2500);

    Sanctum::actingAs($owner);

    $this->getJson("/api/businesses/{$business->id}/wallet")
        ->assertOk()
        ->assertJsonPath('data.balance_minor', 2500)
        ->assertJsonPath('data.currency', 'KES');
});

test('a non member cannot view the wallet balance', function () {
    $business = Business::factory()->create();
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $this->getJson("/api/businesses/{$business->id}/wallet")
        ->assertForbidden();
});

test('a business without a wallet reports a zero balance', function () {
    [$business, $owner] = walletBusinessOwner();

    Sanctum::actingAs($owner);

    $this->getJson("/api/businesses/{$business->id}")
        ->assertOk()
        ->assertJsonPath('data.wallet_balance_minor', 0);
});
