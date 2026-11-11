<?php

namespace App\Services\Assessments;

use App\Enums\AdvanceStatus;
use App\Enums\AssessmentStatus;
use App\Enums\TransactionDirection;
use App\Models\Business;
use App\Models\EraAssessment;
use App\Models\Transaction;
use App\Models\User;
use App\Support\BasisPoints;
use App\Support\Money;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AssessmentService
{
    public function __construct(private readonly PythonAssessmentClient $client) {}

    /**
     * Request an assessment from the Python ML/Decision API and persist the
     * result as a new, immutable snapshot.
     *
     * The Python API speaks major-unit KES amounts and mixed rate scales; this
     * service converts to/from the internal integer minor-unit and basis-point
     * conventions at the boundary.
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
     * Assemble the request payload expected by the Python /api/v1/assessments
     * endpoint. Transaction amounts are converted from minor units to signed
     * major-unit KES and direction is mapped to credit/debit.
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
            'assessment_reference' => (string) Str::orderedUuid(),
            'assessment_date' => now()->toIso8601String(),
            'transactions' => $transactions,
            'business_profile' => [
                'business_type' => $business->businessType?->slug,
                'sector' => $business->businessType?->category,
                'cohort' => null,
                'registration_date' => $business->operating_since?->toDateString(),
            ],
            'repayment_history' => $this->buildRepaymentHistory($business),
            'exposure' => $this->buildExposure($business),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function buildRepaymentHistory(Business $business): array
    {
        $advances = $business->advances()->get();

        return [
            'advances_count' => $advances->count(),
            'on_time_count' => $advances->filter(fn ($advance) => $advance->status === AdvanceStatus::Settled)->count(),
            'late_count' => $advances->filter(fn ($advance) => $advance->status === AdvanceStatus::Overdue)->count(),
            'default_count' => $advances->filter(fn ($advance) => $advance->status === AdvanceStatus::Defaulted)->count(),
        ];
    }

    /**
     * @return array<string, mixed>
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

        return [
            'outstanding_principal' => $this->minorToMajor($active->sum('outstanding_balance_minor')),
            'active_advances' => $active->count(),
            'max_exposure_kes' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapTransaction(Transaction $transaction): array
    {
        $isInflow = $transaction->direction === TransactionDirection::Inflow;
        $signedMinor = $isInflow ? $transaction->amount_minor : -$transaction->amount_minor;

        return [
            'transaction_id' => (string) $transaction->id,
            'transaction_date' => $transaction->transaction_time->toIso8601String(),
            'amount' => $this->minorToMajor($signedMinor),
            'direction' => $isInflow ? 'credit' : 'debit',
            'status' => $transaction->provider_status,
            'transaction_type' => $transaction->transaction_type,
            'reference' => $transaction->counterparty_reference,
            'counterparty' => $transaction->counterparty_name,
        ];
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    protected function validateResponse(array $response): array
    {
        return Validator::make($response, [
            'assessment_status' => ['required', Rule::in(array_column(AssessmentStatus::cases(), 'value'))],
            'predicted_revenue' => ['nullable', 'numeric', 'min:0'],
            'forecast_period_days' => ['required', 'integer', 'min:1'],
            'reliability_score' => ['required', 'numeric', 'between:0,100'],
            'recommended_advance_rate' => ['required', 'numeric', 'between:0,1'],
            'recommended_advance_amount' => ['required', 'numeric', 'min:0'],
            'recommended_recovery_rate' => ['required', 'numeric', 'min:0'],
            'maximum_repayment_days' => ['required', 'integer', 'min:1'],
            'score_breakdown' => ['sometimes', 'array'],
            'reason_codes' => ['sometimes', 'array'],
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
        return EraAssessment::create([
            'business_id' => $business->id,
            'model_version_id' => $options['model_version_id'] ?? null,
            'decision_policy_id' => $options['decision_policy_id'] ?? null,
            'requested_by_user_id' => $requestedBy?->id,
            'assessment_status' => AssessmentStatus::from($validated['assessment_status']),
            'assessment_date' => now(),
            'forecast_period_days' => $validated['forecast_period_days'],
            'predicted_revenue_minor' => $validated['predicted_revenue'] !== null
                ? $this->majorToMinor($validated['predicted_revenue'])
                : null,
            'reliability_score' => $validated['reliability_score'],
            'recommended_advance_rate_bps' => $this->advanceRateToBasisPoints($validated['recommended_advance_rate']),
            'recommended_advance_amount_minor' => $this->majorToMinor($validated['recommended_advance_amount']),
            'recommended_recovery_rate_bps' => $this->recoveryRateToBasisPoints($validated['recommended_recovery_rate']),
            'maximum_repayment_days' => $validated['maximum_repayment_days'],
            'score_breakdown' => $validated['score_breakdown'] ?? [],
            'reason_codes' => $validated['reason_codes'] ?? [],
            'warnings' => $validated['warnings'] ?? [],
            'input_snapshot' => $input,
            'output_snapshot' => $rawResponse,
        ]);
    }

    /**
     * Convert an integer minor-unit amount to major-unit KES for the wire.
     */
    private function minorToMajor(int $minor): float
    {
        return (float) Money::of($minor)->toMajor();
    }

    /**
     * Convert a major-unit KES amount from the wire into integer minor units.
     */
    private function majorToMinor(float|int|string $major): int
    {
        return Money::fromMajor((string) $major)->minor;
    }

    /**
     * The Python API reports the advance rate as a decimal fraction (0.3 = 30%).
     */
    private function advanceRateToBasisPoints(float|int|string $rate): int
    {
        return BasisPoints::fromRate((string) $rate)->value;
    }

    /**
     * The Python API reports the recovery rate as a percentage (10.0 = 10%).
     */
    private function recoveryRateToBasisPoints(float|int|string $rate): int
    {
        return BasisPoints::fromPercent((string) $rate)->value;
    }
}
