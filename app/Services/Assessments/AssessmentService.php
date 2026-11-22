<?php

namespace App\Services\Assessments;

use App\Enums\AdvanceStatus;
use App\Enums\AssessmentStatus;
use App\Enums\TransactionDirection;
use App\Models\Business;
use App\Models\EraAssessment;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AssessmentService
{
    public function __construct(private readonly PythonAssessmentClient $client) {}

    /**
     * Request an assessment from the Python ML/Decision API and persist the
     * result as a new, immutable snapshot.
     *
     * The API speaks integer minor units and basis points throughout, which
     * matches the internal conventions, so values are stored without scaling.
     *
     * @param  array<string, mixed>  $options
     */
    public function request(Business $business, ?User $requestedBy = null, array $options = []): EraAssessment
    {
        $input = $this->buildInput($business, $options);

        $rawResponse = $this->client->assess($input);

        $validated = $this->validateResponse($rawResponse);

        return $this->persist($business, $input, $rawResponse, $validated, $requestedBy, $options);
    }

    /**
     * Assemble the request payload expected by the Python /assess endpoint.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function buildInput(Business $business, array $options = []): array
    {
        $transactions = $business->transactions()
            ->orderBy('transaction_time')
            ->limit($options['transaction_limit'] ?? 2000)
            ->get()
            ->map(fn (Transaction $transaction) => $this->mapTransaction($transaction))
            ->values()
            ->all();

        return [
            'business_id' => $business->public_id,
            'sector' => $this->sector($business),
            'assessment_as_of' => now()->toIso8601String(),
            'transactions' => $transactions,
            'repayment' => $this->buildRepayment($business),
            'exposure' => $this->buildExposure($business),
            'business_profile' => [
                'business_type' => $business->businessType?->slug,
                'sector' => $this->sector($business),
                'cohort' => null,
                'registration_date' => $business->operating_since?->toDateString(),
            ],
        ];
    }

    /**
     * Map the internal business type to one of the sectors the engine knows.
     * Unknown values fall back to general retail (the engine's generic margin).
     */
    private function sector(Business $business): string
    {
        $businessType = $business->businessType;

        return match ($businessType?->slug) {
            'mama-mboga' => 'greengrocer',
            'small-restaurant-cafe' => 'food_vendor',
            'salon-barbershop' => 'barbershop',
            'boutique-fashion' => 'secondhand_clothing',
            'hardware-store' => 'hardware',
            default => match ($businessType?->category) {
                'food' => 'food_vendor',
                default => 'general_retail',
            },
        };
    }

    /**
     * @return array{settled_count: int, on_time_count: int}
     */
    private function buildRepayment(Business $business): array
    {
        $settled = $business->advances()
            ->where('status', AdvanceStatus::Settled->value)
            ->get();

        $onTime = $settled->filter(fn ($advance) => $advance->settled_at !== null
            && $advance->due_at !== null
            && $advance->settled_at->lessThanOrEqualTo($advance->due_at));

        return [
            'settled_count' => $settled->count(),
            'on_time_count' => $onTime->count(),
        ];
    }

    /**
     * @return array<string, int|bool>
     */
    private function buildExposure(Business $business): array
    {
        $active = $business->advances()
            ->whereIn('status', [
                AdvanceStatus::Active->value,
                AdvanceStatus::Overdue->value,
                AdvanceStatus::Disbursing->value,
            ])
            ->get();

        $overdue = $active->filter(fn ($advance) => $advance->status === AdvanceStatus::Overdue);

        return [
            'outstanding_minor' => (int) $active->sum('outstanding_balance_minor'),
            'overdue_minor' => (int) $overdue->sum('outstanding_balance_minor'),
            'has_overdue' => $overdue->isNotEmpty(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapTransaction(Transaction $transaction): array
    {
        $isInflow = $transaction->direction === TransactionDirection::Inflow;

        return [
            'ref' => (string) $transaction->id,
            'occurred_at' => $transaction->transaction_time->toIso8601String(),
            'amount_minor' => $transaction->amount_minor,
            'type' => $isInflow ? 'credit' : 'debit',
            'is_reversal_of' => null,
            'reference' => $transaction->counterparty_reference,
            'payer_hash' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    protected function validateResponse(array $response): array
    {
        return Validator::make($response, [
            'status' => ['required', Rule::in(['eligible', 'not_eligible', 'insufficient_data'])],
            'reason_codes' => ['sometimes', 'array'],
            'detail' => ['sometimes', 'array'],
            'model_version' => ['sometimes', 'nullable', 'string'],
            'history_tier' => ['sometimes', 'nullable', 'string'],
            'observed_days' => ['sometimes', 'nullable', 'integer'],
            'intelligence' => ['sometimes', 'array'],
            'prediction' => ['required_unless:status,insufficient_data', 'array'],
            'prediction.horizon_days' => ['sometimes', 'integer'],
            'prediction.point_minor' => ['sometimes', 'integer'],
            'prediction.lower_bound_minor' => ['sometimes', 'integer'],
            'prediction.method' => ['sometimes', 'string'],
            'reliability' => ['required_unless:status,insufficient_data', 'array'],
            'reliability.score' => ['sometimes', 'nullable', 'numeric', 'between:0,100'],
            'reliability.band' => ['sometimes', 'nullable', 'string'],
            'reliability.components' => ['sometimes', 'array'],
            'reliability.weights_renormalised' => ['sometimes', 'boolean'],
            'recommendation' => ['required_unless:status,insufficient_data', 'array'],
            'recommendation.status' => ['sometimes', 'string'],
            'recommendation.amount_minor' => ['sometimes', 'integer', 'min:0'],
            'recommendation.advance_rate_bps' => ['sometimes', 'integer', 'min:0'],
            'recommendation.binding_constraint' => ['sometimes', 'string'],
            'recommendation.reason_codes' => ['sometimes', 'array'],
            'recommendation.fee_minor' => ['sometimes', 'integer', 'min:0'],
            'recommendation.total_repayable_minor' => ['sometimes', 'integer', 'min:0'],
            'recommendation.affordability_capacity_minor' => ['sometimes', 'integer', 'min:0'],
            'terms' => ['required_unless:status,insufficient_data', 'array'],
            'terms.term_days' => ['sometimes', 'integer'],
            'terms.max_repayment_days' => ['sometimes', 'integer'],
            'terms.recovery_rate_bps' => ['sometimes', 'integer', 'min:0'],
            'terms.daily_cap_bps' => ['sometimes', 'integer'],
            'terms.fallback' => ['sometimes', 'string'],
            'warnings' => ['sometimes', 'array'],
        ])->validate();
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $rawResponse
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $options
     */
    protected function persist(
        Business $business,
        array $input,
        array $rawResponse,
        array $validated,
        ?User $requestedBy,
        array $options,
    ): EraAssessment {
        $prediction = $validated['prediction'] ?? [];
        $reliability = $validated['reliability'] ?? [];
        $recommendation = $validated['recommendation'] ?? [];
        $terms = $validated['terms'] ?? [];

        return EraAssessment::create([
            'business_id' => $business->id,
            'model_version_id' => $options['model_version_id'] ?? null,
            'decision_policy_id' => $options['decision_policy_id'] ?? null,
            'requested_by_user_id' => $requestedBy?->id,
            'assessment_status' => $this->mapStatus($validated['status']),
            'assessment_date' => now(),
            'forecast_period_days' => $prediction['horizon_days'] ?? 30,
            'predicted_revenue_minor' => $prediction['point_minor'] ?? null,
            'reliability_score' => $reliability['score'] ?? null,
            'recommended_advance_rate_bps' => $recommendation['advance_rate_bps'] ?? null,
            'recommended_advance_amount_minor' => $recommendation['amount_minor'] ?? null,
            'recommended_recovery_rate_bps' => $terms['recovery_rate_bps'] ?? null,
            'maximum_repayment_days' => $terms['max_repayment_days'] ?? null,
            'score_breakdown' => $reliability,
            'reason_codes' => $validated['reason_codes'] ?? [],
            'warnings' => $validated['warnings'] ?? $this->warnings($validated),
            'input_snapshot' => $input,
            'output_snapshot' => $rawResponse,
        ]);
    }

    private function mapStatus(string $status): AssessmentStatus
    {
        return match ($status) {
            'eligible' => AssessmentStatus::Eligible,
            'not_eligible' => AssessmentStatus::Ineligible,
            default => AssessmentStatus::InsufficientData,
        };
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function warnings(array $validated): array
    {
        return isset($validated['detail']) ? ['detail' => $validated['detail']] : [];
    }
}
