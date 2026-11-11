<?php

use App\Models\Business;
use App\Models\BusinessType;
use App\Models\EraAssessment;
use App\Models\Transaction;
use App\Services\Assessments\AssessmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function fakeEligibleAssessmentResponse(): array
{
    return [
        'assessment_reference' => 'ref-1',
        'business_id' => 'biz-1',
        'assessment_status' => 'eligible',
        'predicted_revenue' => 100000.00,
        'forecast_period_days' => 30,
        'reliability_score' => 69.74,
        'score_breakdown' => [],
        'recommended_advance_rate' => 0.3,
        'recommended_advance_amount' => 30000.00,
        'recommended_recovery_rate' => 10.0,
        'maximum_repayment_days' => 30,
        'repayment_fallback' => 'Pre-agreed installment arrangement.',
        'repayment_risk_flags' => [],
        'reason_codes' => [],
        'warnings' => [],
        'model_version' => 'random_forest/D_revenue_behavioural_seasonal',
        'decision_policy_version' => 'era-policy-v1',
        'assessment_timestamp' => now()->toIso8601String(),
        'currency' => 'KES',
        'transaction_summary' => [
            'total_transactions' => 0,
            'category_counts' => [],
            'anomaly_count' => 0,
            'unknown_count' => 0,
            'eligible_revenue_kes' => 0.0,
        ],
    ];
}

test('an assessment is requested from python and converted to minor units', function () {
    config(['services.ml_api.url' => 'http://ml.test']);

    Http::fake([
        'http://ml.test/api/v1/assessments' => Http::response(fakeEligibleAssessmentResponse(), 200),
    ]);

    $business = Business::factory()->create();

    $assessment = app(AssessmentService::class)->request($business);

    expect($assessment->assessment_status->value)->toBe('eligible');
    expect($assessment->predicted_revenue_minor)->toBe(10000000);
    expect($assessment->recommended_advance_amount_minor)->toBe(3000000);
    expect($assessment->recommended_advance_rate_bps)->toBe(3000);
    expect($assessment->recommended_recovery_rate_bps)->toBe(1000);
    expect($assessment->reliability_score)->toBe('69.7400');
    expect($assessment->input_snapshot)->toHaveKeys(['business_id', 'transactions']);
    expect($assessment->output_snapshot)->toHaveKey('assessment_status');
});

test('the assessment payload uses the business type slug and category', function () {
    config(['services.ml_api.url' => 'http://ml.test']);

    Http::fake([
        'http://ml.test/api/v1/assessments' => Http::response(fakeEligibleAssessmentResponse(), 200),
    ]);

    $businessType = BusinessType::factory()->create([
        'slug' => 'mama-mboga',
        'category' => 'food',
    ]);
    $business = Business::factory()->create(['business_type_id' => $businessType->id]);

    app(AssessmentService::class)->request($business);

    Http::assertSent(fn ($request) => $request['business_profile']['business_type'] === 'mama-mboga'
        && $request['business_profile']['sector'] === 'food');
});

test('transaction amounts are sent to python in signed major units', function () {
    config(['services.ml_api.url' => 'http://ml.test']);

    Http::fake([
        'http://ml.test/api/v1/assessments' => Http::response(fakeEligibleAssessmentResponse(), 200),
    ]);

    $business = Business::factory()->create();
    Transaction::factory()->create([
        'business_id' => $business->id,
        'direction' => 'inflow',
        'amount_minor' => 10050,
        'transaction_time' => now()->subDays(2),
    ]);
    Transaction::factory()->create([
        'business_id' => $business->id,
        'direction' => 'outflow',
        'amount_minor' => 5075,
        'transaction_time' => now()->subDay(),
    ]);

    app(AssessmentService::class)->request($business);

    Http::assertSent(function ($request) {
        $transactions = $request['transactions'];

        return count($transactions) === 2
            && $transactions[0]['amount'] === 100.5
            && $transactions[0]['direction'] === 'credit'
            && $transactions[1]['amount'] === -50.75
            && $transactions[1]['direction'] === 'debit';
    });
});

test('each assessment is a new historical snapshot', function () {
    config(['services.ml_api.url' => 'http://ml.test']);

    Http::fake([
        'http://ml.test/api/v1/assessments' => Http::response(fakeEligibleAssessmentResponse(), 200),
    ]);

    $business = Business::factory()->create();
    $service = app(AssessmentService::class);

    $service->request($business);
    $service->request($business);

    expect(EraAssessment::query()->count())->toBe(2);
});

test('an invalid python response is rejected', function () {
    config(['services.ml_api.url' => 'http://ml.test']);

    Http::fake([
        'http://ml.test/api/v1/assessments' => Http::response(['assessment_status' => 'eligible'], 200),
    ]);

    $business = Business::factory()->create();

    app(AssessmentService::class)->request($business);
})->throws(ValidationException::class);
