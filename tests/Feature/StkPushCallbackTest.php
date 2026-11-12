<?php

use App\Enums\StkPushStatus;
use App\Enums\TransactionDirection;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\StkPushRequest;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Markt\LaravelAuth\Contracts\SmsSender;
use Tests\Fixtures\FakeSmsSender;

uses(RefreshDatabase::class);

/**
 * @return array{0: Business, 1: User}
 */
function stkPushCallbackBusinessOwner(): array
{
    $business = Business::factory()->create();
    $owner = User::factory()->create(['phone_number' => '254712345678']);

    BusinessUser::factory()->for($business)->for($owner)->create();

    return [$business, $owner];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function stkPushCallbackPayload(StkPushRequest $stkPush, array $overrides = []): array
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
                        ['Name' => 'Amount', 'Value' => 10.00],
                        ['Name' => 'MpesaReceiptNumber', 'Value' => 'NLJ7RT61SV'],
                        ['Name' => 'TransactionDate', 'Value' => 20260115123045],
                        ['Name' => 'PhoneNumber', 'Value' => 254708374149],
                    ],
                ],
            ], $overrides),
        ],
    ];
}

test('a successful stk callback creates a transaction and notifies the owner', function () {
    [$business, $owner] = stkPushCallbackBusinessOwner();

    $stkPush = StkPushRequest::factory()->for($business)->create([
        'checkout_request_id' => 'ws_CO_TEST1',
    ]);

    $sms = new FakeSmsSender;
    $this->instance(SmsSender::class, $sms);

    $this->postJson('/api/webhooks/stk', stkPushCallbackPayload($stkPush))
        ->assertOk()
        ->assertExactJson(['ResultCode' => '0', 'ResultDesc' => 'Success']);

    $transaction = Transaction::query()->sole();

    expect($transaction->business_id)->toBe($business->id)
        ->and($transaction->provider)->toBe('mpesa')
        ->and($transaction->provider_transaction_id)->toBe('NLJ7RT61SV')
        ->and($transaction->transaction_type)->toBe('stk_push')
        ->and($transaction->direction)->toBe(TransactionDirection::Inflow)
        ->and($transaction->amount_minor)->toBe(1000)
        ->and($transaction->currency)->toBe('KES')
        ->and($transaction->counterparty_reference)->toBe('254708374149')
        ->and($transaction->transaction_time->toDateTimeString())->toBe('2026-01-15 09:30:45');

    $stkPush->refresh();

    expect($stkPush->status)->toBe(StkPushStatus::Successful)
        ->and($stkPush->transaction_id)->toBe($transaction->id)
        ->and($sms->lastMessageFor($owner->phone_number))
        ->toBe('Confirmed. STK push for KES 10.00 has been paid successfully by phone number 254708374149 on 15/01/26 at 12:30 PM.');
});

test('a duplicate stk callback is only processed once', function () {
    [$business, $owner] = stkPushCallbackBusinessOwner();

    $stkPush = StkPushRequest::factory()->for($business)->create([
        'checkout_request_id' => 'ws_CO_TEST2',
    ]);

    $sms = new FakeSmsSender;
    $this->instance(SmsSender::class, $sms);

    $payload = stkPushCallbackPayload($stkPush);

    $this->postJson('/api/webhooks/stk', $payload)->assertOk();
    $this->postJson('/api/webhooks/stk', $payload)->assertOk();

    expect(Transaction::query()->count())->toBe(1)
        ->and($sms->messages[$owner->phone_number] ?? [])->toHaveCount(1)
        ->and($stkPush->refresh()->status)->toBe(StkPushStatus::Successful);
});

test('a failed stk callback records the failure without creating a transaction', function () {
    $stkPush = StkPushRequest::factory()->create([
        'checkout_request_id' => 'ws_CO_TEST3',
    ]);

    $payload = stkPushCallbackPayload($stkPush, [
        'ResultCode' => 1032,
        'ResultDesc' => 'Request cancelled by user',
        'CallbackMetadata' => null,
    ]);

    $this->postJson('/api/webhooks/stk', $payload)
        ->assertOk()
        ->assertExactJson(['ResultCode' => '0', 'ResultDesc' => 'Success']);

    expect(Transaction::query()->count())->toBe(0);

    $stkPush->refresh();

    expect($stkPush->status)->toBe(StkPushStatus::Cancelled)
        ->and($stkPush->result_code)->toBe('1032')
        ->and($stkPush->result_desc)->toBe('Request cancelled by user');
});

test('an stk callback without metadata marks the request as failed', function () {
    $stkPush = StkPushRequest::factory()->create([
        'checkout_request_id' => 'ws_CO_TEST4',
    ]);

    $payload = stkPushCallbackPayload($stkPush, [
        'CallbackMetadata' => null,
    ]);

    $this->postJson('/api/webhooks/stk', $payload)->assertOk();

    expect(Transaction::query()->count())->toBe(0)
        ->and($stkPush->refresh()->status)->toBe(StkPushStatus::Failed)
        ->and($stkPush->result_desc)->toBe('The STK callback metadata is incomplete.');
});

test('an unknown checkout request id is acknowledged without creating anything', function () {
    $payload = [
        'Body' => [
            'stkCallback' => [
                'MerchantRequestID' => 'unknown',
                'CheckoutRequestID' => 'ws_CO_UNKNOWN',
                'ResultCode' => 0,
                'ResultDesc' => 'The service request is processed successfully.',
            ],
        ],
    ];

    $this->postJson('/api/webhooks/stk', $payload)
        ->assertOk()
        ->assertExactJson(['ResultCode' => '0', 'ResultDesc' => 'Success']);

    expect(Transaction::query()->count())->toBe(0)
        ->and(StkPushRequest::query()->count())->toBe(0);
});
