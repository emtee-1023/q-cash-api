<?php

use App\Enums\AdvanceStatus;
use App\Models\Advance;
use App\Models\Business;
use App\Models\EraAssessment;
use App\Services\Advances\AdvanceExposureService;
use App\Services\Advances\AdvanceService;
use App\Services\Advances\OfferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function exposureAdvance(Business $business, int $outstanding, string $status = 'active'): Advance
{
    return Advance::factory()->for($business)->create([
        'principal_amount_minor' => $outstanding,
        'fee_amount_minor' => 0,
        'total_repayable_minor' => $outstanding,
        'outstanding_balance_minor' => $outstanding,
        'amount_repaid_minor' => 0,
        'maximum_repayment_days' => 30,
        'status' => $status,
    ]);
}

function exposureAssessment(Business $business, int $recommended = 3000000): EraAssessment
{
    return EraAssessment::factory()->for($business)->create([
        'assessment_status' => 'eligible',
        'recommended_advance_amount_minor' => $recommended,
        'recommended_recovery_rate_bps' => 1500,
        'maximum_repayment_days' => 30,
    ]);
}

test('available exposure is the assessment limit less the outstanding balance', function () {
    $business = Business::factory()->create();
    exposureAdvance($business, 800000);

    expect(app(AdvanceExposureService::class)->availableExposureMinor($business, 1200000))
        ->toBe(400000);
});

test('a borrower cannot exceed the remaining headroom under the assessment limit', function () {
    $business = Business::factory()->create();
    exposureAdvance($business, 800000);
    $assessment = exposureAssessment($business, 1200000);

    app(OfferService::class)->createOffer($assessment, [
        'requested_amount_minor' => 1200000,
    ]);
})->throws(ValidationException::class);

test('a borrower can borrow the remaining headroom', function () {
    $business = Business::factory()->create();
    exposureAdvance($business, 800000);
    $assessment = exposureAssessment($business, 1200000);

    $offer = app(OfferService::class)->createOffer($assessment, [
        'requested_amount_minor' => 400000,
    ]);

    expect($offer->total_repayable_minor)->toBe(400000);
});

test('pending advances count towards committed exposure', function () {
    $business = Business::factory()->create();
    exposureAdvance($business, 800000, AdvanceStatus::Pending->value);
    $assessment = exposureAssessment($business, 1200000);

    app(OfferService::class)->createOffer($assessment, [
        'requested_amount_minor' => 1200000,
    ]);
})->throws(ValidationException::class);

test('accepting an offer rechecks exposure', function () {
    $business = Business::factory()->create();
    $assessment = exposureAssessment($business, 3000000);

    $offer = app(OfferService::class)->createOffer($assessment, [
        'requested_amount_minor' => 3000000,
    ]);

    exposureAdvance($business, 800000);

    app(AdvanceService::class)->acceptOffer($offer);
})->throws(ValidationException::class);

test('settled advances do not count towards exposure', function () {
    $business = Business::factory()->create();
    exposureAdvance($business, 4000000);
    exposureAdvance($business, 8000000, AdvanceStatus::Settled->value);

    expect(app(AdvanceExposureService::class)->availableExposureMinor($business, 5000000))
        ->toBe(1000000);
});
