<?php

use App\Enums\AdvanceStatus;
use App\Enums\OfferStatus;
use App\Models\Business;
use App\Models\EraAssessment;
use App\Services\Advances\AdvanceService;
use App\Services\Advances\OfferService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function eligibleAssessment(Business $business): EraAssessment
{
    return EraAssessment::factory()->create([
        'business_id' => $business->id,
        'assessment_status' => 'eligible',
        'recommended_advance_amount_minor' => 3000000,
        'recommended_recovery_rate_bps' => 1000,
        'maximum_repayment_days' => 30,
    ]);
}

test('an offer is created from an eligible assessment', function () {
    $business = Business::factory()->create();
    $assessment = eligibleAssessment($business);

    $offer = app(OfferService::class)->createOffer($assessment);

    expect($offer->status)->toBe(OfferStatus::Pending);
    expect($offer->offered_amount_minor)->toBe(3000000);
    expect($offer->total_repayable_minor)->toBe(3000000);
    expect($offer->offer_terms)->toHaveKey('offered_amount_minor');
});

test('an offer cannot be created from an ineligible assessment', function () {
    $business = Business::factory()->create();
    $assessment = EraAssessment::factory()->create([
        'business_id' => $business->id,
        'assessment_status' => 'ineligible',
    ]);

    app(OfferService::class)->createOffer($assessment);
})->throws(RuntimeException::class);

test('accepting an offer creates an advance with original terms', function () {
    $business = Business::factory()->create();
    $assessment = eligibleAssessment($business);
    $offer = app(OfferService::class)->createOffer($assessment);

    $advance = app(AdvanceService::class)->acceptOffer($offer);

    expect($advance->status)->toBe(AdvanceStatus::Pending);
    expect($advance->outstanding_balance_minor)->toBe(3000000);
    expect($advance->amount_repaid_minor)->toBe(0);

    $offer->refresh();
    expect($offer->status)->toBe(OfferStatus::Accepted);
    expect($offer->accepted_at)->not->toBeNull();

    expect($advance->repaymentTerms()->count())->toBe(1);
    expect($advance->repaymentTerms()->first()->adjustment_reason)->toBe('Original accepted terms');
});

test('an offer can only be accepted once', function () {
    $business = Business::factory()->create();
    $assessment = eligibleAssessment($business);
    $offer = app(OfferService::class)->createOffer($assessment);

    $service = app(AdvanceService::class);
    $service->acceptOffer($offer);

    $service->acceptOffer($offer);
})->throws(RuntimeException::class);
