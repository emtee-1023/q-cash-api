<?php

use App\Enums\TransactionDirection;
use App\Enums\WebhookProcessingStatus;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\ProviderWebhookEvent;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Markt\LaravelAuth\Contracts\SmsSender;
use Tests\Fixtures\FakeSmsSender;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function mpesaC2bPayload(Business $business, array $overrides = []): array
{
    return array_merge([
        'TransactionType' => 'Pay Bill',
        'TransID' => 'RKTQDM7W6S',
        'TransTime' => '20260115123045',
        'TransAmount' => '150.75',
        'BusinessShortCode' => '174379',
        'BillRefNumber' => $business->collection_account_number,
        'InvoiceNumber' => '',
        'OrgAccountBalance' => '10000.00',
        'ThirdPartyTransID' => '',
        'MSISDN' => '254708374149',
        'FirstName' => 'John',
        'MiddleName' => 'Doe',
        'LastName' => 'Kamau',
    ], $overrides);
}

function mpesaBusinessOwner(Business $business, string $phoneNumber = '254712345678'): User
{
    $owner = User::factory()->create(['phone_number' => $phoneNumber]);

    BusinessUser::factory()->for($business)->for($owner)->create();

    return $owner;
}

test('a c2b confirmation creates an inflow transaction for the matching business', function () {
    $business = Business::factory()->create();
    $payload = mpesaC2bPayload($business);

    $this->postJson('/api/webhooks/confirmation', $payload)
        ->assertOk()
        ->assertExactJson(['ResultCode' => '0', 'ResultDesc' => 'Success']);

    $transaction = Transaction::query()->sole();

    expect($transaction->business_id)->toBe($business->id)
        ->and($transaction->provider)->toBe('mpesa')
        ->and($transaction->provider_transaction_id)->toBe('RKTQDM7W6S')
        ->and($transaction->transaction_type)->toBe('pay_bill')
        ->and($transaction->direction)->toBe(TransactionDirection::Inflow)
        ->and($transaction->amount_minor)->toBe(15075)
        ->and($transaction->currency)->toBe('KES')
        ->and($transaction->provider_status)->toBe('completed')
        ->and($transaction->counterparty_reference)->toBe('254708374149')
        ->and($transaction->counterparty_name)->toBe('John Doe Kamau')
        ->and($transaction->raw_payload)->toBe($payload)
        ->and($transaction->transaction_time->toDateTimeString())->toBe('2026-01-15 09:30:45');

    $event = ProviderWebhookEvent::query()->sole();

    expect($event->provider)->toBe('mpesa')
        ->and($event->external_event_id)->toBe('RKTQDM7W6S')
        ->and($event->event_type)->toBe('c2b_confirmation')
        ->and($event->payload)->toBe($payload)
        ->and($event->processing_status)->toBe(WebhookProcessingStatus::Processed)
        ->and($event->processed_at)->not->toBeNull();
});

test('a duplicate c2b confirmation is only ingested and notified once', function () {
    $business = Business::factory()->create();
    $owner = mpesaBusinessOwner($business);
    $sms = new FakeSmsSender;

    $this->instance(SmsSender::class, $sms);

    $payload = mpesaC2bPayload($business);

    $this->postJson('/api/webhooks/confirmation', $payload)->assertOk();
    $this->postJson('/api/webhooks/confirmation', $payload)->assertOk();

    expect(Transaction::query()->count())->toBe(1)
        ->and(ProviderWebhookEvent::query()->count())->toBe(1)
        ->and($sms->messages[$owner->phone_number] ?? [])->toHaveCount(1);
});

test('a successful c2b confirmation sends an mpesa confirmation sms to the business owner', function () {
    $business = Business::factory()->create();
    $owner = mpesaBusinessOwner($business, '254712345678');
    $sms = new FakeSmsSender;

    $this->instance(SmsSender::class, $sms);

    $this->postJson('/api/webhooks/confirmation', mpesaC2bPayload($business))->assertOk();

    expect($sms->lastMessageFor($owner->phone_number))
        ->toBe('RKTQDM7W6S Confirmed. Ksh150.75 received from John Doe Kamau xxx on 15/01/26 at 12:30 PM.');
});

test('the confirmation sms masks a hashed msisdn from the sender description', function () {
    $business = Business::factory()->create();
    $owner = mpesaBusinessOwner($business);
    $sms = new FakeSmsSender;

    $this->instance(SmsSender::class, $sms);

    $hashedMsisdn = hash('sha256', '254790000001');

    $payload = mpesaC2bPayload($business, [
        'MSISDN' => $hashedMsisdn,
    ]);

    $this->postJson('/api/webhooks/confirmation', $payload)->assertOk();

    $message = $sms->lastMessageFor($owner->phone_number);

    expect($message)
        ->not->toContain($hashedMsisdn)
        ->and($message)
        ->toBe('RKTQDM7W6S Confirmed. Ksh150.75 received from John Doe Kamau xxx on 15/01/26 at 12:30 PM.');
});

test('a confirmation without a provider transaction id is recorded as failed', function () {
    $business = Business::factory()->create();
    $payload = mpesaC2bPayload($business, ['TransID' => '']);

    $this->postJson('/api/webhooks/confirmation', $payload)
        ->assertOk()
        ->assertExactJson(['ResultCode' => '0', 'ResultDesc' => 'Success']);

    expect(Transaction::query()->count())->toBe(0);

    $event = ProviderWebhookEvent::query()->sole();

    expect($event->processing_status)->toBe(WebhookProcessingStatus::Failed)
        ->and($event->processing_error)->toContain('TransID');
});

test('an unmatched collection account is recorded without creating a transaction', function () {
    $business = Business::factory()->create();
    $payload = mpesaC2bPayload($business, [
        'TransID' => 'UNKNOWN1',
        'BillRefNumber' => '000000',
    ]);

    $this->postJson('/api/webhooks/confirmation', $payload)
        ->assertOk()
        ->assertExactJson(['ResultCode' => '0', 'ResultDesc' => 'Success']);

    expect(Transaction::query()->count())->toBe(0);

    $event = ProviderWebhookEvent::query()->sole();

    expect($event->processing_status)->toBe(WebhookProcessingStatus::Failed)
        ->and($event->processing_error)->toContain('000000');
});

test('c2b validation accepts a known collection account when validation is enabled', function () {
    config(['services.mpesa.validation_enabled' => true]);

    $payload = mpesaC2bPayload(Business::factory()->create());

    $this->postJson('/api/webhooks/validation', $payload)
        ->assertOk()
        ->assertExactJson(['ResultCode' => '0', 'ResultDesc' => 'Accepted']);
});

test('c2b validation accepts any collection account when validation is disabled', function () {
    config(['services.mpesa.validation_enabled' => false]);

    $payload = mpesaC2bPayload(Business::factory()->create(), [
        'BillRefNumber' => '000000',
        'BusinessShortCode' => '999999',
    ]);

    $this->postJson('/api/webhooks/validation', $payload)
        ->assertOk()
        ->assertExactJson(['ResultCode' => '0', 'ResultDesc' => 'Accepted']);
});

test('c2b validation rejects an unknown collection account when validation is enabled', function () {
    config(['services.mpesa.validation_enabled' => true]);

    $payload = mpesaC2bPayload(Business::factory()->create(), [
        'BillRefNumber' => '000000',
    ]);

    $this->postJson('/api/webhooks/validation', $payload)
        ->assertOk()
        ->assertExactJson(['ResultCode' => 'C2B00012', 'ResultDesc' => 'Invalid account number']);
});

test('c2b validation rejects a payment for another shortcode when validation is enabled', function () {
    config([
        'services.mpesa.shortcode' => '174379',
        'services.mpesa.validation_enabled' => true,
    ]);

    $payload = mpesaC2bPayload(Business::factory()->create(), [
        'BusinessShortCode' => '999999',
    ]);

    $this->postJson('/api/webhooks/validation', $payload)
        ->assertOk()
        ->assertExactJson(['ResultCode' => 'C2B00012', 'ResultDesc' => 'Invalid account number']);
});
