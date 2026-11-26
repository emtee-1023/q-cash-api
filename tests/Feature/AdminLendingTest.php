<?php

use App\Enums\AdvanceStatus;
use App\Models\Advance;
use App\Models\Business;
use App\Models\User;
use App\Support\AdminToken;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function adminLendingToken(User $admin): string
{
    return $admin->createToken(AdminToken::NAME, [AdminToken::ABILITY])->plainTextToken;
}

test('admins can view platform lending totals and per-loan apr', function () {
    $admin = User::factory()->admin()->create();
    $business = Business::factory()->create(['business_name' => 'Lending Business']);

    Advance::factory()->for($business)->create([
        'principal_amount_minor' => 1_000_000,
        'fee_amount_minor' => 30_000,
        'total_repayable_minor' => 1_030_000,
        'amount_repaid_minor' => 30_000,
        'outstanding_balance_minor' => 1_000_000,
        'maximum_repayment_days' => 365,
        'disbursed_at' => now(),
        'status' => AdvanceStatus::Active->value,
    ]);

    $this->withToken(adminLendingToken($admin))
        ->getJson('/api/admin/lending')
        ->assertOk()
        ->assertJsonPath('summary.disbursed_principal_minor', 1_000_000)
        ->assertJsonPath('summary.repaid_minor', 30_000)
        ->assertJsonPath('summary.outstanding_minor', 1_000_000)
        ->assertJsonPath('summary.weighted_apr_bps', 300)
        ->assertJsonPath('businesses.0.business_id', $business->id);

    $this->withToken(adminLendingToken($admin))
        ->getJson("/api/admin/businesses/{$business->id}/lending")
        ->assertOk()
        ->assertJsonPath('summary.loans_count', 1)
        ->assertJsonPath('advances.0.apr_bps', 300)
        ->assertJsonPath('advances.0.business_id', $business->id);
});

test('normal users cannot access admin lending data', function () {
    $user = User::factory()->create();

    $this->withToken($user->createToken('mobile', ['mobile'])->plainTextToken)
        ->getJson('/api/admin/lending')
        ->assertForbidden();
});
