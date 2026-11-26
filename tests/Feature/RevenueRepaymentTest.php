<?php

use App\Enums\AdvanceStatus;
use App\Enums\CollectionMethod;
use App\Enums\CollectionStatus;
use App\Enums\TransactionCategory;
use App\Models\Advance;
use App\Models\Business;
use App\Models\RepaymentCollection;
use App\Models\Transaction;
use App\Services\Transactions\TransactionClassificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Markt\LaravelAuth\Contracts\SmsSender;
use Tests\Fixtures\FakeSmsSender;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->instance(SmsSender::class, new FakeSmsSender);
});

/**
 * @param  array<string, mixed>  $overrides
 */
function revenueAdvance(Business $business, array $overrides = []): Advance
{
    return Advance::factory()->for($business)->create(array_merge([
        'principal_amount_minor' => 1000000,
        'fee_amount_minor' => 30000,
        'total_repayable_minor' => 1030000,
        'amount_repaid_minor' => 0,
        'outstanding_balance_minor' => 1030000,
        'recovery_rate_bps' => 1500,
        'maximum_repayment_days' => 90,
        'status' => AdvanceStatus::Active->value,
    ], $overrides));
}

function classifyRevenue(
    Transaction $transaction,
    bool $eligible = true,
    string $version = 'txn-rules-v1',
): void {
    app(TransactionClassificationService::class)->classify(
        $transaction,
        $version,
        TransactionCategory::Sale,
        $eligible,
    );
}

function revenueReceipt(Business $business, int $amountMinor, string $time): Transaction
{
    return Transaction::factory()->for($business)->create([
        'direction' => 'inflow',
        'amount_minor' => $amountMinor,
        'transaction_time' => $time,
    ]);
}

test('the illustrative thirty day collection sequence matches the policy', function () {
    $business = Business::factory()->create();
    $advance = revenueAdvance($business);

    $receipts = [
        ['2026-01-04 10:00:00', 800000],
        ['2026-01-09 10:00:00', 350000],
        ['2026-01-16 10:00:00', 1200000],
        ['2026-01-23 10:00:00', 600000],
        ['2026-01-29 10:00:00', 900000],
    ];

    foreach ($receipts as [$time, $amount]) {
        classifyRevenue(revenueReceipt($business, $amount, $time));
    }

    $advance->refresh();

    expect(RepaymentCollection::query()->orderBy('collected_at')->pluck('amount_minor')->all())
        ->toBe([120000, 52500, 180000, 90000, 135000])
        ->and($advance->amount_repaid_minor)->toBe(577500)
        ->and($advance->fee_repaid_minor)->toBe(30000)
        ->and($advance->principal_repaid_minor)->toBe(547500)
        ->and($advance->outstanding_balance_minor)->toBe(452500)
        ->and($advance->status)->toBe(AdvanceStatus::Active);
});

test('a revenue deduction is allocated to the fee before the principal', function () {
    $business = Business::factory()->create();
    $advance = revenueAdvance($business);

    classifyRevenue(revenueReceipt($business, 100000, '2026-01-04 10:00:00'));

    $collection = RepaymentCollection::query()->sole();
    $allocation = $collection->allocations()->sole();

    expect($collection->collection_method)->toBe(CollectionMethod::RevenueDeduction)
        ->and($collection->status)->toBe(CollectionStatus::Successful)
        ->and($collection->amount_minor)->toBe(15000)
        ->and($allocation->fee_amount_minor)->toBe(15000)
        ->and($allocation->principal_amount_minor)->toBe(0);

    $advance->refresh();

    expect($advance->fee_repaid_minor)->toBe(15000)
        ->and($advance->principal_repaid_minor)->toBe(0)
        ->and($advance->outstanding_balance_minor)->toBe(1015000);
});

test('an ineligible receipt is not deducted', function () {
    $business = Business::factory()->create();
    $advance = revenueAdvance($business);

    classifyRevenue(
        revenueReceipt($business, 800000, '2026-01-04 10:00:00'),
        eligible: false,
    );

    expect(RepaymentCollection::query()->count())->toBe(0)
        ->and($advance->refresh()->outstanding_balance_minor)->toBe(1030000);
});

test('a receipt is not deducted when there is no outstanding advance', function () {
    $business = Business::factory()->create();

    classifyRevenue(revenueReceipt($business, 800000, '2026-01-04 10:00:00'));

    expect(RepaymentCollection::query()->count())->toBe(0);
});

test('the daily deduction cap limits collections on the same day', function () {
    config(['repayment.daily_cap_minor' => 400000]);

    $business = Business::factory()->create();
    $advance = revenueAdvance($business, [
        'principal_amount_minor' => 5000000,
        'fee_amount_minor' => 0,
        'total_repayable_minor' => 5000000,
        'outstanding_balance_minor' => 5000000,
    ]);

    classifyRevenue(revenueReceipt($business, 800000, '2026-01-04 09:00:00'));
    classifyRevenue(revenueReceipt($business, 4000000, '2026-01-04 12:00:00'));
    classifyRevenue(revenueReceipt($business, 1000000, '2026-01-04 15:00:00'));

    $collections = RepaymentCollection::query()->orderBy('collected_at')->get();

    expect($collections)->toHaveCount(2)
        ->and($collections->pluck('amount_minor')->all())->toBe([120000, 280000])
        ->and((int) $collections->sum('amount_minor'))->toBe(400000)
        ->and($advance->refresh()->amount_repaid_minor)->toBe(400000);
});

test('the working capital floor caps the deduction rate', function () {
    config(['repayment.working_capital_floor_bps' => 8500]);

    $business = Business::factory()->create();
    $advance = revenueAdvance($business, [
        'recovery_rate_bps' => 2000,
    ]);

    classifyRevenue(revenueReceipt($business, 100000, '2026-01-04 10:00:00'));

    expect(RepaymentCollection::query()->sole()->amount_minor)->toBe(15000)
        ->and($advance->refresh()->outstanding_balance_minor)->toBe(1015000);
});

test('a deduction never exceeds the outstanding balance', function () {
    $business = Business::factory()->create();
    $advance = revenueAdvance($business, [
        'principal_amount_minor' => 50000,
        'fee_amount_minor' => 0,
        'total_repayable_minor' => 50000,
        'outstanding_balance_minor' => 50000,
    ]);

    classifyRevenue(revenueReceipt($business, 800000, '2026-01-04 10:00:00'));

    $advance->refresh();

    expect(RepaymentCollection::query()->sole()->amount_minor)->toBe(50000)
        ->and($advance->outstanding_balance_minor)->toBe(0)
        ->and($advance->status)->toBe(AdvanceStatus::Settled);
});

test('a deduction is idempotent when a transaction is reclassified', function () {
    $business = Business::factory()->create();
    $advance = revenueAdvance($business);

    $transaction = revenueReceipt($business, 800000, '2026-01-04 10:00:00');

    classifyRevenue($transaction);
    classifyRevenue($transaction, version: 'txn-rules-v2');

    expect(RepaymentCollection::query()->count())->toBe(1)
        ->and($advance->refresh()->amount_repaid_minor)->toBe(120000);
});

test('a receipt already applied to a repayment is not deducted again', function () {
    $business = Business::factory()->create();
    $advance = revenueAdvance($business);

    $transaction = revenueReceipt($business, 800000, '2026-01-04 10:00:00');

    RepaymentCollection::factory()->for($business)->create([
        'source_transaction_id' => $transaction->id,
        'collection_method' => CollectionMethod::ManualPayment->value,
        'amount_minor' => 1000,
        'status' => CollectionStatus::Successful->value,
    ]);

    classifyRevenue($transaction);

    expect(RepaymentCollection::query()->count())->toBe(1)
        ->and($advance->refresh()->outstanding_balance_minor)->toBe(1030000);
});

test('deductions apply to the overdue advance before a current advance', function () {
    $business = Business::factory()->create();

    $overdue = revenueAdvance($business, [
        'status' => AdvanceStatus::Overdue->value,
        'due_at' => now()->subDays(10),
    ]);

    $current = revenueAdvance($business, [
        'due_at' => now()->addDays(10),
    ]);

    classifyRevenue(revenueReceipt($business, 800000, '2026-01-04 10:00:00'));

    $collection = RepaymentCollection::query()->sole();

    expect($collection->amount_minor)->toBe(120000)
        ->and($collection->allocations()->sole()->advance_id)->toBe($overdue->id)
        ->and($overdue->refresh()->amount_repaid_minor)->toBe(120000)
        ->and($current->refresh()->amount_repaid_minor)->toBe(0);
});
