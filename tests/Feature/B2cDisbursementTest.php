<?php

use App\Enums\AdvanceStatus;
use App\Enums\DisbursementStatus;
use App\Enums\LedgerEntryType;
use App\Models\Advance;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\Disbursement;
use App\Models\User;
use App\Services\Disbursements\MpesaB2cDisbursementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Markt\LaravelAuth\Contracts\SmsSender;
use Tests\Fixtures\FakeSmsSender;

uses(RefreshDatabase::class);

function b2cSecurityCredential(): string
{
    return str_repeat('A', 344);
}

/**
 * @return array{0: string, 1: string}
 */
function b2cTestCertificate(): array
{
    $config = [
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ];

    $privateKey = openssl_pkey_new($config);
    $csr = openssl_csr_new(['commonName' => 'mpesa-test'], $privateKey, $config);
    $certificate = openssl_csr_sign($csr, null, $privateKey, 365, [], random_int(1, PHP_INT_MAX));

    openssl_x509_export($certificate, $certificatePem);
    openssl_pkey_export($privateKey, $privateKeyPem);

    $certificatePath = tempnam(sys_get_temp_dir(), 'mpesa-test-cert-');
    file_put_contents($certificatePath, $certificatePem);

    return [$certificatePath, $privateKeyPem];
}

function b2cConfig(): void
{
    config([
        'services.mpesa.environment' => 'sandbox',
        'services.mpesa.base_url' => null,
        'services.mpesa.shortcode' => '600000',
        'services.mpesa.b2c.shortcode' => '600000',
        'services.mpesa.b2c.initiator_name' => 'testapi',
        'services.mpesa.b2c.security_credential' => '"'.b2cSecurityCredential().'"',
        'services.mpesa.b2c.command_id' => 'BusinessPayment',
        'services.mpesa.b2c.result_url' => 'https://example.test/api/webhooks/b2c/result',
        'services.mpesa.b2c.timeout_url' => 'https://example.test/api/webhooks/b2c/timeout',
    ]);
}

/**
 * @return array{0: Business, 1: User}
 */
function b2cBusinessOwner(string $phoneNumber = '254712345678'): array
{
    $business = Business::factory()->create();
    $owner = User::factory()->create(['phone_number' => $phoneNumber]);

    BusinessUser::factory()->for($business)->for($owner)->create();

    return [$business, $owner];
}

function b2cAdvance(Business $business, int $principal = 3000000): Advance
{
    return Advance::factory()->create([
        'business_id' => $business->id,
        'principal_amount_minor' => $principal,
        'fee_amount_minor' => 0,
        'total_repayable_minor' => $principal,
        'outstanding_balance_minor' => $principal,
        'maximum_repayment_days' => 30,
        'status' => AdvanceStatus::Approved->value,
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function b2cResultPayload(Disbursement $disbursement, array $overrides = []): array
{
    return [
        'Result' => array_merge([
            'ResultType' => 0,
            'ResultCode' => 0,
            'ResultDesc' => 'The service request is processed successfully.',
            'OriginatorConversationID' => $disbursement->originator_conversation_id,
            'ConversationID' => $disbursement->provider_reference,
            'TransactionID' => 'NLJ41HAY6Q',
            'ResultParameters' => [
                'ResultParameter' => [
                    ['Key' => 'TransactionAmount', 'Value' => 30000.00],
                    ['Key' => 'TransactionReceipt', 'Value' => 'NLJ41HAY6Q'],
                ],
            ],
        ], $overrides),
    ];
}

function processingB2cDisbursement(Advance $advance): Disbursement
{
    return Disbursement::factory()->for($advance)->create([
        'provider' => 'mpesa_b2c',
        'provider_reference' => 'AG_20260115_00001',
        'originator_conversation_id' => 'originator-1',
        'amount_minor' => 3000000,
        'status' => DisbursementStatus::Processing->value,
        'requested_at' => now(),
    ]);
}

test('disbursement sends the advance principal to the owner phone via b2c', function () {
    b2cConfig();
    [$business, $owner] = b2cBusinessOwner();
    $advance = b2cAdvance($business);

    Http::preventStrayRequests();

    Http::fake([
        'https://sandbox.safaricom.co.ke/oauth/v1/generate*' => Http::response([
            'access_token' => 'test-token',
        ]),
        'https://sandbox.safaricom.co.ke/mpesa/b2c/v1/paymentrequest' => Http::response([
            'ConversationID' => 'AG_20260115_00001',
            'OriginatorConversationID' => 'ignored',
            'ResponseCode' => '0',
            'ResponseDescription' => 'Accept the service request successfully.',
        ]),
    ]);

    Sanctum::actingAs($owner);

    $this->postJson("/api/advances/{$advance->id}/disburse")
        ->assertAccepted()
        ->assertJsonPath('data.status', 'disbursing');

    $disbursement = Disbursement::query()->sole();

    expect($disbursement->provider)->toBe('mpesa_b2c')
        ->and($disbursement->status)->toBe(DisbursementStatus::Processing)
        ->and($disbursement->amount_minor)->toBe(3000000)
        ->and($disbursement->provider_reference)->toBe('AG_20260115_00001')
        ->and($disbursement->originator_conversation_id)->not->toBeNull()
        ->and($disbursement->provider_response['recipient_phone_number'])->toBe('254712345678');

    expect($advance->refresh()->status)->toBe(AdvanceStatus::Disbursing);

    Http::assertSent(function (Request $request) use ($owner, $disbursement): bool {
        if (! str_ends_with($request->url(), '/mpesa/b2c/v1/paymentrequest')) {
            return false;
        }

        return $request['OriginatorConversationID'] === $disbursement->originator_conversation_id
            && $request['InitiatorName'] === 'testapi'
            && $request['SecurityCredential'] === b2cSecurityCredential()
            && $request['CommandID'] === 'BusinessPayment'
            && $request['Amount'] === '30000.00'
            && $request['PartyA'] === '600000'
            && $request['PartyB'] === $owner->phone_number
            && $request['Remarks'] === 'QCash advance'
            && $request['QueueTimeOutURL'] === 'https://example.test/api/webhooks/b2c/timeout'
            && $request['ResultURL'] === 'https://example.test/api/webhooks/b2c/result'
            && $request['Occasion'] === 'QCash advance';
    });
});

test('a disbursement cannot be sent when the owner has no phone number', function () {
    b2cConfig();

    $business = Business::factory()->create();
    $owner = User::factory()->create(['phone_number' => null]);
    BusinessUser::factory()->for($business)->for($owner)->create();
    $advance = b2cAdvance($business);

    expect(fn () => app(MpesaB2cDisbursementService::class)->disburse($advance))
        ->toThrow(ValidationException::class);

    expect(Disbursement::query()->count())->toBe(0);
});

test('a plaintext initiator password is encrypted at runtime', function () {
    b2cConfig();
    [$certificatePath, $privateKeyPem] = b2cTestCertificate();

    config([
        'services.mpesa.b2c.security_credential' => null,
        'services.mpesa.b2c.initiator_password' => 'secret-password',
        'services.mpesa.b2c.certificate_path' => $certificatePath,
    ]);

    [$business] = b2cBusinessOwner();
    $advance = b2cAdvance($business);

    Http::preventStrayRequests();

    Http::fake([
        'https://sandbox.safaricom.co.ke/oauth/v1/generate*' => Http::response([
            'access_token' => 'test-token',
        ]),
        'https://sandbox.safaricom.co.ke/mpesa/b2c/v1/paymentrequest' => Http::response([
            'ConversationID' => 'AG_20260115_00003',
            'ResponseCode' => '0',
            'ResponseDescription' => 'Accept the service request successfully.',
        ]),
    ]);

    app(MpesaB2cDisbursementService::class)->disburse($advance);

    Http::assertSent(function (Request $request) use ($privateKeyPem): bool {
        if (! str_ends_with($request->url(), '/mpesa/b2c/v1/paymentrequest')) {
            return false;
        }

        $credential = (string) $request['SecurityCredential'];
        $encrypted = base64_decode($credential, true);

        if ($encrypted === false || $credential === 'secret-password' || strlen($credential) < 100) {
            return false;
        }

        $decrypted = '';

        return openssl_private_decrypt($encrypted, $decrypted, $privateKeyPem, OPENSSL_PKCS1_PADDING)
            && $decrypted === 'secret-password';
    });
});

test('a missing b2c certificate fails before anything is created', function () {
    b2cConfig();
    config([
        'services.mpesa.b2c.security_credential' => null,
        'services.mpesa.b2c.initiator_password' => 'secret-password',
        'services.mpesa.b2c.certificate_path' => '/path/does/not/exist.pem',
    ]);

    [$business] = b2cBusinessOwner();
    $advance = b2cAdvance($business);

    expect(fn () => app(MpesaB2cDisbursementService::class)->disburse($advance))
        ->toThrow(RuntimeException::class, 'The M-Pesa B2C certificate file could not be read');

    expect(Disbursement::query()->count())->toBe(0);
});

test('a rejected b2c request fails the disbursement and returns the advance to pending', function () {
    b2cConfig();
    [$business] = b2cBusinessOwner();
    $advance = b2cAdvance($business);

    Http::preventStrayRequests();

    Http::fake([
        'https://sandbox.safaricom.co.ke/oauth/v1/generate*' => Http::response([
            'access_token' => 'test-token',
        ]),
        'https://sandbox.safaricom.co.ke/mpesa/b2c/v1/paymentrequest' => Http::response([
            'ConversationID' => 'AG_20260115_00002',
            'ResponseCode' => '1',
            'ResponseDescription' => 'The initiator information is invalid.',
        ]),
    ]);

    expect(fn () => app(MpesaB2cDisbursementService::class)->disburse($advance))
        ->toThrow(RuntimeException::class, 'The initiator information is invalid.');

    $disbursement = Disbursement::query()->sole();

    expect($disbursement->status)->toBe(DisbursementStatus::Failed)
        ->and($disbursement->failure_reason)->toBe('The initiator information is invalid.')
        ->and($advance->refresh()->status)->toBe(AdvanceStatus::Pending);
});

test('a successful b2c result activates the advance and notifies the owner', function () {
    b2cConfig();
    [$business, $owner] = b2cBusinessOwner();
    $advance = b2cAdvance($business);
    $disbursement = processingB2cDisbursement($advance);

    $sms = new FakeSmsSender;
    $this->instance(SmsSender::class, $sms);

    $this->postJson('/api/webhooks/b2c/result', b2cResultPayload($disbursement))
        ->assertOk()
        ->assertExactJson(['ResultCode' => '0', 'ResultDesc' => 'Success']);

    $advance->refresh();
    $disbursement->refresh();

    expect($disbursement->status)->toBe(DisbursementStatus::Successful)
        ->and($disbursement->provider_reference)->toBe('NLJ41HAY6Q')
        ->and($advance->status)->toBe(AdvanceStatus::Active)
        ->and($advance->ledgerEntries()->where('entry_type', LedgerEntryType::PrincipalDisbursed->value)->count())->toBe(1)
        ->and($sms->lastMessageFor($owner->phone_number))
        ->toBe('QCash: A cash advance of KES 30,000.00 has been credited to your acct. Ref: NLJ41HAY6Q. Thank you for using QCash.');
});

test('a failed b2c result fails the disbursement without sending an sms', function () {
    b2cConfig();
    [$business, $owner] = b2cBusinessOwner();
    $advance = b2cAdvance($business);
    $disbursement = processingB2cDisbursement($advance);

    $sms = new FakeSmsSender;
    $this->instance(SmsSender::class, $sms);

    $payload = b2cResultPayload($disbursement, [
        'ResultCode' => 1,
        'ResultDesc' => 'The balance is insufficient for the transaction.',
        'ResultParameters' => null,
    ]);

    $this->postJson('/api/webhooks/b2c/result', $payload)->assertOk();

    expect($disbursement->refresh()->status)->toBe(DisbursementStatus::Failed)
        ->and($advance->refresh()->status)->toBe(AdvanceStatus::Pending)
        ->and($sms->messages[$owner->phone_number] ?? [])->toBeEmpty();
});

test('a duplicate successful b2c result is only processed once', function () {
    b2cConfig();
    [$business, $owner] = b2cBusinessOwner();
    $advance = b2cAdvance($business);
    $disbursement = processingB2cDisbursement($advance);

    $sms = new FakeSmsSender;
    $this->instance(SmsSender::class, $sms);

    $payload = b2cResultPayload($disbursement);

    $this->postJson('/api/webhooks/b2c/result', $payload)->assertOk();
    $this->postJson('/api/webhooks/b2c/result', $payload)->assertOk();

    expect($advance->refresh()->ledgerEntries()->where('entry_type', LedgerEntryType::PrincipalDisbursed->value)->count())->toBe(1)
        ->and($sms->messages[$owner->phone_number] ?? [])->toHaveCount(1);
});

test('a second successful b2c result for the same advance is rejected', function () {
    b2cConfig();
    [$business, $owner] = b2cBusinessOwner();
    $advance = b2cAdvance($business);
    $first = processingB2cDisbursement($advance);

    $second = Disbursement::factory()->for($advance)->create([
        'provider' => 'mpesa_b2c',
        'provider_reference' => 'AG_20260115_00002',
        'originator_conversation_id' => 'originator-2',
        'amount_minor' => 3000000,
        'status' => DisbursementStatus::Processing->value,
        'requested_at' => now(),
    ]);

    $sms = new FakeSmsSender;
    $this->instance(SmsSender::class, $sms);

    $this->postJson('/api/webhooks/b2c/result', b2cResultPayload($first))->assertOk();
    $this->postJson('/api/webhooks/b2c/result', b2cResultPayload($second, [
        'TransactionID' => 'NLJ41HAY6Q2',
        'ResultParameters' => [
            'ResultParameter' => [
                ['Key' => 'TransactionAmount', 'Value' => 30000.00],
                ['Key' => 'TransactionReceipt', 'Value' => 'NLJ41HAY6Q2'],
            ],
        ],
    ]))->assertOk();

    expect($first->refresh()->status)->toBe(DisbursementStatus::Successful)
        ->and($second->refresh()->status)->toBe(DisbursementStatus::Failed)
        ->and($second->failure_reason)->toBe('Duplicate disbursement; this advance was already disbursed.')
        ->and($advance->refresh()->ledgerEntries()->where('entry_type', LedgerEntryType::PrincipalDisbursed->value)->count())->toBe(1)
        ->and($sms->messages[$owner->phone_number] ?? [])->toHaveCount(1);
});

test('an unknown b2c result is acknowledged without changing anything', function () {
    $payload = [
        'Result' => [
            'ResultCode' => 0,
            'ResultDesc' => 'The service request is processed successfully.',
            'OriginatorConversationID' => 'unknown-originator',
            'ConversationID' => 'unknown-conversation',
        ],
    ];

    $this->postJson('/api/webhooks/b2c/result', $payload)
        ->assertOk()
        ->assertExactJson(['ResultCode' => '0', 'ResultDesc' => 'Success']);

    expect(Disbursement::query()->count())->toBe(0);
});

test('a b2c timeout fails the disbursement and returns the advance to pending', function () {
    b2cConfig();
    [$business] = b2cBusinessOwner();
    $advance = b2cAdvance($business);
    $disbursement = processingB2cDisbursement($advance);

    $payload = b2cResultPayload($disbursement, [
        'ResultCode' => 1,
        'ResultDesc' => 'Request timed out.',
        'ResultParameters' => null,
    ]);

    $this->postJson('/api/webhooks/b2c/timeout', $payload)->assertOk();

    expect($disbursement->refresh()->status)->toBe(DisbursementStatus::Failed)
        ->and($disbursement->failure_reason)->toBe('The B2C request timed out.')
        ->and($advance->refresh()->status)->toBe(AdvanceStatus::Pending);
});
