<?php

use App\Enums\BusinessWalletEntryType;
use App\Enums\BusinessWithdrawalStatus;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\BusinessWithdrawal;
use App\Models\User;
use App\Services\Wallets\BusinessWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Markt\LaravelAuth\Contracts\SmsSender;
use Tests\Fixtures\FakeSmsSender;

uses(RefreshDatabase::class);

function reconciliationB2cConfig(): void
{
    config([
        'services.mpesa.environment' => 'sandbox',
        'services.mpesa.base_url' => null,
        'services.mpesa.shortcode' => '600000',
        'services.mpesa.b2c.shortcode' => '600426',
        'services.mpesa.b2c.initiator_name' => 'testapi',
        'services.mpesa.b2c.security_credential' => str_repeat('A', 344),
        'services.mpesa.b2c.command_id' => 'BusinessPayment',
        'services.mpesa.b2c.result_url' => 'https://example.test/api/webhooks/b2c/result',
        'services.mpesa.b2c.timeout_url' => 'https://example.test/api/webhooks/b2c/timeout',
        'services.mpesa.b2c.status_result_url' => 'https://example.test/api/webhooks/b2c/status/result',
        'services.mpesa.b2c.status_timeout_url' => 'https://example.test/api/webhooks/b2c/status/timeout',
        'services.mpesa.b2c.reconcile_after_minutes' => 5,
        'services.mpesa.b2c.reconcile_interval_minutes' => 10,
        'services.mpesa.b2c.reconcile_max_attempts' => 6,
        'services.mpesa.b2c.reconcile_request_delay_ms' => 0,
    ]);
}

/**
 * @return array{0: Business, 1: User}
 */
function reconciliationBusinessOwner(): array
{
    $business = Business::factory()->create();
    $owner = User::factory()->create(['phone_number' => '254712345678']);

    BusinessUser::factory()->for($business)->for($owner)->create();

    return [$business, $owner];
}

function fakeB2cStatusQuery(string $conversationId = 'AG_STATUS_1'): void
{
    Http::preventStrayRequests();

    Http::fake([
        'https://sandbox.safaricom.co.ke/oauth/v1/generate*' => Http::response([
            'access_token' => 'test-token',
        ]),
        'https://sandbox.safaricom.co.ke/mpesa/transactionstatus/v1/query' => Http::response([
            'ConversationID' => $conversationId,
            'OriginatorConversationID' => 'status-originator',
            'ResponseCode' => '0',
            'ResponseDescription' => 'Accept the service request successfully.',
        ]),
    ]);
}

/**
 * @return array<string, mixed>
 */
function b2cStatusResultPayload(BusinessWithdrawal $withdrawal, string $status = 'Completed', string $receipt = 'LGR919G2AV'): array
{
    return [
        'Result' => [
            'ResultType' => 0,
            'ResultCode' => 0,
            'ResultDesc' => 'The service request has been accepted successfully.',
            'OriginatorConversationID' => 'status-originator',
            'ConversationID' => 'AG_STATUS_1',
            'TransactionID' => 'LGR0000000',
            'ResultParameters' => [
                'ResultParameter' => [
                    ['Key' => 'ReceiptNo', 'Value' => $receipt],
                    ['Key' => 'Conversation ID', 'Value' => $withdrawal->provider_reference],
                    ['Key' => 'FinalisedTime', 'Value' => 20261113101515],
                    ['Key' => 'Amount', 'Value' => 30],
                    ['Key' => 'TransactionStatus', 'Value' => $status],
                    ['Key' => 'Originator Conversation ID', 'Value' => $withdrawal->originator_conversation_id],
                    ['Key' => 'ReasonType', 'Value' => 'Business Payment via API'],
                ],
            ],
        ],
    ];
}

test('the reconcile command queries stale processing withdrawals by originator conversation id', function () {
    reconciliationB2cConfig();
    [$business] = reconciliationBusinessOwner();

    $withdrawal = BusinessWithdrawal::factory()->for($business)->create([
        'status' => BusinessWithdrawalStatus::Processing->value,
        'originator_conversation_id' => 'originator-reconcile-1',
        'provider_reference' => 'AG_RECONCILE_1',
        'created_at' => now()->subMinutes(10),
    ]);

    $recent = BusinessWithdrawal::factory()->for($business)->create([
        'status' => BusinessWithdrawalStatus::Processing->value,
        'created_at' => now(),
    ]);

    fakeB2cStatusQuery();

    $this->artisan('mpesa:reconcile-b2c-withdrawals')->assertSuccessful();

    $withdrawal->refresh();

    expect($withdrawal->reconciliation_attempts)->toBe(1)
        ->and($withdrawal->reconciliation_conversation_id)->toBe('AG_STATUS_1')
        ->and($withdrawal->reconciliation_requested_at)->not->toBeNull()
        ->and($recent->refresh()->reconciliation_attempts)->toBe(0);

    Http::assertSent(function (Request $request): bool {
        if (! str_ends_with($request->url(), '/mpesa/transactionstatus/v1/query')) {
            return false;
        }

        return $request['CommandID'] === 'TransactionStatusQuery'
            && $request['TransactionID'] === 'originator-reconcile-1'
            && $request['PartyA'] === '600426'
            && $request['IdentifierType'] === '4'
            && $request['Initiator'] === 'testapi'
            && $request['ResultURL'] === 'https://example.test/api/webhooks/b2c/status/result'
            && $request['QueueTimeOutURL'] === 'https://example.test/api/webhooks/b2c/status/timeout';
    });
});

test('a completed status result resolves the withdrawal and notifies the owner', function () {
    reconciliationB2cConfig();
    [$business, $owner] = reconciliationBusinessOwner();

    $wallets = app(BusinessWalletService::class);
    $wallets->credit($business, 5000);

    $withdrawal = BusinessWithdrawal::factory()->for($business)->create([
        'amount_minor' => 3000,
        'recipient_phone_number' => $owner->phone_number,
        'status' => BusinessWithdrawalStatus::Processing->value,
        'provider_reference' => 'AG_RECONCILE_2',
        'originator_conversation_id' => 'originator-reconcile-2',
        'reconciliation_conversation_id' => 'AG_STATUS_1',
    ]);

    $wallets->debit($business, 3000, $withdrawal);

    $sms = new FakeSmsSender;
    $this->instance(SmsSender::class, $sms);

    $this->postJson('/api/webhooks/b2c/status/result', b2cStatusResultPayload($withdrawal))
        ->assertOk()
        ->assertExactJson(['ResultCode' => '0', 'ResultDesc' => 'Success']);

    $withdrawal->refresh();
    $wallet = $business->wallet()->firstOrFail();

    expect($withdrawal->status)->toBe(BusinessWithdrawalStatus::Successful)
        ->and($withdrawal->provider_reference)->toBe('LGR919G2AV')
        ->and($wallet->balance_minor)->toBe(2000)
        ->and($wallet->entries()->where('entry_type', BusinessWalletEntryType::Refund->value)->count())->toBe(0)
        ->and($sms->lastMessageFor($owner->phone_number))
        ->toBe('QCash: A withdrawal of KES 30.00 has been sent to your M-Pesa account. Ref: LGR919G2AV. Thank you for using QCash.');
});

test('a declined status result refunds the wallet and marks the withdrawal failed', function () {
    reconciliationB2cConfig();
    [$business] = reconciliationBusinessOwner();

    $wallets = app(BusinessWalletService::class);
    $wallets->credit($business, 5000);

    $withdrawal = BusinessWithdrawal::factory()->for($business)->create([
        'amount_minor' => 3000,
        'status' => BusinessWithdrawalStatus::Processing->value,
        'provider_reference' => 'AG_RECONCILE_3',
        'originator_conversation_id' => 'originator-reconcile-3',
        'reconciliation_conversation_id' => 'AG_STATUS_1',
    ]);

    $wallets->debit($business, 3000, $withdrawal);

    $payload = b2cStatusResultPayload($withdrawal, 'Declined', '');
    $payload['Result']['ResultParameters']['ResultParameter'][6]['Value'] = 'The transaction was declined.';

    $this->postJson('/api/webhooks/b2c/status/result', $payload)->assertOk();

    $withdrawal->refresh();
    $wallet = $business->wallet()->firstOrFail();

    expect($withdrawal->status)->toBe(BusinessWithdrawalStatus::Failed)
        ->and($withdrawal->failure_reason)->toBe('The transaction was declined.')
        ->and($wallet->balance_minor)->toBe(5000)
        ->and($wallet->entries()->where('entry_type', BusinessWalletEntryType::Refund->value)->count())->toBe(1);
});

test('an in progress status result leaves the withdrawal processing', function () {
    reconciliationB2cConfig();
    [$business] = reconciliationBusinessOwner();

    $wallets = app(BusinessWalletService::class);
    $wallets->credit($business, 5000);

    $withdrawal = BusinessWithdrawal::factory()->for($business)->create([
        'amount_minor' => 3000,
        'status' => BusinessWithdrawalStatus::Processing->value,
        'provider_reference' => 'AG_RECONCILE_4',
        'originator_conversation_id' => 'originator-reconcile-4',
        'reconciliation_conversation_id' => 'AG_STATUS_1',
    ]);

    $wallets->debit($business, 3000, $withdrawal);

    $this->postJson('/api/webhooks/b2c/status/result', b2cStatusResultPayload($withdrawal, 'Initiated', ''))
        ->assertOk();

    expect($withdrawal->refresh()->status)->toBe(BusinessWithdrawalStatus::Processing)
        ->and($business->wallet()->firstOrFail()->balance_minor)->toBe(2000);
});

test('a blocked status query does not count as a reconciliation attempt', function () {
    reconciliationB2cConfig();
    [$business] = reconciliationBusinessOwner();

    $withdrawal = BusinessWithdrawal::factory()->for($business)->create([
        'status' => BusinessWithdrawalStatus::Processing->value,
        'originator_conversation_id' => 'originator-reconcile-6',
        'provider_reference' => 'AG_RECONCILE_6',
        'created_at' => now()->subMinutes(10),
    ]);

    Http::preventStrayRequests();

    Http::fake([
        'https://sandbox.safaricom.co.ke/oauth/v1/generate*' => Http::response([
            'access_token' => 'test-token',
        ]),
        'https://sandbox.safaricom.co.ke/mpesa/transactionstatus/v1/query' => Http::response('blocked', 403),
    ]);

    $this->artisan('mpesa:reconcile-b2c-withdrawals')->assertSuccessful();

    $withdrawal->refresh();

    expect($withdrawal->status)->toBe(BusinessWithdrawalStatus::Processing)
        ->and($withdrawal->reconciliation_attempts)->toBe(0)
        ->and($withdrawal->reconciliation_requested_at)->toBeNull();
});

test('the reconcile command marks withdrawals timed out after the maximum attempts', function () {
    reconciliationB2cConfig();
    config(['services.mpesa.b2c.reconcile_max_attempts' => 3]);

    [$business] = reconciliationBusinessOwner();

    $withdrawal = BusinessWithdrawal::factory()->for($business)->create([
        'status' => BusinessWithdrawalStatus::Processing->value,
        'originator_conversation_id' => 'originator-reconcile-5',
        'provider_reference' => 'AG_RECONCILE_5',
        'reconciliation_attempts' => 2,
        'created_at' => now()->subMinutes(10),
    ]);

    fakeB2cStatusQuery();

    $this->artisan('mpesa:reconcile-b2c-withdrawals')->assertSuccessful();

    $withdrawal->refresh();

    expect($withdrawal->status)->toBe(BusinessWithdrawalStatus::TimedOut)
        ->and($withdrawal->reconciliation_attempts)->toBe(3)
        ->and($withdrawal->failure_reason)->toBe('The B2C transaction status could not be confirmed; the debit is held for reconciliation.');
});
