<?php

use App\Enums\BusinessWalletEntryType;
use App\Enums\BusinessWithdrawalStatus;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\BusinessWithdrawal;
use App\Models\User;
use App\Services\Wallets\BusinessWalletService;
use App\Services\Withdrawals\MpesaB2cWithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Markt\LaravelAuth\Contracts\SmsSender;
use Tests\Fixtures\FakeSmsSender;

uses(RefreshDatabase::class);

function withdrawalB2cConfig(): void
{
    config([
        'services.mpesa.environment' => 'sandbox',
        'services.mpesa.base_url' => null,
        'services.mpesa.shortcode' => '600000',
        'services.mpesa.b2c.shortcode' => '600000',
        'services.mpesa.b2c.initiator_name' => 'testapi',
        'services.mpesa.b2c.security_credential' => str_repeat('A', 344),
        'services.mpesa.b2c.command_id' => 'BusinessPayment',
        'services.mpesa.b2c.result_url' => 'https://example.test/api/webhooks/b2c/result',
        'services.mpesa.b2c.timeout_url' => 'https://example.test/api/webhooks/b2c/timeout',
    ]);
}

/**
 * @return array{0: Business, 1: User}
 */
function withdrawalBusinessOwner(): array
{
    $business = Business::factory()->create();
    $owner = User::factory()->create(['phone_number' => '254712345678']);

    BusinessUser::factory()->for($business)->for($owner)->create();

    return [$business, $owner];
}

function fakeAcceptedWithdrawalB2c(string $conversationId = 'AG_WITHDRAWAL_1'): void
{
    Http::preventStrayRequests();

    Http::fake([
        'https://sandbox.safaricom.co.ke/oauth/v1/generate*' => Http::response([
            'access_token' => 'test-token',
        ]),
        'https://sandbox.safaricom.co.ke/mpesa/b2c/v1/paymentrequest' => Http::response([
            'ConversationID' => $conversationId,
            'OriginatorConversationID' => 'ignored',
            'ResponseCode' => '0',
            'ResponseDescription' => 'Accept the service request successfully.',
        ]),
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function withdrawalResultPayload(BusinessWithdrawal $withdrawal, array $overrides = []): array
{
    return [
        'Result' => array_merge([
            'ResultType' => 0,
            'ResultCode' => 0,
            'ResultDesc' => 'The service request is processed successfully.',
            'OriginatorConversationID' => $withdrawal->originator_conversation_id,
            'ConversationID' => $withdrawal->provider_reference,
            'TransactionID' => 'TESTWD001',
            'ResultParameters' => [
                'ResultParameter' => [
                    ['Key' => 'TransactionAmount', 'Value' => 30.00],
                    ['Key' => 'TransactionReceipt', 'Value' => 'TESTWD001'],
                ],
            ],
        ], $overrides),
    ];
}

test('an owner can withdraw to the owner phone via b2c', function () {
    withdrawalB2cConfig();
    [$business, $owner] = withdrawalBusinessOwner();

    app(BusinessWalletService::class)->credit($business, 5000);

    fakeAcceptedWithdrawalB2c();

    Sanctum::actingAs($owner);

    $this->postJson("/api/businesses/{$business->id}/withdrawals", [
        'amount_minor' => 3000,
    ])->assertCreated()
        ->assertJsonPath('data.amount_minor', 3000)
        ->assertJsonPath('data.status', 'processing')
        ->assertJsonPath('data.recipient_phone_number', $owner->phone_number);

    $withdrawal = BusinessWithdrawal::query()->sole();
    $wallet = $business->wallet()->firstOrFail();

    expect($withdrawal->provider_reference)->toBe('AG_WITHDRAWAL_1')
        ->and($withdrawal->originator_conversation_id)->not->toBeNull()
        ->and($wallet->balance_minor)->toBe(2000)
        ->and($wallet->entries()->where('entry_type', BusinessWalletEntryType::Debit->value)->sole()->amount_minor)->toBe(-3000);

    Http::assertSent(function (Request $request) use ($owner, $withdrawal): bool {
        if (! str_ends_with($request->url(), '/mpesa/b2c/v1/paymentrequest')) {
            return false;
        }

        return $request['PartyB'] === $owner->phone_number
            && $request['Amount'] === '30.00'
            && $request['PartyA'] === '600000'
            && $request['Remarks'] === 'QCash withdrawal'
            && $request['Occasion'] === 'QCash withdrawal'
            && $request['OriginatorConversationID'] === $withdrawal->originator_conversation_id;
    });
});

test('a non owner cannot withdraw from the wallet', function () {
    withdrawalB2cConfig();

    $business = Business::factory()->create();
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $this->postJson("/api/businesses/{$business->id}/withdrawals", [
        'amount_minor' => 1000,
    ])->assertForbidden();

    expect(BusinessWithdrawal::query()->count())->toBe(0);
});

test('a withdrawal cannot exceed the wallet balance', function () {
    withdrawalB2cConfig();
    [$business, $owner] = withdrawalBusinessOwner();

    app(BusinessWalletService::class)->credit($business, 1000);

    Http::preventStrayRequests();

    Sanctum::actingAs($owner);

    $this->postJson("/api/businesses/{$business->id}/withdrawals", [
        'amount_minor' => 2000,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('amount_minor');

    expect(BusinessWithdrawal::query()->count())->toBe(0)
        ->and(app(BusinessWalletService::class)->balance($business))->toBe(1000);

    Http::assertNothingSent();
});

test('a rejected b2c withdrawal refunds the wallet', function () {
    withdrawalB2cConfig();
    [$business] = withdrawalBusinessOwner();

    app(BusinessWalletService::class)->credit($business, 5000);

    Http::preventStrayRequests();

    Http::fake([
        'https://sandbox.safaricom.co.ke/oauth/v1/generate*' => Http::response([
            'access_token' => 'test-token',
        ]),
        'https://sandbox.safaricom.co.ke/mpesa/b2c/v1/paymentrequest' => Http::response([
            'ConversationID' => 'AG_WITHDRAWAL_REJECTED',
            'ResponseCode' => '1',
            'ResponseDescription' => 'The initiator information is invalid.',
        ]),
    ]);

    expect(fn () => app(MpesaB2cWithdrawalService::class)->withdraw($business, 3000))
        ->toThrow(RuntimeException::class, 'The initiator information is invalid.');

    $withdrawal = BusinessWithdrawal::query()->sole();
    $wallet = $business->wallet()->firstOrFail();

    expect($withdrawal->status)->toBe(BusinessWithdrawalStatus::Failed)
        ->and($withdrawal->failure_reason)->toBe('The initiator information is invalid.')
        ->and(app(BusinessWalletService::class)->balance($business))->toBe(5000)
        ->and($wallet->entries()->where('entry_type', BusinessWalletEntryType::Debit->value)->count())->toBe(1)
        ->and($wallet->entries()->where('entry_type', BusinessWalletEntryType::Refund->value)->count())->toBe(1);
});

test('a successful b2c withdrawal result notifies the owner and keeps the debit', function () {
    withdrawalB2cConfig();
    [$business, $owner] = withdrawalBusinessOwner();

    $wallets = app(BusinessWalletService::class);
    $wallets->credit($business, 5000);

    $withdrawal = BusinessWithdrawal::factory()->for($business)->create([
        'amount_minor' => 3000,
        'recipient_phone_number' => $owner->phone_number,
        'provider_reference' => 'AG_WITHDRAWAL_2',
        'originator_conversation_id' => 'originator-withdrawal-1',
        'status' => BusinessWithdrawalStatus::Processing->value,
        'requested_at' => now(),
    ]);

    $wallets->debit($business, 3000, $withdrawal);

    $sms = new FakeSmsSender;
    $this->instance(SmsSender::class, $sms);

    $this->postJson('/api/webhooks/b2c/result', withdrawalResultPayload($withdrawal))
        ->assertOk()
        ->assertExactJson(['ResultCode' => '0', 'ResultDesc' => 'Success']);

    $withdrawal->refresh();
    $wallet = $business->wallet()->firstOrFail();

    expect($withdrawal->status)->toBe(BusinessWithdrawalStatus::Successful)
        ->and($withdrawal->provider_reference)->toBe('TESTWD001')
        ->and($wallet->balance_minor)->toBe(2000)
        ->and($wallet->entries()->where('entry_type', BusinessWalletEntryType::Debit->value)->count())->toBe(1)
        ->and($wallet->entries()->where('entry_type', BusinessWalletEntryType::Refund->value)->count())->toBe(0)
        ->and($sms->lastMessageFor($owner->phone_number))
        ->toBe('QCash: A withdrawal of KES 30.00 has been sent to your M-Pesa account. Ref: TESTWD001. Thank you for using QCash.');
});

test('a failed b2c withdrawal result refunds the wallet only once', function () {
    withdrawalB2cConfig();
    [$business] = withdrawalBusinessOwner();

    $wallets = app(BusinessWalletService::class);
    $wallets->credit($business, 5000);

    $withdrawal = BusinessWithdrawal::factory()->for($business)->create([
        'amount_minor' => 3000,
        'provider_reference' => 'AG_WITHDRAWAL_3',
        'originator_conversation_id' => 'originator-withdrawal-2',
        'status' => BusinessWithdrawalStatus::Processing->value,
    ]);

    $wallets->debit($business, 3000, $withdrawal);

    $payload = withdrawalResultPayload($withdrawal, [
        'ResultCode' => 1,
        'ResultDesc' => 'The balance is insufficient for the transaction.',
        'ResultParameters' => null,
    ]);

    $this->postJson('/api/webhooks/b2c/result', $payload)->assertOk();
    $this->postJson('/api/webhooks/b2c/result', $payload)->assertOk();

    $withdrawal->refresh();
    $wallet = $business->wallet()->firstOrFail();

    expect($withdrawal->status)->toBe(BusinessWithdrawalStatus::Failed)
        ->and($withdrawal->failure_reason)->toBe('The balance is insufficient for the transaction.')
        ->and($wallet->balance_minor)->toBe(5000)
        ->and($wallet->entries()->where('entry_type', BusinessWalletEntryType::Refund->value)->count())->toBe(1);
});

test('a b2c withdrawal timeout keeps the debit for reconciliation', function () {
    withdrawalB2cConfig();
    [$business] = withdrawalBusinessOwner();

    $wallets = app(BusinessWalletService::class);
    $wallets->credit($business, 5000);

    $withdrawal = BusinessWithdrawal::factory()->for($business)->create([
        'amount_minor' => 3000,
        'provider_reference' => 'AG_WITHDRAWAL_4',
        'originator_conversation_id' => 'originator-withdrawal-3',
        'status' => BusinessWithdrawalStatus::Processing->value,
    ]);

    $wallets->debit($business, 3000, $withdrawal);

    $payload = withdrawalResultPayload($withdrawal, [
        'ResultCode' => 1,
        'ResultDesc' => 'Request timed out.',
        'ResultParameters' => null,
    ]);

    $this->postJson('/api/webhooks/b2c/timeout', $payload)->assertOk();

    $withdrawal->refresh();
    $wallet = $business->wallet()->firstOrFail();

    expect($withdrawal->status)->toBe(BusinessWithdrawalStatus::TimedOut)
        ->and($withdrawal->failure_reason)->toBe('The B2C request timed out; the debit is held for reconciliation.')
        ->and($wallet->balance_minor)->toBe(2000)
        ->and($wallet->entries()->where('entry_type', BusinessWalletEntryType::Refund->value)->count())->toBe(0);
});

test('a successful result after a timeout completes the withdrawal without a refund', function () {
    withdrawalB2cConfig();
    [$business, $owner] = withdrawalBusinessOwner();

    $wallets = app(BusinessWalletService::class);
    $wallets->credit($business, 5000);

    $withdrawal = BusinessWithdrawal::factory()->for($business)->create([
        'amount_minor' => 3000,
        'recipient_phone_number' => $owner->phone_number,
        'provider_reference' => 'AG_WITHDRAWAL_5',
        'originator_conversation_id' => 'originator-withdrawal-4',
        'status' => BusinessWithdrawalStatus::TimedOut->value,
    ]);

    $wallets->debit($business, 3000, $withdrawal);

    $sms = new FakeSmsSender;
    $this->instance(SmsSender::class, $sms);

    $payload = withdrawalResultPayload($withdrawal, [
        'OriginatorConversationID' => 'originator-withdrawal-4',
        'ConversationID' => 'AG_WITHDRAWAL_5',
        'TransactionID' => 'TESTWD002',
        'ResultParameters' => [
            'ResultParameter' => [
                ['Key' => 'TransactionAmount', 'Value' => 30.00],
                ['Key' => 'TransactionReceipt', 'Value' => 'TESTWD002'],
            ],
        ],
    ]);

    $this->postJson('/api/webhooks/b2c/result', $payload)->assertOk();

    $withdrawal->refresh();
    $wallet = $business->wallet()->firstOrFail();

    expect($withdrawal->status)->toBe(BusinessWithdrawalStatus::Successful)
        ->and($withdrawal->provider_reference)->toBe('TESTWD002')
        ->and($wallet->balance_minor)->toBe(2000)
        ->and($wallet->entries()->where('entry_type', BusinessWalletEntryType::Refund->value)->count())->toBe(0)
        ->and($sms->lastMessageFor($owner->phone_number))
        ->toBe('QCash: A withdrawal of KES 30.00 has been sent to your M-Pesa account. Ref: TESTWD002. Thank you for using QCash.');
});
