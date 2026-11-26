<?php

use App\Enums\AdvanceStatus;
use App\Enums\BusinessUserStatus;
use App\Enums\DisbursementStatus;
use App\Enums\OfferStatus;
use App\Models\Advance;
use App\Models\AdvanceOffer;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\BusinessWithdrawal;
use App\Models\Disbursement;
use App\Models\EraAssessment;
use App\Models\StkPushRequest;
use App\Models\User;
use App\Services\Wallets\BusinessWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Markt\LaravelAuth\Contracts\SmsSender;
use Tests\Fixtures\FakeSmsSender;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.africastalking.ussd_service_code' => '*384*123#']);
});

const USSD_OWNER_PHONE = '254711111111';

/**
 * @return array{0: Business, 1: User}
 */
function ussdBusinessOwner(): array
{
    $business = Business::factory()->create(['business_name' => 'Mama Njeri Shop']);
    $owner = User::factory()->create(['phone_number' => USSD_OWNER_PHONE]);

    BusinessUser::factory()->for($business)->for($owner)->create();

    return [$business, $owner];
}

function ussdPayload(string $sessionId, string $phoneNumber, string $text): array
{
    return [
        'sessionId' => $sessionId,
        'serviceCode' => '*384*123#',
        'phoneNumber' => $phoneNumber,
        'text' => $text,
    ];
}

function ussdStkConfig(): void
{
    config([
        'services.mpesa.shortcode' => '174379',
        'services.mpesa.passkey' => 'test-passkey',
        'services.mpesa.stk_callback_url' => 'https://example.test/api/webhooks/stk',
        'services.mpesa.environment' => 'sandbox',
        'services.mpesa.base_url' => null,
    ]);
}

function ussdFakeStk(): void
{
    Http::preventStrayRequests();

    Http::fake([
        'https://sandbox.safaricom.co.ke/oauth/v1/generate*' => Http::response([
            'access_token' => 'test-token',
            'expires_in' => '3599',
        ]),
        'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest' => Http::response([
            'MerchantRequestID' => '29115-34620561-1',
            'CheckoutRequestID' => 'ws_CO_USSD_1',
            'ResponseCode' => '0',
            'ResponseDescription' => 'Success. Request accepted for processing',
            'CustomerMessage' => 'Success. Request accepted for processing',
        ]),
    ]);
}

function ussdB2cConfig(): void
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

function ussdFakeB2c(): void
{
    Http::preventStrayRequests();

    Http::fake([
        'https://sandbox.safaricom.co.ke/oauth/v1/generate*' => Http::response([
            'access_token' => 'test-token',
        ]),
        'https://sandbox.safaricom.co.ke/mpesa/b2c/v3/paymentrequest' => Http::response([
            'ConversationID' => 'AG_USSD_1',
            'OriginatorConversationID' => 'ignored',
            'ResponseCode' => '0',
            'ResponseDescription' => 'Accept the service request successfully.',
        ]),
    ]);
}

test('an unregistered number cannot use the service', function () {
    $response = $this->post('/api/webhooks/ussd', ussdPayload(
        'session-unregistered',
        '254799999999',
        '',
    ));

    expect($response->getContent())->toBe('END This number is not registered for QCash.');
});

test('a number without an active owner business is rejected', function () {
    User::factory()->create(['phone_number' => USSD_OWNER_PHONE]);

    $response = $this->post('/api/webhooks/ussd', ussdPayload(
        'session-no-business',
        USSD_OWNER_PHONE,
        '',
    ));

    expect($response->getContent())->toBe('END No active business is linked to this number.');
});

test('a single business owner lands on the main menu', function () {
    ussdBusinessOwner();

    $response = $this->post('/api/webhooks/ussd', ussdPayload(
        'session-main',
        USSD_OWNER_PHONE,
        '',
    ));

    expect($response->getContent())
        ->toStartWith('CON Mama Njeri Shop')
        ->toContain('1. Collect payment')
        ->toContain('2. Cash advance')
        ->toContain('3. Withdraw');
});

test('an owner with multiple businesses can choose one', function () {
    ussdBusinessOwner();

    $second = Business::factory()->create(['business_name' => 'Second Shop']);
    BusinessUser::factory()
        ->for($second)
        ->for(User::query()->where('phone_number', USSD_OWNER_PHONE)->sole())
        ->create();

    $response = $this->post('/api/webhooks/ussd', ussdPayload(
        'session-multiple',
        USSD_OWNER_PHONE,
        '',
    ));

    expect($response->getContent())
        ->toStartWith('CON Select business')
        ->toContain('1. Mama Njeri Shop')
        ->toContain('2. Second Shop');

    $selected = $this->post('/api/webhooks/ussd', ussdPayload(
        'session-multiple',
        USSD_OWNER_PHONE,
        '1',
    ));

    expect($selected->getContent())
        ->toStartWith('CON Mama Njeri Shop')
        ->toContain('1. Collect payment');
});

test('an owner can collect a payment with an stk push', function () {
    ussdStkConfig();
    ussdFakeStk();
    ussdBusinessOwner();

    $this->post('/api/webhooks/ussd', ussdPayload('session-collect', USSD_OWNER_PHONE, ''));
    $this->post('/api/webhooks/ussd', ussdPayload('session-collect', USSD_OWNER_PHONE, '1'));
    $this->post('/api/webhooks/ussd', ussdPayload('session-collect', USSD_OWNER_PHONE, '1*0722222222'));

    $confirm = $this->post('/api/webhooks/ussd', ussdPayload(
        'session-collect',
        USSD_OWNER_PHONE,
        '1*0722222222*1500',
    ));

    expect($confirm->getContent())->toContain('Send STK of KES 1,500.00 to 254722222222?');

    $response = $this->post('/api/webhooks/ussd', ussdPayload(
        'session-collect',
        USSD_OWNER_PHONE,
        '1*0722222222*1500*1',
    ));

    expect($response->getContent())->toStartWith('END STK push sent to 254722222222.');

    $stkPush = StkPushRequest::query()->sole();

    expect($stkPush->purpose->value)->toBe('payment')
        ->and($stkPush->phone_number)->toBe('254722222222')
        ->and($stkPush->amount_minor)->toBe(150000)
        ->and($stkPush->transaction_desc)->toBe('QCash payment');

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/mpesa/stkpush/v1/processrequest')
        && $request['Amount'] === '1500.00'
        && $request['PhoneNumber'] === '254722222222');
});

test('an owner can check the available advance', function () {
    [$business] = ussdBusinessOwner();

    EraAssessment::factory()->for($business)->create([
        'assessment_status' => 'eligible',
        'recommended_advance_amount_minor' => 3000000,
        'recommended_recovery_rate_bps' => 1500,
        'maximum_repayment_days' => 90,
    ]);

    $this->post('/api/webhooks/ussd', ussdPayload('session-advance', USSD_OWNER_PHONE, ''));

    $response = $this->post('/api/webhooks/ussd', ussdPayload(
        'session-advance',
        USSD_OWNER_PHONE,
        '2',
    ));

    expect($response->getContent())
        ->toStartWith('CON Cash advance')
        ->toContain('Available: KES 30,000.00')
        ->toContain('Outstanding: KES 0.00')
        ->toContain('1. Borrow');
});

test('the available advance is reduced by existing exposure', function () {
    [$business] = ussdBusinessOwner();

    Advance::factory()->for($business)->create([
        'principal_amount_minor' => 800000,
        'fee_amount_minor' => 0,
        'total_repayable_minor' => 800000,
        'outstanding_balance_minor' => 800000,
        'status' => AdvanceStatus::Active->value,
    ]);

    EraAssessment::factory()->for($business)->create([
        'assessment_status' => 'eligible',
        'recommended_advance_amount_minor' => 1200000,
        'recommended_recovery_rate_bps' => 1500,
        'maximum_repayment_days' => 30,
    ]);

    $this->post('/api/webhooks/ussd', ussdPayload('session-exposure', USSD_OWNER_PHONE, ''));

    $response = $this->post('/api/webhooks/ussd', ussdPayload(
        'session-exposure',
        USSD_OWNER_PHONE,
        '2',
    ));

    expect($response->getContent())
        ->toContain('Available: KES 4,000.00')
        ->toContain('Outstanding: KES 8,000.00');
});

test('an owner can borrow and have the advance credited to the wallet', function () {
    [$business, $owner] = ussdBusinessOwner();

    $sms = new FakeSmsSender;
    $this->instance(SmsSender::class, $sms);

    $assessment = EraAssessment::factory()->for($business)->create([
        'assessment_status' => 'eligible',
        'recommended_advance_amount_minor' => 3000000,
        'recommended_recovery_rate_bps' => 1500,
        'maximum_repayment_days' => 90,
    ]);

    $this->post('/api/webhooks/ussd', ussdPayload('session-borrow', USSD_OWNER_PHONE, ''));
    $this->post('/api/webhooks/ussd', ussdPayload('session-borrow', USSD_OWNER_PHONE, '2'));
    $this->post('/api/webhooks/ussd', ussdPayload('session-borrow', USSD_OWNER_PHONE, '2*1'));

    $confirm = $this->post('/api/webhooks/ussd', ussdPayload(
        'session-borrow',
        USSD_OWNER_PHONE,
        '2*1*500',
    ));

    expect($confirm->getContent())
        ->toContain('Borrow KES 500.00?')
        ->toContain('Access fee: KES 10.00')
        ->toContain('You receive: KES 490.00')
        ->toContain('Repay KES 500.00 over 30 days')
        ->toContain('Recovery: 15%');

    $response = $this->post('/api/webhooks/ussd', ussdPayload(
        'session-borrow',
        USSD_OWNER_PHONE,
        '2*1*500*1',
    ));

    expect($response->getContent())->toStartWith('END Advance of KES 500.00 approved. KES 490.00 credited to your QCash wallet.');

    $offer = AdvanceOffer::query()->sole();
    $advance = Advance::query()->sole();
    $disbursement = Disbursement::query()->sole();

    expect($offer->status)->toBe(OfferStatus::Accepted)
        ->and($offer->assessment_id)->toBe($assessment->id)
        ->and($offer->offered_amount_minor)->toBe(49000)
        ->and($offer->fee_amount_minor)->toBe(1000)
        ->and($offer->total_repayable_minor)->toBe(50000)
        ->and($offer->maximum_repayment_days)->toBe(30)
        ->and($advance->status)->toBe(AdvanceStatus::Active)
        ->and($advance->principal_amount_minor)->toBe(49000)
        ->and($advance->fee_amount_minor)->toBe(1000)
        ->and($advance->total_repayable_minor)->toBe(50000)
        ->and($advance->maximum_repayment_days)->toBe(30)
        ->and($disbursement->status)->toBe(DisbursementStatus::Successful)
        ->and($disbursement->provider)->toBe('qcash_wallet')
        ->and($disbursement->amount_minor)->toBe(49000)
        ->and(app(BusinessWalletService::class)->balance($business))->toBe(49000);

    expect($sms->lastMessageFor($owner->phone_number))
        ->toBe('QCash: A cash advance of KES 490.00 has been credited to your QCash wallet. Access fee: KES 10.00. Repay: KES 500.00. Thank you for using QCash.')
        ->and($owner->notifications()->sole()->type)->toBe('advance_disbursed');
});

test('an owner without an eligible assessment cannot borrow', function () {
    ussdBusinessOwner();

    $this->post('/api/webhooks/ussd', ussdPayload('session-no-advance', USSD_OWNER_PHONE, ''));

    $response = $this->post('/api/webhooks/ussd', ussdPayload(
        'session-no-advance',
        USSD_OWNER_PHONE,
        '2',
    ));

    expect($response->getContent())->toContain('No eligible cash advance right now.');
});

test('an owner can withdraw to their mpesa number', function () {
    ussdB2cConfig();
    ussdFakeB2c();
    [$business] = ussdBusinessOwner();

    app(BusinessWalletService::class)->credit($business, 100000);

    $this->post('/api/webhooks/ussd', ussdPayload('session-withdraw', USSD_OWNER_PHONE, ''));
    $this->post('/api/webhooks/ussd', ussdPayload('session-withdraw', USSD_OWNER_PHONE, '3'));

    $confirm = $this->post('/api/webhooks/ussd', ussdPayload(
        'session-withdraw',
        USSD_OWNER_PHONE,
        '3*500',
    ));

    expect($confirm->getContent())->toContain('Withdraw KES 500.00 to your M-Pesa?');

    $response = $this->post('/api/webhooks/ussd', ussdPayload(
        'session-withdraw',
        USSD_OWNER_PHONE,
        '3*500*1',
    ));

    expect($response->getContent())->toStartWith('END Withdrawal of KES 500.00 requested.');

    $withdrawal = BusinessWithdrawal::query()->sole();

    expect($withdrawal->amount_minor)->toBe(50000)
        ->and($withdrawal->recipient_phone_number)->toBe(USSD_OWNER_PHONE)
        ->and(app(BusinessWalletService::class)->balance($business))->toBe(50000);

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/mpesa/b2c/v3/paymentrequest')
        && $request['Amount'] === 500
        && $request['PartyB'] === USSD_OWNER_PHONE);
});

test('a withdrawal above the wallet balance is rejected', function () {
    [$business] = ussdBusinessOwner();

    app(BusinessWalletService::class)->credit($business, 10000);

    $this->post('/api/webhooks/ussd', ussdPayload('session-low-balance', USSD_OWNER_PHONE, ''));
    $this->post('/api/webhooks/ussd', ussdPayload('session-low-balance', USSD_OWNER_PHONE, '3'));

    $response = $this->post('/api/webhooks/ussd', ussdPayload(
        'session-low-balance',
        USSD_OWNER_PHONE,
        '3*500',
    ));

    expect($response->getContent())->toContain('Enter a valid amount up to KES 100.00');
});

test('a non owner cannot use a business through ussd', function () {
    $business = Business::factory()->create();
    $staff = User::factory()->create(['phone_number' => USSD_OWNER_PHONE]);

    BusinessUser::factory()
        ->for($business)
        ->for($staff)
        ->staff()
        ->create(['status' => BusinessUserStatus::Active->value]);

    $response = $this->post('/api/webhooks/ussd', ussdPayload(
        'session-staff',
        USSD_OWNER_PHONE,
        '',
    ));

    expect($response->getContent())->toBe('END No active business is linked to this number.');
});

test('a configured service code rejects a different code', function () {
    config(['services.africastalking.ussd_service_code' => '*384*999#']);

    $response = $this->post('/api/webhooks/ussd', ussdPayload(
        'session-code',
        USSD_OWNER_PHONE,
        '',
    ));

    expect($response->getContent())->toBe('END This USSD service is not available.');
});

test('a continuation without a session is rejected', function () {
    $response = $this->post('/api/webhooks/ussd', ussdPayload(
        'session-expired',
        USSD_OWNER_PHONE,
        '1',
    ));

    expect($response->getContent())->toBe('END Session expired. Please dial again.');
});
