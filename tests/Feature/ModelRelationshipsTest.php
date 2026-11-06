<?php

use App\Models\Advance;
use App\Models\AdvanceOffer;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\EraAssessment;
use App\Models\PaymentAccount;
use App\Models\RepaymentAllocation;
use App\Models\RepaymentCollection;
use App\Models\Transaction;
use App\Models\TransactionClassification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a user has businesses through business_users', function () {
    $user = User::factory()->create();
    $business = Business::factory()->create();

    $business->businessUsers()->create([
        'user_id' => $user->id,
        'role' => 'owner',
        'is_primary_contact' => true,
        'status' => 'active',
    ]);

    expect($user->businesses()->count())->toBe(1);
    expect($user->businesses()->first()->id)->toBe($business->id);
});

test('a business belongs to many users', function () {
    $business = Business::factory()->create();
    $owner = User::factory()->create();
    $staff = User::factory()->create();

    $business->users()->attach($owner->id, ['role' => 'owner', 'is_primary_contact' => true, 'status' => 'active']);
    $business->users()->attach($staff->id, ['role' => 'staff', 'is_primary_contact' => false, 'status' => 'active']);

    expect($business->users()->count())->toBe(2);
});

test('business users pivot carries role and primary-contact flags', function () {
    $business = Business::factory()->create();
    $owner = User::factory()->create();

    $business->businessUsers()->create([
        'user_id' => $owner->id,
        'role' => 'owner',
        'is_primary_contact' => true,
        'status' => 'active',
    ]);

    $membership = BusinessUser::query()->first();

    expect($membership->role->value)->toBe('owner');
    expect($membership->is_primary_contact)->toBeTrue();
});

test('a business has payment accounts and transactions', function () {
    $business = Business::factory()->create();
    $account = PaymentAccount::factory()->create(['business_id' => $business->id]);
    $transaction = Transaction::factory()->create([
        'business_id' => $business->id,
        'payment_account_id' => $account->id,
    ]);

    expect($business->paymentAccounts()->count())->toBe(1);
    expect($business->transactions()->count())->toBe(1);
    expect($transaction->paymentAccount->id)->toBe($account->id);
});

test('a transaction has historical and current classifications', function () {
    $transaction = Transaction::factory()->create();
    $classification = TransactionClassification::factory()->create([
        'transaction_id' => $transaction->id,
        'is_current' => true,
    ]);

    expect($transaction->currentClassification->id)->toBe($classification->id);
});

test('an assessment belongs to a business and can have an offer', function () {
    $business = Business::factory()->create();
    $assessment = EraAssessment::factory()->create(['business_id' => $business->id]);
    $offer = AdvanceOffer::factory()->create([
        'assessment_id' => $assessment->id,
        'business_id' => $business->id,
    ]);

    expect($assessment->business->id)->toBe($business->id);
    expect($assessment->offer->id)->toBe($offer->id);
});

test('an advance chains offer, disbursement, ledger and repayment terms', function () {
    $business = Business::factory()->create();
    $assessment = EraAssessment::factory()->create(['business_id' => $business->id]);
    $offer = AdvanceOffer::factory()->create([
        'assessment_id' => $assessment->id,
        'business_id' => $business->id,
    ]);
    $advance = Advance::factory()->create([
        'offer_id' => $offer->id,
        'business_id' => $business->id,
    ]);

    $advance->repaymentTerms()->create([
        'recovery_rate_bps' => 1000,
        'maximum_repayment_days' => 30,
        'effective_from' => now(),
    ]);

    expect($advance->offer->id)->toBe($offer->id);
    expect($advance->business->id)->toBe($business->id);
    expect($advance->repaymentTerms()->count())->toBe(1);
    expect($advance->currentRepaymentTerm)->not->toBeNull();
});

test('a repayment collection has allocations', function () {
    $business = Business::factory()->create();
    $assessment = EraAssessment::factory()->create(['business_id' => $business->id]);
    $offer = AdvanceOffer::factory()->create([
        'assessment_id' => $assessment->id,
        'business_id' => $business->id,
    ]);
    $advance = Advance::factory()->create([
        'offer_id' => $offer->id,
        'business_id' => $business->id,
    ]);
    $collection = RepaymentCollection::factory()->create(['business_id' => $business->id]);

    $allocation = RepaymentAllocation::factory()->create([
        'repayment_collection_id' => $collection->id,
        'advance_id' => $advance->id,
    ]);

    expect($collection->allocations()->count())->toBe(1);
    expect($allocation->advance->id)->toBe($advance->id);
});
