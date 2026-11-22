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
        'status' => 'eligible',
        'model_version' => 'xgb-ratio-ma90-2026.11.0',
        'history_tier' => 'full',
        'observed_days' => 95,
        'intelligence' => [
            'counts' => ['revenue_eligible' => 657],
            'value_minor' => ['eligible' => 20695100],
            'anomalies_flagged' => 3,
        ],
        'prediction' => [
            'horizon_days' => 30,
            'point_minor' => 10000000,
            'lower_bound_minor' => 8500000,
            'method' => 'xgboost_ratio_ma90',
        ],
        'reliability' => [
            'score' => 69.74,
            'band' => 'B',
            'components' => [],
            'weights_renormalised' => false,
        ],
        'recommendation' => [
            'status' => 'eligible',
            'amount_minor' => 3000000,
            'advance_rate_bps' => 3000,
            'binding_constraint' => 'affordability',
            'reason_codes' => [],
            'fee_minor' => 90000,
            'total_repayable_minor' => 3090000,
            'affordability_capacity_minor' => 3393502,
        ],
        'terms' => [
            'term_days' => 30,
            'max_repayment_days' => 60,
            'recovery_rate_bps' => 1000,
            'daily_cap_bps' => 3000,
            'fallback' => 'extend_once_then_partner_review',
        ],
        'reason_codes' => [],
    ];
}

test('an assessment is requested from python and stored as minor units', function () {
    config(['services.ml_api.url' => 'http://ml.test']);

    Http::fake([
        'http://ml.test/assess' => Http::response(fakeEligibleAssessmentResponse(), 200),
    ]);

    $business = Business::factory()->create();

    $assessment = app(AssessmentService::class)->request($business);

    expect($assessment->assessment_status->value)->toBe('eligible');
    expect($assessment->predicted_revenue_minor)->toBe(10000000);
    expect($assessment->recommended_advance_amount_minor)->toBe(3000000);
    expect($assessment->recommended_advance_rate_bps)->toBe(3000);
    expect($assessment->recommended_recovery_rate_bps)->toBe(1000);
    expect($assessment->maximum_repayment_days)->toBe(60);
    expect($assessment->reliability_score)->toBe('69.7400');
    expect($assessment->input_snapshot)->toHaveKeys(['business_id', 'sector', 'transactions']);
    expect($assessment->output_snapshot)->toHaveKey('status');
});

test('the assessment payload uses a mapped sector and the business type slug', function () {
    config(['services.ml_api.url' => 'http://ml.test']);

    Http::fake([
        'http://ml.test/assess' => Http::response(fakeEligibleAssessmentResponse(), 200),
    ]);

    $businessType = BusinessType::factory()->create([
        'slug' => 'mama-mboga',
        'category' => 'food',
    ]);
    $business = Business::factory()->create(['business_type_id' => $businessType->id]);

    app(AssessmentService::class)->request($business);

    Http::assertSent(fn ($request) => $request['sector'] === 'greengrocer'
        && $request['business_profile']['business_type'] === 'mama-mboga');
});

test('transaction amounts are sent as positive minor units with a credit or debit type', function () {
    config(['services.ml_api.url' => 'http://ml.test']);

    Http::fake([
        'http://ml.test/assess' => Http::response(fakeEligibleAssessmentResponse(), 200),
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
            && $transactions[0]['amount_minor'] === 10050
            && $transactions[0]['type'] === 'credit'
            && $transactions[1]['amount_minor'] === 5075
            && $transactions[1]['type'] === 'debit';
    });
});

test('a not eligible response is stored as ineligible with a zero amount', function () {
    config(['services.ml_api.url' => 'http://ml.test']);

    $response = fakeEligibleAssessmentResponse();
    $response['status'] = 'not_eligible';
    $response['reason_codes'] = ['R02', 'R05'];
    $response['recommendation'] = [
        'status' => 'not_eligible',
        'amount_minor' => 0,
        'advance_rate_bps' => 2200,
        'binding_constraint' => 'affordability',
        'reason_codes' => ['R02', 'R05'],
    ];

    Http::fake([
        'http://ml.test/assess' => Http::response($response, 200),
    ]);

    $assessment = app(AssessmentService::class)->request(Business::factory()->create());

    expect($assessment->assessment_status->value)->toBe('ineligible');
    expect($assessment->recommended_advance_amount_minor)->toBe(0);
    expect($assessment->reason_codes)->toBe(['R02', 'R05']);
});

test('an insufficient data response is stored with nullable recommendation fields', function () {
    config(['services.ml_api.url' => 'http://ml.test']);

    Http::fake([
        'http://ml.test/assess' => Http::response([
            'status' => 'insufficient_data',
            'reason_codes' => ['R01'],
            'detail' => [
                'observed_days' => 1,
                'required_days' => 14,
                'eligible_tx' => 10,
                'required_txn' => 10,
            ],
        ], 200),
    ]);

    $assessment = app(AssessmentService::class)->request(Business::factory()->create());

    expect($assessment->assessment_status->value)->toBe('insufficient_data');
    expect($assessment->predicted_revenue_minor)->toBeNull();
    expect($assessment->recommended_advance_amount_minor)->toBeNull();
    expect($assessment->warnings)->toHaveKey('detail');
});

test('each assessment is a new historical snapshot', function () {
    config(['services.ml_api.url' => 'http://ml.test']);

    Http::fake([
        'http://ml.test/assess' => Http::response(fakeEligibleAssessmentResponse(), 200),
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
        'http://ml.test/assess' => Http::response(['status' => 'unknown'], 200),
    ]);

    $business = Business::factory()->create();

    app(AssessmentService::class)->request($business);
})->throws(ValidationException::class);
