<?php

use App\Enums\TransactionDirection;
use App\Models\Advance;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\BusinessWallet;
use App\Models\EraAssessment;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * @return array{0: Business, 1: User}
 */
function analyticsBusinessOwner(): array
{
    $business = Business::factory()->create();
    $owner = User::factory()->create();

    BusinessUser::factory()->for($business)->for($owner)->create();

    return [$business, $owner];
}

test('a business member sees earnings, balances and the latest advance', function () {
    $this->travelTo('2026-06-15 12:00:00');

    [$business, $owner] = analyticsBusinessOwner();

    Transaction::factory()->for($business)->create([
        'direction' => TransactionDirection::Inflow->value,
        'amount_minor' => 12000,
        'transaction_time' => now(),
    ]);
    Transaction::factory()->for($business)->create([
        'direction' => TransactionDirection::Inflow->value,
        'amount_minor' => 3000,
        'transaction_time' => now(),
    ]);
    Transaction::factory()->for($business)->create([
        'direction' => TransactionDirection::Inflow->value,
        'amount_minor' => 7000,
        'transaction_time' => now()->subDay(),
    ]);
    Transaction::factory()->for($business)->create([
        'direction' => TransactionDirection::Inflow->value,
        'amount_minor' => 50000,
        'transaction_time' => now()->subMonth()->startOfMonth()->addDay(),
    ]);
    Transaction::factory()->for($business)->create([
        'direction' => TransactionDirection::Inflow->value,
        'amount_minor' => 9000,
        'transaction_time' => now()->subMonth()->endOfMonth(),
    ]);
    Transaction::factory()->for($business)->create([
        'direction' => TransactionDirection::Inflow->value,
        'amount_minor' => 99999,
        'transaction_time' => now()->subMonths(2),
    ]);
    Transaction::factory()->for($business)->create([
        'direction' => TransactionDirection::Outflow->value,
        'amount_minor' => 44444,
        'transaction_time' => now(),
    ]);
    Transaction::factory()->create([
        'direction' => TransactionDirection::Inflow->value,
        'amount_minor' => 88888,
        'transaction_time' => now(),
    ]);

    Advance::factory()->for($business)->create([
        'outstanding_balance_minor' => 4000,
        'created_at' => now()->subDays(2),
    ]);
    $latestAdvance = Advance::factory()->for($business)->create([
        'outstanding_balance_minor' => 6000,
        'created_at' => now(),
    ]);
    Advance::factory()->create([
        'outstanding_balance_minor' => 77777,
        'created_at' => now(),
    ]);

    BusinessWallet::factory()->for($business)->create(['balance_minor' => 2500]);

    EraAssessment::factory()->for($business)->create([
        'recommended_advance_amount_minor' => 1000000,
        'assessment_date' => now()->subDays(14),
    ]);
    EraAssessment::factory()->for($business)->create([
        'recommended_advance_amount_minor' => 3000000,
        'assessment_date' => now()->subDay(),
    ]);

    Sanctum::actingAs($owner);

    $this->getJson("/api/businesses/{$business->id}/analytics")
        ->assertOk()
        ->assertJsonPath('data.today_earnings_minor', 15000)
        ->assertJsonPath('data.last_month_earnings_minor', 59000)
        ->assertJsonPath('data.outstanding_balance_minor', 10000)
        ->assertJsonPath('data.latest_advance.id', $latestAdvance->id)
        ->assertJsonPath('data.available_balance_minor', 2500)
        ->assertJsonPath('data.available_advance_minor', 3000000);
});

test('a business without analytics data returns zero totals and no latest advance', function () {
    [$business, $owner] = analyticsBusinessOwner();

    Sanctum::actingAs($owner);

    $this->getJson("/api/businesses/{$business->id}/analytics")
        ->assertOk()
        ->assertJsonPath('data.today_earnings_minor', 0)
        ->assertJsonPath('data.last_month_earnings_minor', 0)
        ->assertJsonPath('data.outstanding_balance_minor', 0)
        ->assertJsonPath('data.latest_advance', null)
        ->assertJsonPath('data.available_balance_minor', 0)
        ->assertJsonPath('data.available_advance_minor', 0);
});

test('a non member cannot view business analytics', function () {
    $business = Business::factory()->create();
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $this->getJson("/api/businesses/{$business->id}/analytics")
        ->assertForbidden();
});

test('an unauthenticated request cannot view business analytics', function () {
    $business = Business::factory()->create();

    $this->getJson("/api/businesses/{$business->id}/analytics")
        ->assertUnauthorized();
});
