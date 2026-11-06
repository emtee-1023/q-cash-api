<?php

use App\Enums\AdvanceStatus;
use App\Enums\CollectionStatus;
use App\Models\Advance;
use App\Models\Business;
use App\Models\RepaymentCollection;
use App\Services\Repayments\RepaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function repayableAdvance(Business $business, int $outstanding = 3000000, string $status = 'active'): Advance
{
    return Advance::factory()->create([
        'business_id' => $business->id,
        'principal_amount_minor' => $outstanding,
        'total_repayable_minor' => $outstanding,
        'outstanding_balance_minor' => $outstanding,
        'amount_repaid_minor' => 0,
        'status' => $status,
    ]);
}

test('a partial repayment reduces the outstanding balance', function () {
    $business = Business::factory()->create();
    $advance = repayableAdvance($business);

    $collection = app(RepaymentService::class)->recordSuccessfulCollection([
        'business_id' => $business->id,
        'collection_method' => 'revenue_deduction',
        'amount_minor' => 1500000,
        'currency' => 'KES',
        'idempotency_key' => 'ik-partial',
    ], [
        ['advance_id' => $advance->id, 'amount_minor' => 1500000],
    ]);

    expect($collection->status)->toBe(CollectionStatus::Successful);

    $advance->refresh();
    expect($advance->amount_repaid_minor)->toBe(1500000);
    expect($advance->outstanding_balance_minor)->toBe(1500000);
    expect($advance->status)->toBe(AdvanceStatus::Active);
});

test('a full repayment settles the advance', function () {
    $business = Business::factory()->create();
    $advance = repayableAdvance($business, 3000000);

    app(RepaymentService::class)->recordSuccessfulCollection([
        'business_id' => $business->id,
        'collection_method' => 'revenue_deduction',
        'amount_minor' => 3000000,
        'currency' => 'KES',
        'idempotency_key' => 'ik-full',
    ], [
        ['advance_id' => $advance->id, 'amount_minor' => 3000000],
    ]);

    $advance->refresh();
    expect($advance->status)->toBe(AdvanceStatus::Settled);
    expect($advance->outstanding_balance_minor)->toBe(0);
    expect($advance->settled_at)->not->toBeNull();
});

test('one collection can be allocated across multiple advances', function () {
    $business = Business::factory()->create();
    $first = repayableAdvance($business, 2000000);
    $second = repayableAdvance($business, 2000000);

    app(RepaymentService::class)->recordSuccessfulCollection([
        'business_id' => $business->id,
        'collection_method' => 'revenue_deduction',
        'amount_minor' => 2000000,
        'currency' => 'KES',
        'idempotency_key' => 'ik-multi',
    ], [
        ['advance_id' => $first->id, 'amount_minor' => 1000000],
        ['advance_id' => $second->id, 'amount_minor' => 1000000],
    ]);

    expect($first->refresh()->amount_repaid_minor)->toBe(1000000);
    expect($second->refresh()->amount_repaid_minor)->toBe(1000000);
});

test('a duplicate callback is idempotent', function () {
    $business = Business::factory()->create();
    $advance = repayableAdvance($business);

    $service = app(RepaymentService::class);
    $data = [
        'business_id' => $business->id,
        'collection_method' => 'revenue_deduction',
        'amount_minor' => 1000000,
        'currency' => 'KES',
        'idempotency_key' => 'ik-dupe',
    ];
    $allocation = [['advance_id' => $advance->id, 'amount_minor' => 1000000]];

    $first = $service->recordSuccessfulCollection($data, $allocation);
    $second = $service->recordSuccessfulCollection($data, $allocation);

    expect($second->id)->toBe($first->id);
    expect(RepaymentCollection::query()->count())->toBe(1);
    expect($advance->refresh()->amount_repaid_minor)->toBe(1000000);
});

test('a failed collection does not reduce balances', function () {
    $business = Business::factory()->create();
    $advance = repayableAdvance($business);

    $collection = app(RepaymentService::class)->recordFailedCollection([
        'business_id' => $business->id,
        'collection_method' => 'revenue_deduction',
        'amount_minor' => 1000000,
        'currency' => 'KES',
        'idempotency_key' => 'ik-failed',
    ]);

    expect($collection->status)->toBe(CollectionStatus::Failed);
    expect($advance->refresh()->amount_repaid_minor)->toBe(0);
    expect($advance->outstanding_balance_minor)->toBe(3000000);
});

test('a reversal restores the advance balance', function () {
    $business = Business::factory()->create();
    $advance = repayableAdvance($business);

    $service = app(RepaymentService::class);
    $collection = $service->recordSuccessfulCollection([
        'business_id' => $business->id,
        'collection_method' => 'revenue_deduction',
        'amount_minor' => 1000000,
        'currency' => 'KES',
        'idempotency_key' => 'ik-reverse',
    ], [
        ['advance_id' => $advance->id, 'amount_minor' => 1000000],
    ]);

    $service->reverse($collection);

    $advance->refresh();
    expect($advance->amount_repaid_minor)->toBe(0);
    expect($advance->outstanding_balance_minor)->toBe(3000000);
    expect($collection->refresh()->status)->toBe(CollectionStatus::Reversed);
});

test('allocations must sum to the collected amount', function () {
    $business = Business::factory()->create();
    $advance = repayableAdvance($business);

    app(RepaymentService::class)->recordSuccessfulCollection([
        'business_id' => $business->id,
        'collection_method' => 'revenue_deduction',
        'amount_minor' => 1000000,
        'currency' => 'KES',
        'idempotency_key' => 'ik-mismatch',
    ], [
        ['advance_id' => $advance->id, 'amount_minor' => 500000],
    ]);
})->throws(InvalidArgumentException::class);

test('a repayment cannot be allocated to another business advance', function () {
    $business = Business::factory()->create();
    $otherBusiness = Business::factory()->create();
    $otherAdvance = repayableAdvance($otherBusiness);

    app(RepaymentService::class)->recordSuccessfulCollection([
        'business_id' => $business->id,
        'collection_method' => 'revenue_deduction',
        'amount_minor' => 1000000,
        'currency' => 'KES',
        'idempotency_key' => 'ik-cross',
    ], [
        ['advance_id' => $otherAdvance->id, 'amount_minor' => 1000000],
    ]);
})->throws(InvalidArgumentException::class);
