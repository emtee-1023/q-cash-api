<?php

use App\Enums\AdvanceStatus;
use App\Enums\DisbursementStatus;
use App\Enums\LedgerEntryType;
use App\Models\Advance;
use App\Models\Business;
use App\Services\Advances\AdvanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function disburseableAdvance(Business $business): Advance
{
    return Advance::factory()->create([
        'business_id' => $business->id,
        'principal_amount_minor' => 3000000,
        'fee_amount_minor' => 0,
        'total_repayable_minor' => 3000000,
        'outstanding_balance_minor' => 3000000,
        'maximum_repayment_days' => 30,
        'status' => 'approved',
    ]);
}

test('requesting a disbursement transitions the advance to disbursing', function () {
    $business = Business::factory()->create();
    $advance = disburseableAdvance($business);

    $disbursement = app(AdvanceService::class)->requestDisbursement($advance, 'Safaricom');

    expect($disbursement->status)->toBe(DisbursementStatus::Processing);
    expect($disbursement->amount_minor)->toBe(3000000);

    $advance->refresh();
    expect($advance->status)->toBe(AdvanceStatus::Disbursing);
});

test('a successful disbursement activates the advance and writes ledger entries', function () {
    $business = Business::factory()->create();
    $advance = disburseableAdvance($business);
    $service = app(AdvanceService::class);

    $disbursement = $service->requestDisbursement($advance, 'Safaricom');
    $service->markDisbursementSuccessful($disbursement, 'REF-1', ['ok' => true]);

    $advance->refresh();
    expect($advance->status)->toBe(AdvanceStatus::Active);
    expect($advance->disbursed_at)->not->toBeNull();
    expect($advance->due_at->toDateString())->toBe(now()->addDays(30)->toDateString());

    expect($advance->ledgerEntries()->where('entry_type', LedgerEntryType::PrincipalDisbursed->value)->count())->toBe(1);
});

test('a successful disbursement records the fee when present', function () {
    $business = Business::factory()->create();
    $advance = Advance::factory()->create([
        'business_id' => $business->id,
        'principal_amount_minor' => 3000000,
        'fee_amount_minor' => 150000,
        'total_repayable_minor' => 3150000,
        'outstanding_balance_minor' => 3150000,
        'maximum_repayment_days' => 30,
        'status' => 'approved',
    ]);
    $service = app(AdvanceService::class);

    $disbursement = $service->requestDisbursement($advance, 'Safaricom');
    $service->markDisbursementSuccessful($disbursement, 'REF-2');

    $advance->refresh();
    expect($advance->ledgerEntries()->where('entry_type', LedgerEntryType::FeeAssessed->value)->count())->toBe(1);
});

test('a failed disbursement returns the advance to pending', function () {
    $business = Business::factory()->create();
    $advance = disburseableAdvance($business);
    $service = app(AdvanceService::class);

    $disbursement = $service->requestDisbursement($advance, 'Safaricom');
    $service->markDisbursementFailed($disbursement, 'Insufficient funds');

    expect($disbursement->refresh()->status)->toBe(DisbursementStatus::Failed);
    expect($disbursement->failure_reason)->toBe('Insufficient funds');
    expect($advance->refresh()->status)->toBe(AdvanceStatus::Pending);
});
