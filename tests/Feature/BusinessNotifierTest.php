<?php

use App\Models\Advance;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\BusinessWithdrawal;
use App\Models\Disbursement;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Notifications\BusinessNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;

uses(RefreshDatabase::class);

/**
 * @return array{0: Business, 1: User}
 */
function notifierBusinessOwner(): array
{
    $business = Business::factory()->create();
    $owner = User::factory()->create();

    BusinessUser::factory()->for($business)->for($owner)->create();

    return [$business, $owner];
}

test('a payment creates an in-app notification for the business owner', function () {
    [$business, $owner] = notifierBusinessOwner();

    $transaction = Transaction::factory()->for($business)->create([
        'amount_minor' => 15075,
        'currency' => 'KES',
        'provider_transaction_id' => 'RKTQDM7W6S',
        'counterparty_name' => 'John Doe Kamau',
        'transaction_time' => now(),
    ]);

    app(BusinessNotifier::class)->paymentReceived($business, $transaction);

    $notification = $owner->notifications()->sole();

    expect($notification->type)->toBe('payment_received')
        ->and($notification->data)->toMatchArray([
            'type' => 'payment_received',
            'title' => 'Payment received',
            'body' => 'A payment of KES 150.75 was received from John Doe Kamau.',
            'business_id' => $business->id,
            'business_public_id' => $business->public_id,
            'transaction_id' => $transaction->id,
            'amount_minor' => 15075,
            'currency' => 'KES',
            'reference' => 'RKTQDM7W6S',
        ]);
});

test('a completed withdrawal creates an in-app notification for the business owner', function () {
    [$business, $owner] = notifierBusinessOwner();

    $withdrawal = BusinessWithdrawal::factory()->for($business)->create([
        'amount_minor' => 3000,
        'currency' => 'KES',
        'completed_at' => now(),
    ]);

    app(BusinessNotifier::class)->withdrawalCompleted($business, $withdrawal, 'TESTWD001');

    $notification = $owner->notifications()->sole();

    expect($notification->type)->toBe('withdrawal_completed')
        ->and($notification->data)->toMatchArray([
            'type' => 'withdrawal_completed',
            'title' => 'Withdrawal sent',
            'body' => 'A withdrawal of KES 30.00 has been sent to your M-Pesa account. Ref: TESTWD001.',
            'business_id' => $business->id,
            'withdrawal_id' => $withdrawal->id,
            'amount_minor' => 3000,
            'currency' => 'KES',
            'reference' => 'TESTWD001',
        ]);
});

test('a disbursed advance creates an in-app notification for the business owner', function () {
    [$business, $owner] = notifierBusinessOwner();
    $advance = Advance::factory()->for($business)->create();
    $disbursement = Disbursement::factory()->for($advance)->create([
        'amount_minor' => 3000000,
        'completed_at' => now(),
    ]);

    app(BusinessNotifier::class)->advanceDisbursed($business, $disbursement, 'NLJ41HAY6Q');

    $notification = $owner->notifications()->sole();

    expect($notification->type)->toBe('advance_disbursed')
        ->and($notification->data)->toMatchArray([
            'type' => 'advance_disbursed',
            'title' => 'Advance disbursed',
            'body' => 'A cash advance of KES 30,000.00 has been credited to your QCash wallet. Ref: NLJ41HAY6Q.',
            'business_id' => $business->id,
            'advance_id' => $advance->id,
            'disbursement_id' => $disbursement->id,
            'amount_minor' => 3000000,
            'currency' => 'KES',
            'reference' => 'NLJ41HAY6Q',
        ]);
});

test('no notification is created when the business has no owner', function () {
    $business = Business::factory()->create();
    $transaction = Transaction::factory()->for($business)->create();

    app(BusinessNotifier::class)->paymentReceived($business, $transaction);

    expect(DatabaseNotification::query()->count())->toBe(0);
});
