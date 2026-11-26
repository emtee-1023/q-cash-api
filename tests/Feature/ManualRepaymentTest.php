<?php

use App\Enums\AdvanceStatus;
use App\Enums\CollectionMethod;
use App\Enums\CollectionStatus;
use App\Enums\StkPushPurpose;
use App\Models\Advance;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\RepaymentCollection;
use App\Models\StkPushRequest;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Wallets\BusinessWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Markt\LaravelAuth\Contracts\SmsSender;
use Tests\Fixtures\FakeSmsSender;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->instance(SmsSender::class, new FakeSmsSender);
});

/**
 * @return array{0: Business, 1: User}
 */
function manualRepaymentBusinessOwner(): array
{
    $business = Business::factory()->create();
    $owner = User::factory()->create(['phone_number' => '254712345678']);

    BusinessUser::factory()->for($business)->for($owner)->create();

    return [$business, $owner];
}

/**
 * @param  array<string, mixed>  $overrides
 */
function repayableManualAdvance(Business $business, int $outstanding, array $overrides = []): Advance
{
    return Advance::factory()->for($business)->create(array_merge([
        'principal_amount_minor' => $outstanding,
        'total_repayable_minor' => $outstanding,
        'outstanding_balance_minor' => $outstanding,
        'amount_repaid_minor' => 0,
        'status' => AdvanceStatus::Active->value,
    ], $overrides));
}

function manualRepaymentStkConfig(): void
{
    config([
        'services.mpesa.shortcode' => '174379',
        'services.mpesa.passkey' => 'test-passkey',
        'services.mpesa.stk_callback_url' => 'https://example.test/api/webhooks/stk',
        'services.mpesa.environment' => 'sandbox',
        'services.mpesa.base_url' => null,
    ]);
}

function fakeAcceptedStkPush(string $checkoutRequestId = 'ws_CO_REPAY_1'): void
{
    Http::preventStrayRequests();

    Http::fake([
        'https://sandbox.safaricom.co.ke/oauth/v1/generate*' => Http::response([
            'access_token' => 'test-token',
            'expires_in' => '3599',
        ]),
        'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest' => Http::response([
            'MerchantRequestID' => '29115-34620561-1',
            'CheckoutRequestID' => $checkoutRequestId,
            'ResponseCode' => '0',
            'ResponseDescription' => 'Success. Request accepted for processing',
            'CustomerMessage' => 'Success. Request accepted for processing',
        ]),
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function manualRepaymentStkPayload(StkPushRequest $stkPush, float $amount = 10.00, array $overrides = []): array
{
    return [
        'Body' => [
            'stkCallback' => array_merge([
                'MerchantRequestID' => $stkPush->merchant_request_id,
                'CheckoutRequestID' => $stkPush->checkout_request_id,
                'ResultCode' => 0,
                'ResultDesc' => 'The service request is processed successfully.',
                'CallbackMetadata' => [
                    'Item' => [
                        ['Name' => 'Amount', 'Value' => $amount],
                        ['Name' => 'MpesaReceiptNumber', 'Value' => 'NLJ7RT61SV'],
                        ['Name' => 'TransactionDate', 'Value' => 20260115123045],
                        ['Name' => 'PhoneNumber', 'Value' => 254708374149],
                    ],
                ],
            ], $overrides),
        ],
    ];
}

test('a wallet repayment debits the wallet and reduces the advance balance', function () {
    [$business, $owner] = manualRepaymentBusinessOwner();
    $advance = repayableManualAdvance($business, 4000);

    app(BusinessWalletService::class)->credit($business, 5000);

    $sms = new FakeSmsSender;
    $this->instance(SmsSender::class, $sms);

    Sanctum::actingAs($owner);

    $this->postJson("/api/businesses/{$business->id}/repayments/wallet", [
        'amount_minor' => 1500,
        'idempotency_key' => 'wallet-repayment-1',
    ])->assertCreated()
        ->assertJsonPath('data.amount_minor', 1500)
        ->assertJsonPath('data.collection_method', 'manual_payment')
        ->assertJsonPath('data.provider', 'qcash_wallet')
        ->assertJsonPath('data.allocations.0.advance_id', $advance->id)
        ->assertJsonPath('data.allocations.0.amount_minor', 1500);

    expect(app(BusinessWalletService::class)->balance($business))->toBe(3500);

    $advance->refresh();
    expect($advance->amount_repaid_minor)->toBe(1500)
        ->and($advance->outstanding_balance_minor)->toBe(2500)
        ->and($advance->status)->toBe(AdvanceStatus::Active)
        ->and($sms->lastMessageFor($owner->phone_number))
        ->toBe('QCash: Loan repayment of KES 15.00 received. Outstanding balance: KES 25.00.')
        ->and($owner->notifications()->sole()->type)->toBe('repayment_received');
});

test('a wallet repayment is applied to overdue and oldest advances first', function () {
    [$business, $owner] = manualRepaymentBusinessOwner();

    $current = repayableManualAdvance($business, 1000, [
        'due_at' => now()->addDays(5),
    ]);
    $overdue = repayableManualAdvance($business, 1000, [
        'status' => AdvanceStatus::Overdue->value,
        'due_at' => now()->subDays(10),
    ]);
    $olderCurrent = repayableManualAdvance($business, 1000, [
        'due_at' => now()->subDay(),
    ]);

    app(BusinessWalletService::class)->credit($business, 2500);

    Sanctum::actingAs($owner);

    $this->postJson("/api/businesses/{$business->id}/repayments/wallet", [
        'amount_minor' => 2000,
        'idempotency_key' => 'wallet-repayment-fifo',
    ])->assertCreated()
        ->assertJsonPath('data.allocations.0.advance_id', $overdue->id)
        ->assertJsonPath('data.allocations.1.advance_id', $olderCurrent->id);

    expect($overdue->refresh()->outstanding_balance_minor)->toBe(0)
        ->and($overdue->status)->toBe(AdvanceStatus::Settled)
        ->and($olderCurrent->refresh()->outstanding_balance_minor)->toBe(0)
        ->and($current->refresh()->outstanding_balance_minor)->toBe(1000);
});

test('a wallet repayment with an insufficient balance notifies the owner', function () {
    [$business, $owner] = manualRepaymentBusinessOwner();
    $advance = repayableManualAdvance($business, 3000);

    app(BusinessWalletService::class)->credit($business, 500);

    $sms = new FakeSmsSender;
    $this->instance(SmsSender::class, $sms);

    Sanctum::actingAs($owner);

    $this->postJson("/api/businesses/{$business->id}/repayments/wallet", [
        'amount_minor' => 1000,
        'idempotency_key' => 'wallet-repayment-insufficient',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('amount_minor');

    expect(RepaymentCollection::query()->count())->toBe(0)
        ->and(app(BusinessWalletService::class)->balance($business))->toBe(500)
        ->and($advance->refresh()->amount_repaid_minor)->toBe(0);

    expect($sms->lastMessageFor($owner->phone_number))
        ->toBe('QCash: Your repayment of KES 10.00 could not be completed because your wallet balance of KES 5.00 is insufficient. Top up your wallet or pay via M-Pesa.');

    expect($owner->notifications()->sole()->type)->toBe('repayment_failed');
});

test('a wallet repayment is idempotent on the idempotency key', function () {
    [$business, $owner] = manualRepaymentBusinessOwner();
    $advance = repayableManualAdvance($business, 4000);

    app(BusinessWalletService::class)->credit($business, 5000);

    Sanctum::actingAs($owner);

    $payload = [
        'amount_minor' => 1000,
        'idempotency_key' => 'wallet-repayment-dupe',
    ];

    $this->postJson("/api/businesses/{$business->id}/repayments/wallet", $payload)->assertCreated();
    $this->postJson("/api/businesses/{$business->id}/repayments/wallet", $payload)->assertCreated();

    expect(RepaymentCollection::query()->count())->toBe(1)
        ->and(app(BusinessWalletService::class)->balance($business))->toBe(4000)
        ->and($advance->refresh()->amount_repaid_minor)->toBe(1000);
});

test('a wallet repayment requires an idempotency key', function () {
    [$business, $owner] = manualRepaymentBusinessOwner();
    repayableManualAdvance($business, 1000);

    Sanctum::actingAs($owner);

    $this->postJson("/api/businesses/{$business->id}/repayments/wallet", [
        'amount_minor' => 500,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('idempotency_key');
});

test('a wallet repayment cannot exceed the outstanding balance', function () {
    [$business, $owner] = manualRepaymentBusinessOwner();
    repayableManualAdvance($business, 1000);

    app(BusinessWalletService::class)->credit($business, 5000);

    Sanctum::actingAs($owner);

    $this->postJson("/api/businesses/{$business->id}/repayments/wallet", [
        'amount_minor' => 2000,
        'idempotency_key' => 'wallet-repayment-exceeds',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('amount_minor');

    expect(RepaymentCollection::query()->count())->toBe(0)
        ->and(app(BusinessWalletService::class)->balance($business))->toBe(5000);
});

test('an mpesa repayment initiates an stk push with the repayment purpose', function () {
    manualRepaymentStkConfig();
    [$business, $owner] = manualRepaymentBusinessOwner();
    repayableManualAdvance($business, 4000);

    fakeAcceptedStkPush();

    Sanctum::actingAs($owner);

    $this->postJson("/api/businesses/{$business->id}/repayments/mpesa", [
        'phone_number' => '0712345678',
        'amount_minor' => 1500,
    ])->assertCreated()
        ->assertJsonPath('data.purpose', 'repayment')
        ->assertJsonPath('data.amount_minor', 1500);

    $stkPush = StkPushRequest::query()->sole();

    expect($stkPush->purpose)->toBe(StkPushPurpose::Repayment)
        ->and($stkPush->phone_number)->toBe('254712345678')
        ->and($stkPush->transaction_desc)->toBe('QCash repay');

    Http::assertSent(function (Request $request): bool {
        return str_ends_with($request->url(), '/mpesa/stkpush/v1/processrequest')
            && $request['Amount'] === '15.00'
            && $request['TransactionDesc'] === 'QCash repay';
    });
});

test('an mpesa repayment cannot exceed the outstanding balance', function () {
    manualRepaymentStkConfig();
    [$business, $owner] = manualRepaymentBusinessOwner();
    repayableManualAdvance($business, 1000);

    fakeAcceptedStkPush();

    Sanctum::actingAs($owner);

    $this->postJson("/api/businesses/{$business->id}/repayments/mpesa", [
        'phone_number' => '0712345678',
        'amount_minor' => 2000,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('amount_minor');

    expect(StkPushRequest::query()->count())->toBe(0);
});

test('an mpesa repayment with no outstanding balance is rejected', function () {
    manualRepaymentStkConfig();
    [$business, $owner] = manualRepaymentBusinessOwner();

    Sanctum::actingAs($owner);

    $this->postJson("/api/businesses/{$business->id}/repayments/mpesa", [
        'phone_number' => '0712345678',
        'amount_minor' => 1000,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('amount_minor');

    expect(StkPushRequest::query()->count())->toBe(0);
});

test('a successful mpesa repayment callback settles the advance', function () {
    [$business, $owner] = manualRepaymentBusinessOwner();
    $advance = repayableManualAdvance($business, 1000);

    $stkPush = StkPushRequest::factory()->for($business)->create([
        'purpose' => StkPushPurpose::Repayment->value,
        'checkout_request_id' => 'ws_CO_REPAY_SETTLE',
        'amount_minor' => 1000,
    ]);

    $sms = new FakeSmsSender;
    $this->instance(SmsSender::class, $sms);

    $this->postJson('/api/webhooks/stk', manualRepaymentStkPayload($stkPush))
        ->assertOk()
        ->assertExactJson(['ResultCode' => '0', 'ResultDesc' => 'Success']);

    $transaction = Transaction::query()->sole();
    $collection = RepaymentCollection::query()->sole();

    expect($collection->business_id)->toBe($business->id)
        ->and($collection->collection_method)->toBe(CollectionMethod::ManualPayment)
        ->and($collection->provider)->toBe('mpesa')
        ->and($collection->provider_reference)->toBe('NLJ7RT61SV')
        ->and($collection->amount_minor)->toBe(1000)
        ->and($collection->status)->toBe(CollectionStatus::Successful)
        ->and($collection->source_transaction_id)->toBe($transaction->id)
        ->and($collection->allocations()->count())->toBe(1);

    $advance->refresh();

    expect($advance->outstanding_balance_minor)->toBe(0)
        ->and($advance->amount_repaid_minor)->toBe(1000)
        ->and($advance->status)->toBe(AdvanceStatus::Settled)
        ->and($sms->lastMessageFor($owner->phone_number))
        ->toBe('QCash: Loan repayment of KES 10.00 received. Your loan is fully repaid.')
        ->and($owner->notifications()->sole()->type)->toBe('repayment_received');
});

test('a successful mpesa repayment callback can partially reduce the balance', function () {
    [$business] = manualRepaymentBusinessOwner();
    $advance = repayableManualAdvance($business, 3000);

    $stkPush = StkPushRequest::factory()->for($business)->create([
        'purpose' => StkPushPurpose::Repayment->value,
        'checkout_request_id' => 'ws_CO_REPAY_PARTIAL',
        'amount_minor' => 1000,
    ]);

    $this->instance(SmsSender::class, new FakeSmsSender);

    $this->postJson('/api/webhooks/stk', manualRepaymentStkPayload($stkPush))
        ->assertOk();

    expect($advance->refresh()->outstanding_balance_minor)->toBe(2000)
        ->and($advance->amount_repaid_minor)->toBe(1000)
        ->and($advance->status)->toBe(AdvanceStatus::Active)
        ->and(RepaymentCollection::query()->sole()->amount_minor)->toBe(1000);
});

test('a duplicate mpesa repayment callback only records one collection', function () {
    [$business] = manualRepaymentBusinessOwner();
    $advance = repayableManualAdvance($business, 1000);

    $stkPush = StkPushRequest::factory()->for($business)->create([
        'purpose' => StkPushPurpose::Repayment->value,
        'checkout_request_id' => 'ws_CO_REPAY_DUPE',
        'amount_minor' => 1000,
    ]);

    $this->instance(SmsSender::class, new FakeSmsSender);

    $payload = manualRepaymentStkPayload($stkPush);

    $this->postJson('/api/webhooks/stk', $payload)->assertOk();
    $this->postJson('/api/webhooks/stk', $payload)->assertOk();

    expect(RepaymentCollection::query()->count())->toBe(1)
        ->and($advance->refresh()->amount_repaid_minor)->toBe(1000)
        ->and($advance->outstanding_balance_minor)->toBe(0);
});

test('a non owner cannot initiate a manual repayment', function () {
    manualRepaymentStkConfig();

    $business = Business::factory()->create();
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $this->postJson("/api/businesses/{$business->id}/repayments/wallet", [
        'amount_minor' => 1000,
        'idempotency_key' => 'wallet-repayment-forbidden',
    ])->assertForbidden();

    $this->postJson("/api/businesses/{$business->id}/repayments/mpesa", [
        'phone_number' => '0712345678',
        'amount_minor' => 1000,
    ])->assertForbidden();
});
