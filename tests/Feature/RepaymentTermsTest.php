<?php

use App\Models\Business;
use App\Models\EraAssessment;
use App\Models\User;
use App\Services\Advances\AdvanceService;
use App\Services\Advances\OfferService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('adjusting repayment terms appends a new record and preserves history', function () {
    $business = Business::factory()->create();
    $user = User::factory()->create();
    $assessment = EraAssessment::factory()->create([
        'business_id' => $business->id,
        'assessment_status' => 'eligible',
        'recommended_advance_amount_minor' => 3000000,
        'recommended_recovery_rate_bps' => 1000,
        'maximum_repayment_days' => 30,
    ]);

    $offer = app(OfferService::class)->createOffer($assessment);
    $advance = app(AdvanceService::class)->acceptOffer($offer);

    $original = $advance->repaymentTerms()->first();

    $service = app(AdvanceService::class);
    $service->adjustRepaymentTerms(
        $advance,
        1500,
        45,
        'Business income increased',
        policyVersion: 'v2',
        changedBy: $user,
    );

    expect($advance->repaymentTerms()->count())->toBe(2);

    $original->refresh();
    expect($original->effective_until)->not->toBeNull();
    expect($original->recovery_rate_bps)->toBe(1000);

    $current = $advance->currentRepaymentTerm;
    expect($current->recovery_rate_bps)->toBe(1500);
    expect($current->maximum_repayment_days)->toBe(45);
    expect($current->adjustment_reason)->toBe('Business income increased');
    expect($current->policy_version)->toBe('v2');
    expect($current->changed_by_user_id)->toBe($user->id);

    expect($advance->refresh()->recovery_rate_bps)->toBe(1500);
});

test('the original accepted terms are never overwritten', function () {
    $business = Business::factory()->create();
    $assessment = EraAssessment::factory()->create([
        'business_id' => $business->id,
        'assessment_status' => 'eligible',
        'recommended_advance_amount_minor' => 3000000,
        'recommended_recovery_rate_bps' => 1000,
        'maximum_repayment_days' => 30,
    ]);

    $offer = app(OfferService::class)->createOffer($assessment);
    $advance = app(AdvanceService::class)->acceptOffer($offer);

    app(AdvanceService::class)->adjustRepaymentTerms($advance, 2000, 60, 'Adjustment');

    $original = $advance->repaymentTerms()->orderBy('effective_from')->first();

    expect($original->recovery_rate_bps)->toBe(1000);
    expect($original->maximum_repayment_days)->toBe(30);
    expect($original->adjustment_reason)->toBe('Original accepted terms');
});
