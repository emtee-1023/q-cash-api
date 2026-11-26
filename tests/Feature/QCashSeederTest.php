<?php

use App\Models\Business;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\BusinessTypeSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\QCashSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        LocationSeeder::class,
        BusinessTypeSeeder::class,
        AdminUserSeeder::class,
        QCashSeeder::class,
    ]);
});

test('the seeder creates an activated admin user', function () {
    $admin = User::query()->where('is_admin', true)->sole();

    expect($admin->phone_number)->toBe('254790000000')
        ->and($admin->admin_activated_at)->not->toBeNull()
        ->and($admin->email)->toBe('admin@qcash.test');
});

test('every business has between 60 and 120 days of transaction history', function () {
    foreach (Business::query()->with('transactions')->get() as $business) {
        $first = $business->transactions->min('transaction_time')->copy()->startOfDay();
        $last = $business->transactions->max('transaction_time')->copy()->startOfDay();
        $span = (int) $first->diffInDays($last) + 1;

        expect($span)->toBeGreaterThanOrEqual(60, "{$business->business_name} has too little history.")
            ->and($span)->toBeLessThanOrEqual(120, "{$business->business_name} has too much history.");
    }
});

test('each business wallet has kes 500 more than its outstanding balance', function () {
    foreach (Business::query()->with(['advances', 'wallet'])->get() as $business) {
        $outstanding = (int) $business->advances->sum('outstanding_balance_minor');

        expect($business->wallet?->balance_minor)->toBe($outstanding + 50000, "{$business->business_name} wallet is not outstanding + KES 500.");
    }
});

test('each full trading month earns between 7600 and 14000', function () {
    foreach (Business::query()->with(['transactions.currentClassification'])->get() as $business) {
        $transactions = $business->transactions->sortBy('transaction_time')->values();
        $first = $transactions->first()->transaction_time->copy()->startOfDay();
        $last = $transactions->last()->transaction_time->copy()->startOfDay();
        $historyDays = (int) $first->diffInDays($last) + 1;
        $fullMonths = intdiv($historyDays, 30);

        $monthlyRevenue = [];

        foreach ($transactions as $transaction) {
            if (! $transaction->currentClassification?->revenue_eligible) {
                continue;
            }

            $day = $transaction->transaction_time->copy()->startOfDay();
            $month = (int) floor($first->diffInDays($day) / 30);

            $monthlyRevenue[$month] = ($monthlyRevenue[$month] ?? 0) + $transaction->amount_minor;
        }

        for ($month = 0; $month < $fullMonths; $month++) {
            $revenue = $monthlyRevenue[$month] ?? 0;

            expect($revenue)->toBeGreaterThanOrEqual(760000, "{$business->business_name} month {$month} earned below KES 7,600.")
                ->and($revenue)->toBeLessThanOrEqual(1400000, "{$business->business_name} month {$month} earned above KES 14,000.")
                ->and($revenue)->toBeLessThan(1500000);
        }
    }
});
