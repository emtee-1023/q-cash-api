<?php

use App\Enums\LedgerEntryType;
use App\Models\Advance;
use App\Models\Business;
use App\Services\Ledger\AdvanceLedger;
use App\Services\Repayments\RepaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the ledger uses the documented sign convention', function () {
    $business = Business::factory()->create();
    $advance = Advance::factory()->create([
        'business_id' => $business->id,
        'principal_amount_minor' => 3000000,
        'total_repayable_minor' => 3000000,
        'outstanding_balance_minor' => 3000000,
        'status' => 'active',
    ]);

    app(RepaymentService::class)->recordSuccessfulCollection([
        'business_id' => $business->id,
        'collection_method' => 'revenue_deduction',
        'amount_minor' => 1000000,
        'currency' => 'KES',
        'idempotency_key' => 'ik-ledger',
    ], [
        ['advance_id' => $advance->id, 'amount_minor' => 1000000],
    ]);

    $repaymentEntry = $advance->ledgerEntries()->where('entry_type', LedgerEntryType::Repayment->value)->first();

    expect($repaymentEntry->amount_minor)->toBe(-1000000);
});

test('the ledger balance reconciles with repayments', function () {
    $business = Business::factory()->create();
    $advance = Advance::factory()->create([
        'business_id' => $business->id,
        'principal_amount_minor' => 3000000,
        'total_repayable_minor' => 3000000,
        'outstanding_balance_minor' => 3000000,
        'status' => 'active',
    ]);

    $ledger = app(AdvanceLedger::class);
    $ledger->record($advance, LedgerEntryType::PrincipalDisbursed, 3000000);
    $ledger->record($advance, LedgerEntryType::Repayment, -1000000);

    $balance = $advance->ledgerEntries()->sum('amount_minor');

    expect($balance)->toBe(2000000);
});

test('a reversal writes a positive entry', function () {
    $business = Business::factory()->create();
    $advance = Advance::factory()->create([
        'business_id' => $business->id,
        'principal_amount_minor' => 3000000,
        'total_repayable_minor' => 3000000,
        'outstanding_balance_minor' => 3000000,
        'status' => 'active',
    ]);

    $collection = app(RepaymentService::class)->recordSuccessfulCollection([
        'business_id' => $business->id,
        'collection_method' => 'revenue_deduction',
        'amount_minor' => 1000000,
        'currency' => 'KES',
        'idempotency_key' => 'ik-rev',
    ], [
        ['advance_id' => $advance->id, 'amount_minor' => 1000000],
    ]);

    app(RepaymentService::class)->reverse($collection);

    $reversalEntry = $advance->ledgerEntries()->where('entry_type', LedgerEntryType::Reversal->value)->first();

    expect($reversalEntry->amount_minor)->toBe(1000000);
});
