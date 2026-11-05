<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('all q-cash tables exist', function () {
    $tables = [
        'users',
        'businesses',
        'business_users',
        'payment_accounts',
        'transactions',
        'transaction_classifications',
        'model_versions',
        'decision_policies',
        'era_assessments',
        'financing_partners',
        'advance_offers',
        'advances',
        'disbursements',
        'repayment_collections',
        'repayment_allocations',
        'advance_ledger_entries',
        'advance_repayment_terms',
        'provider_webhook_events',
    ];

    foreach ($tables as $table) {
        expect(Schema::hasTable($table))->toBeTrue("Missing table: {$table}");
    }
});

test('monetary columns are present on the key financial tables', function () {
    expect(Schema::hasColumns('transactions', ['amount_minor', 'currency']))->toBeTrue();
    expect(Schema::hasColumns('advance_offers', ['offered_amount_minor', 'fee_amount_minor', 'total_repayable_minor']))->toBeTrue();
    expect(Schema::hasColumns('advances', ['principal_amount_minor', 'outstanding_balance_minor']))->toBeTrue();
    expect(Schema::hasColumns('repayment_collections', ['amount_minor', 'idempotency_key']))->toBeTrue();
    expect(Schema::hasColumns('repayment_allocations', ['amount_minor']))->toBeTrue();
    expect(Schema::hasColumns('advance_ledger_entries', ['amount_minor', 'entry_type']))->toBeTrue();
});

test('foreign keys are defined on the core relationships', function () {
    $expectations = [
        'business_users' => ['business_id', 'user_id'],
        'payment_accounts' => ['business_id'],
        'transactions' => ['business_id', 'payment_account_id'],
        'transaction_classifications' => ['transaction_id'],
        'era_assessments' => ['business_id'],
        'advance_offers' => ['assessment_id', 'business_id'],
        'advances' => ['offer_id', 'business_id', 'financing_partner_id'],
        'disbursements' => ['advance_id'],
        'repayment_collections' => ['business_id', 'source_transaction_id'],
        'repayment_allocations' => ['repayment_collection_id', 'advance_id'],
        'advance_ledger_entries' => ['advance_id'],
        'advance_repayment_terms' => ['advance_id', 'changed_by_user_id'],
    ];

    foreach ($expectations as $table => $columns) {
        $foreignColumns = collect(Schema::getForeignKeys($table))->flatMap(fn ($fk) => $fk['columns'])->all();

        foreach ($columns as $column) {
            expect($foreignColumns)->toContain($column);
        }
    }
});

test('advance ledger entries have no updated_at column', function () {
    expect(Schema::hasColumn('advance_ledger_entries', 'created_at'))->toBeTrue();
    expect(Schema::hasColumn('advance_ledger_entries', 'updated_at'))->toBeFalse();
});
