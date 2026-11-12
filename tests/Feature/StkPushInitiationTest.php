<?php

use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\StkPushRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function stkPushConfig(): void
{
    config([
        'services.mpesa.shortcode' => '174379',
        'services.mpesa.passkey' => 'test-passkey',
        'services.mpesa.stk_callback_url' => 'https://example.test/api/webhooks/stk',
        'services.mpesa.environment' => 'sandbox',
        'services.mpesa.base_url' => null,
    ]);
}

/**
 * @return array{0: Business, 1: User}
 */
function stkPushBusinessOwner(): array
{
    $business = Business::factory()->create();
    $owner = User::factory()->create();

    BusinessUser::factory()->for($business)->for($owner)->create();

    return [$business, $owner];
}

test('an owner can initiate an stk push for a business', function () {
    stkPushConfig();
    [$business, $owner] = stkPushBusinessOwner();

    Http::preventStrayRequests();

    Http::fake([
        'https://sandbox.safaricom.co.ke/oauth/v1/generate*' => Http::response([
            'access_token' => 'test-token',
            'expires_in' => '3599',
        ]),
        'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest' => Http::response([
            'MerchantRequestID' => '29115-34620561-1',
            'CheckoutRequestID' => 'ws_CO_191220191020363925',
            'ResponseCode' => '0',
            'ResponseDescription' => 'Success. Request accepted for processing',
            'CustomerMessage' => 'Success. Request accepted for processing',
        ]),
    ]);

    $this->travelTo('2026-01-15 09:30:45');

    Sanctum::actingAs($owner);

    $this->postJson("/api/businesses/{$business->id}/stk-push", [
        'phone_number' => '0712345678',
        'amount_minor' => 1000,
    ])->assertCreated()
        ->assertJsonPath('data.checkout_request_id', 'ws_CO_191220191020363925')
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.amount_minor', 1000);

    $stkPush = StkPushRequest::query()->sole();

    expect($stkPush->business_id)->toBe($business->id)
        ->and($stkPush->checkout_request_id)->toBe('ws_CO_191220191020363925')
        ->and($stkPush->phone_number)->toBe('254712345678')
        ->and($stkPush->amount_minor)->toBe(1000)
        ->and($stkPush->account_reference)->toBe($business->collection_account_number);

    Http::assertSent(function (Request $request) use ($business): bool {
        if (! str_ends_with($request->url(), '/mpesa/stkpush/v1/processrequest')) {
            return false;
        }

        return $request['BusinessShortCode'] === '174379'
            && $request['Timestamp'] === '20260115123045'
            && $request['Password'] === base64_encode('174379test-passkey20260115123045')
            && $request['TransactionType'] === 'CustomerPayBillOnline'
            && $request['Amount'] === '10.00'
            && $request['PartyA'] === '254712345678'
            && $request['PartyB'] === '174379'
            && $request['PhoneNumber'] === '254712345678'
            && $request['CallBackURL'] === 'https://example.test/api/webhooks/stk'
            && $request['AccountReference'] === $business->collection_account_number;
    });
});

test('a non owner cannot initiate an stk push', function () {
    stkPushConfig();

    $business = Business::factory()->create();
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $this->postJson("/api/businesses/{$business->id}/stk-push", [
        'phone_number' => '0712345678',
        'amount_minor' => 1000,
    ])->assertForbidden();

    expect(StkPushRequest::query()->count())->toBe(0);
});

test('amount is required to initiate an stk push', function () {
    stkPushConfig();
    [$business, $owner] = stkPushBusinessOwner();

    Sanctum::actingAs($owner);

    $this->postJson("/api/businesses/{$business->id}/stk-push", [
        'phone_number' => '0712345678',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('amount_minor');

    expect(StkPushRequest::query()->count())->toBe(0);
});
