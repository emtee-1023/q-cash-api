<?php

namespace Database\Seeders;

use App\Enums\AdvanceStatus;
use App\Enums\AssessmentStatus;
use App\Enums\CollectionMethod;
use App\Enums\CollectionStatus;
use App\Enums\DisbursementStatus;
use App\Enums\OfferStatus;
use App\Models\Advance;
use App\Models\AdvanceLedgerEntry;
use App\Models\AdvanceOffer;
use App\Models\Business;
use App\Models\BusinessType;
use App\Models\County;
use App\Models\DecisionPolicy;
use App\Models\Disbursement;
use App\Models\EraAssessment;
use App\Models\FinancingPartner;
use App\Models\ModelVersion;
use App\Models\RepaymentCollection;
use App\Models\SubCounty;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Ward;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Q-Cash synthetic seed data.
 *
 * Reproducible via a fixed Faker seed: `php artisan migrate:fresh --seed`
 * produces the same population every time.
 *
 * Money: integer minor units (KES cents). Rates: integer basis points.
 *
 * Business -> archetype -> expected Python outcome map (by seq):
 *   01  A_stable_barbershop  -> ELIGIBLE (high score, rate band 0.35), SETTLED advance
 *   02  B_volatile_retail    -> ELIGIBLE (medium, 0.30), ACTIVE advance (partial)
 *   03  C_growing            -> ELIGIBLE (medium), DISBURSING advance
 *   04  D_declining          -> INELIGIBLE (low reliability score)
 *   05  E_weekly_seasonal    -> ELIGIBLE (medium), OVERDUE advance
 *   06  F_monthly_seasonal   -> ELIGIBLE, DEFAULTED advance
 *   07  G_intermittent       -> INELIGIBLE (many zero-revenue days)
 *   08  H_non_revenue        -> ELIGIBLE, but eligible revenue << total inflows
 *   09  I_anomaly            -> MANUAL_REVIEW / flagged anomalies (missing ids + large sales)
 *   10  unknown_types        -> MANUAL_REVIEW (> 50% unclassified)
 *   11  cold_start           -> ELIGIBLE (conservative, rate 0.10, KSh 5,000) + PENDING offer
 *   12  fresh                -> INSUFFICIENT_DATA (< 90 days, no profile)
 *   13  exposure             -> INELIGIBLE (exposure reached: active advance >= max_exposure)
 *   14  I_micro_vendor       -> ELIGIBLE, ACTIVE advance (partial) + REJECTED offer
 *   15  J_micro_service      -> ELIGIBLE, SETTLED advance + EXPIRED offer
 *   16  pending              -> (not assessed)
 *   17  suspended            -> (not assessed)
 */
class QCashSeeder extends Seeder
{
    private const SEED = 20261105;

    private const SALE_TYPES = [
        'till_payment_received',
        'paybill_payment',
        'buygoods_payment',
        'sale_receipt',
        'invoice_payment',
    ];

    private const LARGE_SALE_MINOR = 5000000; // KSh 50,000

    private Carbon $now;

    public function run(): void
    {
        fake()->seed(self::SEED);
        $this->now = now()->startOfDay();

        $this->call(BusinessTypeSeeder::class);

        if (
            County::query()->doesntExist()
            || SubCounty::query()->doesntExist()
            || Ward::query()->doesntExist()
        ) {
            $this->call(LocationSeeder::class);
        }

        $businessTypes = BusinessType::query()->pluck('id', 'slug');
        $users = $this->seedUsers();
        $modelVersion = $this->seedModelVersions();
        $policy = $this->seedDecisionPolicies();
        $partners = $this->seedFinancingPartners();

        foreach ($this->scenarioDefinitions() as $scenario) {
            $business = $this->seedBusiness($scenario, $users, $businessTypes);
            $transactions = $this->seedTransactions($business, $scenario);
            $this->seedClassifications($business, $transactions, $scenario);

            if ($scenario['lifecycle'] !== 'none') {
                $this->seedLifecycle($business, $scenario, $transactions, $partners, $modelVersion, $policy);
            }
        }
    }

    /**
     * @return array<int, User>
     */
    private function seedUsers(): array
    {
        return collect([
            ['name' => 'Demo Owner 001', 'phone' => '254790000001'],
            ['name' => 'Demo Owner 002', 'phone' => '254790000002'],
            ['name' => 'Demo Staff 001', 'phone' => '254790000003'],
        ])->map(fn ($u, $i) => User::factory()->create([
            'name' => $u['name'],
            'email' => sprintf('demo%03d@example.test', $i + 1),
            'phone_number' => $u['phone'],
            'phone_verified_at' => $this->now,
        ]))->all();
    }

    private function seedModelVersions(): ModelVersion
    {
        ModelVersion::create([
            'model_name' => 'random_forest/D_revenue_behavioural_seasonal',
            'model_type' => 'revenue_regressor',
            'version' => 'synthetic-v1.0',
            'artifact_uri' => 's3://qcash-models/revenue/synthetic-v1.0',
            'evaluation_metrics' => [
                'holdout_mae_kes' => 12298.18,
                'error_band_kes' => 12298.18,
                'best_baseline_mae_kes' => 12918.12,
            ],
            'training_data_description' => ['source' => 'synthetic', 'target' => 'M-Pesa Till revenue (credit), next 30 days'],
            'trained_at' => $this->now->copy()->subDays(30),
            'is_active' => true,
        ]);

        ModelVersion::create([
            'model_name' => 'transaction_rules',
            'model_type' => 'transaction_classifier',
            'version' => 'txn-rules-v1',
            'artifact_uri' => null,
            'evaluation_metrics' => ['approach' => 'keyword + status rules'],
            'training_data_description' => null,
            'trained_at' => $this->now->copy()->subDays(60),
            'is_active' => true,
        ]);

        return ModelVersion::where('model_type', 'revenue_regressor')->first();
    }

    private function seedDecisionPolicies(): DecisionPolicy
    {
        return DecisionPolicy::create([
            'policy_name' => 'era-policy-v1',
            'version' => 'era-policy-v1',
            'scoring_weights' => [
                'revenue_consistency' => 0.20,
                'revenue_volatility' => 0.20,
                'transaction_frequency' => 0.15,
                'active_days' => 0.10,
                'revenue_trend' => 0.15,
                'transaction_concentration' => 0.10,
                'repayment_history' => 0.10,
            ],
            'scoring_bands' => [
                ['min_score' => 70, 'rate_bps' => 3500],
                ['min_score' => 50, 'rate_bps' => 3000],
                ['min_score' => 30, 'rate_bps' => 2000],
            ],
            'advance_rules' => [
                'rate_bands' => [
                    ['min_score' => 70, 'rate_bps' => 3500],
                    ['min_score' => 50, 'rate_bps' => 3000],
                    ['min_score' => 30, 'rate_bps' => 2000],
                ],
                'min_advance_minor' => 100000,
                'max_advance_minor' => 10000000,
                'max_exposure_minor' => 10000000,
                'cold_start' => ['rate_bps' => 1000, 'amount_minor' => 500000],
            ],
            'repayment_rules' => [
                'base_recovery_bps' => 1000,
                'min_recovery_bps' => 800,
                'max_recovery_bps' => 2000,
                'max_days' => 30,
            ],
            'eligibility_rules' => ['minimum_history_days' => 90, 'minimum_transactions' => 30],
            'effective_from' => $this->now->copy()->subDays(120),
            'effective_until' => null,
            'status' => 'active',
        ]);
    }

    /**
     * @return array<int, FinancingPartner>
     */
    private function seedFinancingPartners(): array
    {
        return collect([
            ['name' => 'Demo Micro Finance Ltd', 'type' => 'financial_institution'],
            ['name' => 'Demo Credit Co', 'type' => 'financial_institution'],
            ['name' => 'Demo Bank Ltd', 'type' => 'bank'],
        ])->map(fn ($p, $i) => FinancingPartner::create([
            'name' => $p['name'],
            'partner_type' => $p['type'],
            'contact_email' => sprintf('partner%03d@example.test', $i + 1),
            'contact_phone' => sprintf('2547900001%02d', $i + 1),
            'status' => 'active',
            'integration_config' => ['sandbox' => true],
        ]))->all();
    }

    /**
     * @param  array<string, mixed>  $scenario
     * @param  array<int, User>  $users
     * @param  Collection<string, int>  $businessTypes
     */
    private function seedBusiness(array $scenario, array $users, Collection $businessTypes): Business
    {
        $b = $scenario['business'];
        $hasPhysicalLocation = $b['has_physical_location'] ?? true;

        $county = $hasPhysicalLocation
            ? County::query()->where('name', $b['county'])->first()
            : null;
        $subCounty = $county !== null
            ? SubCounty::query()
                ->where('county_id', $county->id)
                ->where('name', $b['town'])
                ->first()
            : null;
        $ward = $subCounty !== null && isset($b['ward'])
            ? Ward::query()
                ->where('sub_county_id', $subCounty->id)
                ->where('name', $b['ward'])
                ->first()
            : null;

        $business = Business::create([
            'business_name' => $b['name'],
            'business_type_id' => $businessTypes->get(
                BusinessTypeSeeder::slugForLegacy($b['category'] ?? null, $b['type'] ?? null),
            ),
            'registration_number' => sprintf('REG-%06d', $scenario['seq']),
            'phone_number' => sprintf('2547901%05d', $scenario['seq']),
            'email' => sprintf('acme%03d@example.test', $scenario['seq']),
            'county' => $hasPhysicalLocation ? $county?->name : null,
            'town' => $hasPhysicalLocation ? $subCounty?->name : null,
            'has_physical_location' => $hasPhysicalLocation,
            'county_id' => $county?->id,
            'sub_county_id' => $subCounty?->id,
            'ward_id' => $ward?->id,
            'physical_address' => $hasPhysicalLocation && $county !== null && $subCounty !== null
                ? "{$subCounty->name}, {$county->name}, Kenya"
                : null,
            'operating_since' => $this->now->copy()->subDays($b['operating_since_days'])->toDateString(),
            'status' => $b['status'],
        ]);

        $owner = $users[$scenario['seq'] % 2];
        $business->businessUsers()->create([
            'user_id' => $owner->id,
            'role' => 'owner',
            'is_primary_contact' => true,
            'status' => 'active',
        ]);

        if (($scenario['seq'] % 3) === 0) {
            $business->businessUsers()->create([
                'user_id' => $users[2]->id,
                'role' => 'staff',
                'is_primary_contact' => false,
                'status' => 'active',
            ]);
        }

        // Payment accounts: a Till for most, a Paybill for some.
        $business->paymentAccounts()->create([
            'provider' => 'Safaricom',
            'channel_type' => 'till',
            'account_reference' => sprintf('QC%04d', $scenario['seq']),
            'account_name' => $b['name'],
            'status' => 'active',
            'verified_at' => $this->now->copy()->subDays(30),
            'metadata' => ['synthetic' => true],
        ]);

        if (in_array($scenario['seq'], [2, 8, 13], true)) {
            $business->paymentAccounts()->create([
                'provider' => 'Safaricom',
                'channel_type' => 'paybill',
                'account_reference' => sprintf('PB%04d', $scenario['seq']),
                'account_name' => $b['name'],
                'status' => 'active',
                'verified_at' => $this->now->copy()->subDays(30),
                'metadata' => ['synthetic' => true],
            ]);
        }

        return $business;
    }

    /**
     * @return Collection<int, Transaction>
     */
    private function seedTransactions(Business $business, array $scenario): Collection
    {
        $scenario['payment_account_id'] = $business->paymentAccounts()->value('id');

        $rows = $this->generateTransactionRows($business, $scenario);

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('transactions')->insert($chunk);
        }

        return Transaction::where('business_id', $business->id)->orderBy('id')->get();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function generateTransactionRows(Business $business, array $scenario): array
    {
        $rows = [];
        $slug = $scenario['slug'];
        $historyDays = $scenario['history_days'];
        $start = $this->now->copy()->subDays($historyDays);

        for ($d = 0; $d < $historyDays; $d++) {
            $date = $start->copy()->addDays($d);

            foreach ($this->salesForDay($scenario, $d, $historyDays) as $txIndex => [$amountMinor, $type]) {
                $rows[] = $this->transactionRow(
                    $business,
                    $scenario,
                    $slug,
                    $d,
                    $txIndex,
                    $date,
                    $amountMinor,
                    $type,
                    'inflow',
                    'completed',
                );
            }
        }

        $this->appendExtras($rows, $business, $scenario);
        $this->applyUnknownTypes($rows, $scenario);

        return $rows;
    }

    /**
     * For the manual-review scenario, blank out a fraction of inflow sale
     * transaction types so the classifier marks them "unknown".
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function applyUnknownTypes(array &$rows, array $scenario): void
    {
        $fraction = $scenario['extras']['unknown_fraction'];
        if ($fraction <= 0) {
            return;
        }

        $inflowSaleIndex = 0;
        foreach ($rows as &$row) {
            if ($row['direction'] === 'inflow' && in_array($row['transaction_type'], self::SALE_TYPES, true)) {
                if (($inflowSaleIndex % 10) < ($fraction * 10)) {
                    $row['transaction_type'] = null;
                }
                $inflowSaleIndex++;
            }
        }
        unset($row);
    }

    /**
     * @return array<int, array{0: int, 1: string}>
     */
    private function salesForDay(array $scenario, int $dayIndex, int $totalDays): array
    {
        $pattern = $scenario['pattern'];

        if ($pattern === 'intermittent' && ($dayIndex % 3) !== 0) {
            return [];
        }

        $min = $scenario['amount_minor_min'];
        $max = $scenario['amount_minor_max'];
        $txMin = $scenario['tx_min'];
        $txMax = $scenario['tx_max'];

        $multiplier = 1.0;
        if ($pattern === 'growing') {
            $multiplier = 0.5 + 0.5 * ($dayIndex / max(1, $totalDays));
        } elseif ($pattern === 'declining') {
            $multiplier = 1.3 - 1.0 * ($dayIndex / max(1, $totalDays));
        } elseif ($pattern === 'weekly') {
            $multiplier = in_array($this->now->copy()->subDays($totalDays - $dayIndex)->dayOfWeek, [5, 6, 0], true) ? 1.5 : 0.8;
        } elseif ($pattern === 'monthly') {
            $multiplier = $this->now->copy()->subDays($totalDays - $dayIndex)->day <= 7 ? 1.6 : 0.85;
        } elseif ($pattern === 'volatile') {
            $multiplier = fake()->randomFloat(2, 0.3, 2.2);
        }

        $count = fake()->numberBetween($txMin, $txMax);

        $sales = [];
        for ($i = 0; $i < $count; $i++) {
            $amountMinor = (int) round(fake()->numberBetween($min, $max) * $multiplier);
            $amountMinor = max(100, $amountMinor);
            $sales[] = [$amountMinor, fake()->randomElement(self::SALE_TYPES)];
        }

        return $sales;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function appendExtras(array &$rows, Business $business, array $scenario): void
    {
        $extras = $scenario['extras'];
        $slug = $scenario['slug'];
        $historyDays = $scenario['history_days'];
        $start = $this->now->copy()->subDays($historyDays);

        $offset = 5000;
        $idx = $offset;

        foreach (['refunds' => ['customer_refund', 'outflow'], 'capital_injections' => ['owner_capital_injection', 'inflow'], 'internal_transfers' => ['internal_transfer', 'inflow'], 'reversals' => ['reversal', 'outflow']] as $key => [$type, $direction]) {
            for ($i = 0; $i < $extras[$key]; $i++) {
                $day = ($i * 7 + 3) % $historyDays;
                $date = $start->copy()->addDays($day);
                $amountMinor = fake()->numberBetween($scenario['amount_minor_min'], $scenario['amount_minor_max']);
                $rows[] = $this->transactionRow(
                    $business,
                    $scenario,
                    $slug,
                    $day,
                    $idx++,
                    $date,
                    $amountMinor,
                    $type,
                    $direction,
                    'completed',
                );
            }
        }

        // Deliberate large sales (anomaly) for scenario I.
        for ($i = 0; $i < $extras['large_sales']; $i++) {
            $day = ($i * 17 + 5) % $historyDays;
            $date = $start->copy()->addDays($day);
            $amountMinor = fake()->numberBetween(5000000, 30000000);
            $rows[] = $this->transactionRow(
                $business,
                $scenario,
                $slug,
                $day,
                $idx++,
                $date,
                $amountMinor,
                'till_payment_received',
                'inflow',
                'completed',
            );
        }

        // Transactions whose provider id is missing/unresolved (scenario I anomaly).
        for ($i = 0; $i < $extras['missing_ids']; $i++) {
            $day = ($i * 23 + 11) % $historyDays;
            $date = $start->copy()->addDays($day);
            $amountMinor = fake()->numberBetween($scenario['amount_minor_min'], $scenario['amount_minor_max']);
            $row = $this->transactionRow(
                $business,
                $scenario,
                $slug,
                $day,
                $idx++,
                $date,
                $amountMinor,
                'till_payment_received',
                'inflow',
                'completed',
            );
            $row['provider_transaction_id'] = sprintf('MISSING-%s-%04d', $slug, $i);
            $rows[] = $row;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function transactionRow(
        Business $business,
        array $scenario,
        string $slug,
        int $dayIndex,
        int $txIndex,
        Carbon $date,
        int $amountMinor,
        ?string $type,
        string $direction,
        string $status,
    ): array {
        $providerId = sprintf('%s-%04d-%03d', $slug, $dayIndex, $txIndex);

        return [
            'business_id' => $business->id,
            'payment_account_id' => $scenario['payment_account_id'],
            'provider' => 'Safaricom',
            'provider_transaction_id' => $providerId,
            'transaction_type' => $type,
            'direction' => $direction,
            'amount_minor' => $amountMinor,
            'currency' => 'KES',
            'transaction_time' => $date->copy()->setTime(9, 0)->addMinutes(($txIndex % 500) * 7)->toDateTimeString(),
            'provider_status' => $status,
            'counterparty_reference' => sprintf('REF-%s-%04d%03d', $slug, $dayIndex, $txIndex),
            'counterparty_name' => fake()->optional(0.3)->name(),
            'raw_payload' => json_encode([
                'provider' => 'Safaricom',
                'receipt' => sprintf('RCP-%s-%04d-%03d', $slug, $dayIndex, $txIndex),
                'synthetic' => true,
            ]),
            'ingested_at' => $this->now->toDateTimeString(),
            'created_at' => $this->now->toDateTimeString(),
            'updated_at' => $this->now->toDateTimeString(),
        ];
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     */
    private function seedClassifications(Business $business, Collection $transactions, array $scenario): void
    {
        $rows = [];
        foreach ($transactions as $transaction) {
            $type = strtolower((string) $transaction->transaction_type);

            [$category, $revenueEligible, $anomaly] = $this->classify($type, $transaction->direction->value, $transaction->provider_status, $transaction->amount_minor, $transaction->provider_transaction_id);

            $confidence = $category === 'unknown' ? '0.10000' : '0.98000';

            $rows[] = [
                'transaction_id' => $transaction->id,
                'classifier_version' => 'txn-rules-v1',
                'category' => $category,
                'revenue_eligible' => $revenueEligible,
                'anomaly_status' => $anomaly,
                'confidence' => $confidence,
                'reason_codes' => json_encode($category === 'unknown'
                    ? ['reason' => 'insufficient_information']
                    : ['matched' => $type ?: null]),
                'is_current' => true,
                'classified_at' => $this->now->toDateTimeString(),
                'created_at' => $this->now->toDateTimeString(),
                'updated_at' => $this->now->toDateTimeString(),
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('transaction_classifications')->insert($chunk);
        }
    }

    /**
     * @return array{0: string, 1: bool, 2: string}
     */
    private function classify(string $type, string $direction, ?string $status, int $amountMinor, ?string $providerId): array
    {
        $status = strtolower((string) $status);
        $failed = in_array($status, ['reversed', 'failed', 'declined', 'cancelled', 'void'], true);
        $pending = in_array($status, ['pending', 'processing', 'held'], true);

        $category = 'unknown';

        if ($failed) {
            $category = 'reversal';
        } elseif (str_contains($type, 'refund')) {
            $category = 'refund';
        } elseif (str_contains($type, 'reversal') || str_contains($type, 'reversed')) {
            $category = 'reversal';
        } elseif (str_contains($type, 'capital') || str_contains($type, 'contribution')) {
            $category = 'capital_injection';
        } elseif (str_contains($type, 'transfer')) {
            $category = 'internal_transfer';
        } elseif (in_array($type, self::SALE_TYPES, true)) {
            $category = 'sale';
        }

        $revenueEligible = $direction === 'inflow' && $category === 'sale' && ! $failed && ! $pending;

        $anomaly = 'normal';
        if ($amountMinor >= self::LARGE_SALE_MINOR || str_starts_with((string) $providerId, 'MISSING-')) {
            $anomaly = 'flagged';
        }

        return [$category, $revenueEligible, $anomaly];
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     * @param  array<int, FinancingPartner>  $partners
     */
    private function seedLifecycle(
        Business $business,
        array $scenario,
        Collection $transactions,
        array $partners,
        ModelVersion $modelVersion,
        DecisionPolicy $policy,
    ): void {
        $lifecycle = $scenario['lifecycle'];

        if ($lifecycle === 'none') {
            return;
        }

        $assessment = $this->seedAssessment($business, $scenario, $transactions, $modelVersion, $policy);

        if (in_array($lifecycle, ['eligible_settled', 'eligible_active', 'eligible_disbursing', 'eligible_overdue', 'eligible_defaulted', 'eligible_non_revenue', 'ineligible_exposure'], true)) {
            $this->seedAdvanceFlow($business, $scenario, $assessment, $partners, $lifecycle);
        }

        if (($scenario['extra_offer'] ?? null) !== null) {
            $this->seedOffer($assessment, OfferStatus::from($scenario['extra_offer']), $scenario);
        }
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     */
    private function seedAssessment(
        Business $business,
        array $scenario,
        Collection $transactions,
        ModelVersion $modelVersion,
        DecisionPolicy $policy,
    ): EraAssessment {
        $spec = $scenario['assessment'];
        $eligibleRevenue30d = $this->eligibleRevenue30d($transactions);

        $predictedMinor = $spec['predicted_revenue_minor'] ?? $eligibleRevenue30d;

        $advanceRateBps = round($spec['advance_rate'] * 10000);
        $recommendedMinor = $spec['recommended_minor']
            ?? ($predictedMinor > 0
                ? (int) min(10000000, max(100000, round($predictedMinor * $spec['advance_rate'])))
                : 0);

        return EraAssessment::create([
            'business_id' => $business->id,
            'model_version_id' => $spec['status'] === 'eligible' ? $modelVersion->id : null,
            'decision_policy_id' => $policy->id,
            'requested_by_user_id' => null,
            'assessment_status' => AssessmentStatus::from($spec['status']),
            'assessment_date' => $this->now->copy()->subDays(1),
            'forecast_period_days' => 30,
            'predicted_revenue_minor' => $spec['status'] === 'insufficient_data' ? null : $predictedMinor,
            'reliability_score' => $spec['reliability'],
            'recommended_advance_rate_bps' => $spec['status'] === 'eligible' ? $advanceRateBps : 0,
            'recommended_advance_amount_minor' => $spec['status'] === 'eligible' ? $recommendedMinor : 0,
            'recommended_recovery_rate_bps' => $spec['recovery_rate_bps'],
            'maximum_repayment_days' => 30,
            'score_breakdown' => [],
            'reason_codes' => $spec['reason_codes'],
            'warnings' => $spec['warnings'],
            'input_snapshot' => ['synthetic' => true, 'business_id' => $business->public_id],
            'output_snapshot' => [
                'synthetic' => true,
                'assessment_status' => $spec['status'],
                'decision_policy_version' => 'era-policy-v1',
                'currency' => 'KES',
            ],
        ]);
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     */
    private function eligibleRevenue30d(Collection $transactions): int
    {
        $cutoff = $this->now->copy()->subDays(30);

        return (int) $transactions
            ->filter(fn ($t) => $t->direction->value === 'inflow'
                && in_array($t->transaction_type, self::SALE_TYPES, true)
                && $t->provider_status === 'completed'
                && $t->transaction_time >= $cutoff)
            ->sum('amount_minor');
    }

    private function seedOffer(EraAssessment $assessment, OfferStatus $status, array $scenario): AdvanceOffer
    {
        $expiresAt = $status === OfferStatus::Pending
            ? $this->now->copy()->addDays(14)
            : $this->now->copy()->subDays(7);

        return AdvanceOffer::create([
            'assessment_id' => $assessment->id,
            'business_id' => $assessment->business_id,
            'offered_amount_minor' => $assessment->recommended_advance_amount_minor ?: 500000,
            'fee_amount_minor' => 0,
            'total_repayable_minor' => $assessment->recommended_advance_amount_minor ?: 500000,
            'recovery_rate_bps' => $assessment->recommended_recovery_rate_bps,
            'maximum_repayment_days' => $assessment->maximum_repayment_days,
            'offer_terms' => ['synthetic' => true, 'source' => 'seed'],
            'status' => $status,
            'offered_at' => $this->now->copy()->subDays(14),
            'expires_at' => $expiresAt,
            'accepted_at' => $status === OfferStatus::Accepted ? $this->now->copy()->subDays(10) : null,
            'rejected_at' => $status === OfferStatus::Rejected ? $this->now->copy()->subDays(10) : null,
        ]);
    }

    /**
     * @param  array<int, FinancingPartner>  $partners
     */
    private function seedAdvanceFlow(
        Business $business,
        array $scenario,
        EraAssessment $assessment,
        array $partners,
        string $lifecycle,
    ): void {
        $spec = $scenario['advance'];

        $principalMinor = $spec['principal_minor'] ?? $assessment->recommended_advance_amount_minor;
        $feeMinor = $spec['fee_minor'] ?? (int) round($principalMinor * 0.05);
        $totalRepayable = $principalMinor + $feeMinor;

        $offer = AdvanceOffer::create([
            'assessment_id' => $assessment->id,
            'business_id' => $business->id,
            'offered_amount_minor' => $principalMinor,
            'fee_amount_minor' => $feeMinor,
            'total_repayable_minor' => $totalRepayable,
            'recovery_rate_bps' => $assessment->recommended_recovery_rate_bps,
            'maximum_repayment_days' => 30,
            'offer_terms' => ['synthetic' => true, 'source' => 'seed'],
            'status' => OfferStatus::Accepted,
            'offered_at' => $this->now->copy()->subDays($spec['days_ago']),
            'expires_at' => $this->now->copy()->subDays($spec['days_ago'] - 14),
            'accepted_at' => $this->now->copy()->subDays($spec['days_ago'] - 2),
            'rejected_at' => null,
        ]);

        $advance = Advance::create([
            'offer_id' => $offer->id,
            'business_id' => $business->id,
            'financing_partner_id' => $partners[$scenario['seq'] % count($partners)]->id,
            'principal_amount_minor' => $principalMinor,
            'fee_amount_minor' => $feeMinor,
            'total_repayable_minor' => $totalRepayable,
            'amount_repaid_minor' => 0,
            'outstanding_balance_minor' => $totalRepayable,
            'recovery_rate_bps' => $assessment->recommended_recovery_rate_bps,
            'maximum_repayment_days' => 30,
            'approved_at' => $this->now->copy()->subDays($spec['days_ago'] - 1),
            'disbursed_at' => $this->now->copy()->subDays($spec['days_ago'] - 1),
            'repayment_started_at' => $this->now->copy()->subDays($spec['days_ago'] - 1),
            'due_at' => $this->now->copy()->subDays($spec['days_ago'] - 1)->addDays(30),
            'settled_at' => null,
            'status' => $spec['state'],
        ]);

        // Initial repayment terms.
        $advance->repaymentTerms()->create([
            'recovery_rate_bps' => $assessment->recommended_recovery_rate_bps,
            'maximum_repayment_days' => 30,
            'fallback_terms' => ['arrangement' => 'installment'],
            'effective_from' => $this->now->copy()->subDays($spec['days_ago'] - 2),
            'adjustment_reason' => 'Original accepted terms',
            'policy_version' => 'era-policy-v1',
            'changed_by_user_id' => null,
        ]);

        $disbursementStatus = $lifecycle === 'eligible_disbursing' ? DisbursementStatus::Processing : DisbursementStatus::Successful;
        $disbursement = Disbursement::create([
            'advance_id' => $advance->id,
            'provider' => 'Safaricom',
            'provider_reference' => sprintf('B2C-%s-%04d', $scenario['slug'], $scenario['seq']),
            'amount_minor' => $principalMinor,
            'status' => $disbursementStatus,
            'requested_at' => $this->now->copy()->subDays($spec['days_ago'] - 1),
            'completed_at' => $disbursementStatus === DisbursementStatus::Successful ? $this->now->copy()->subDays($spec['days_ago'] - 1) : null,
            'failure_reason' => null,
            'provider_response' => ['synthetic' => true],
        ]);

        if ($disbursementStatus === DisbursementStatus::Successful) {
            $this->ledger($advance, 'principal_disbursed', $principalMinor, disbursementId: $disbursement->id);
            if ($feeMinor > 0) {
                $this->ledger($advance, 'fee_assessed', $feeMinor);
            }
        }

        if (in_array($lifecycle, ['eligible_settled', 'eligible_active', 'eligible_overdue', 'eligible_defaulted', 'eligible_non_revenue', 'ineligible_exposure'], true)) {
            $this->seedRepayments($advance, $scenario, $spec);
        }
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    private function seedRepayments(Advance $advance, array $scenario, array $spec): void
    {
        $fraction = $spec['repaid_fraction'];
        if ($fraction <= 0) {
            return;
        }

        $totalRepayable = $advance->total_repayable_minor;
        $target = (int) round($totalRepayable * $fraction);
        if ($target <= 0) {
            return;
        }

        $chunks = 5;
        $remaining = $target;
        $repaid = 0;
        $start = $this->now->copy()->subDays($spec['days_ago'] - 2);

        for ($i = 1; $i <= $chunks && $remaining > 0; $i++) {
            $amount = $i === $chunks ? $remaining : (int) round($target / $chunks);
            if ($amount <= 0) {
                break;
            }
            $remaining -= $amount;

            $method = $i === 1 ? CollectionMethod::ManualPayment : CollectionMethod::RevenueDeduction;

            $collection = RepaymentCollection::create([
                'business_id' => $advance->business_id,
                'source_transaction_id' => null,
                'collection_method' => $method,
                'provider' => 'Safaricom',
                'provider_reference' => sprintf('COLL-%s-%d', $scenario['slug'], $i),
                'amount_minor' => $amount,
                'currency' => 'KES',
                'status' => CollectionStatus::Successful,
                'idempotency_key' => sprintf('seed-ik-%s-%d', $scenario['slug'], $i),
                'collected_at' => $start->copy()->addDays($i * 3),
                'processed_at' => $start->copy()->addDays($i * 3),
                'provider_response' => ['synthetic' => true],
            ]);

            $allocation = $collection->allocations()->create([
                'advance_id' => $advance->id,
                'amount_minor' => $amount,
            ]);

            $this->ledger($advance, 'repayment', -$amount, allocationId: $allocation->id);
            $repaid += $amount;
        }

        $outstanding = $totalRepayable - $repaid;
        $updates = [
            'amount_repaid_minor' => $repaid,
            'outstanding_balance_minor' => max(0, $outstanding),
        ];

        if ($fraction >= 1.0) {
            $updates['status'] = AdvanceStatus::Settled;
            $updates['settled_at'] = $this->now->copy()->subDays(2);
        }

        $advance->update($updates);

        // A rule-governed terms adjustment example (volatility raised recovery 10% -> 12%).
        if ($spec['adjust_terms'] ?? false) {
            $advance->repaymentTerms()->whereNull('effective_until')->update(['effective_until' => $this->now->copy()->subDays(5)]);
            $advance->repaymentTerms()->create([
                'recovery_rate_bps' => 1200,
                'maximum_repayment_days' => 30,
                'fallback_terms' => ['arrangement' => 'installment'],
                'effective_from' => $this->now->copy()->subDays(5),
                'adjustment_reason' => 'Volatility raised recovery from 10% to 12%',
                'policy_version' => 'era-policy-v1',
                'changed_by_user_id' => null,
            ]);
        }
    }

    private function ledger(Advance $advance, string $type, int $signedMinor, ?int $allocationId = null, ?int $disbursementId = null): void
    {
        AdvanceLedgerEntry::create([
            'advance_id' => $advance->id,
            'repayment_allocation_id' => $allocationId,
            'disbursement_id' => $disbursementId,
            'entry_type' => $type,
            'amount_minor' => $signedMinor,
            'description' => null,
            'reference' => null,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function scenarioDefinitions(): array
    {
        return [
            [
                'seq' => 1,
                'slug' => 'A_STABLE',
                'lifecycle' => 'eligible_settled',
                'pattern' => 'stable',
                'history_days' => 180,
                'amount_minor_min' => 10000,
                'amount_minor_max' => 200000,
                'tx_min' => 4,
                'tx_max' => 7,
                'extras' => ['refunds' => 0, 'capital_injections' => 0, 'internal_transfers' => 0, 'reversals' => 0, 'large_sales' => 0, 'missing_ids' => 0, 'unknown_fraction' => 0],
                'business' => ['name' => 'Acme Barbershop 001', 'type' => 'retail', 'category' => 'barbershop', 'county' => 'Nairobi', 'town' => 'Westlands', 'operating_since_days' => 900, 'status' => 'active'],
                'assessment' => ['status' => 'eligible', 'reliability' => '85.0000', 'advance_rate' => 0.35, 'recovery_rate_bps' => 1000, 'reason_codes' => ['CONSISTENT_REVENUE', 'ACCEPTABLE_VOLATILITY', 'HEALTHY_TRANSACTION_FREQUENCY', 'DIVERSIFIED_REVENUE'], 'warnings' => []],
                'advance' => ['state' => 'settled', 'days_ago' => 45, 'repaid_fraction' => 1.0, 'adjust_terms' => false],
            ],
            [
                'seq' => 2,
                'slug' => 'B_VOLATILE',
                'lifecycle' => 'eligible_active',
                'pattern' => 'volatile',
                'history_days' => 150,
                'amount_minor_min' => 10000,
                'amount_minor_max' => 500000,
                'tx_min' => 3,
                'tx_max' => 9,
                'extras' => ['refunds' => 3, 'capital_injections' => 0, 'internal_transfers' => 0, 'reversals' => 0, 'large_sales' => 0, 'missing_ids' => 0, 'unknown_fraction' => 0],
                'business' => ['name' => 'Acme Retail 002', 'type' => 'retail', 'category' => 'general_store', 'county' => 'Mombasa', 'town' => 'Mvita', 'operating_since_days' => 700, 'status' => 'active'],
                'assessment' => ['status' => 'eligible', 'reliability' => '58.0000', 'advance_rate' => 0.30, 'recovery_rate_bps' => 1200, 'reason_codes' => ['SUFFICIENT_TRANSACTION_HISTORY', 'HIGH_VOLATILITY'], 'warnings' => ['Volatile revenue']],
                'advance' => ['state' => 'active', 'days_ago' => 25, 'repaid_fraction' => 0.4, 'adjust_terms' => true],
            ],
            [
                'seq' => 3,
                'slug' => 'C_GROWING',
                'lifecycle' => 'eligible_disbursing',
                'pattern' => 'growing',
                'history_days' => 180,
                'amount_minor_min' => 10000,
                'amount_minor_max' => 250000,
                'tx_min' => 3,
                'tx_max' => 8,
                'extras' => ['refunds' => 0, 'capital_injections' => 0, 'internal_transfers' => 0, 'reversals' => 0, 'large_sales' => 0, 'missing_ids' => 0, 'unknown_fraction' => 0],
                'business' => ['name' => 'Acme Groceries 003', 'type' => 'retail', 'category' => 'groceries', 'county' => 'Kisumu', 'town' => 'Kisumu Central', 'operating_since_days' => 400, 'status' => 'active'],
                'assessment' => ['status' => 'eligible', 'reliability' => '72.0000', 'advance_rate' => 0.30, 'recovery_rate_bps' => 1000, 'reason_codes' => ['SUFFICIENT_TRANSACTION_HISTORY', 'POSITIVE_TREND'], 'warnings' => []],
                'advance' => ['state' => 'disbursing', 'days_ago' => 3, 'repaid_fraction' => 0, 'adjust_terms' => false],
            ],
            [
                'seq' => 4,
                'slug' => 'D_DECLINING',
                'lifecycle' => 'ineligible',
                'pattern' => 'declining',
                'history_days' => 180,
                'amount_minor_min' => 10000,
                'amount_minor_max' => 200000,
                'tx_min' => 2,
                'tx_max' => 6,
                'extras' => ['refunds' => 0, 'capital_injections' => 0, 'internal_transfers' => 0, 'reversals' => 0, 'large_sales' => 0, 'missing_ids' => 0, 'unknown_fraction' => 0],
                'business' => ['name' => 'Acme Hardware 004', 'type' => 'retail', 'category' => 'hardware', 'county' => 'Nakuru', 'town' => 'Nakuru East', 'operating_since_days' => 1000, 'status' => 'active'],
                'assessment' => ['status' => 'ineligible', 'reliability' => '28.0000', 'advance_rate' => 0.0, 'recovery_rate_bps' => 1000, 'reason_codes' => ['LOW_RELIABILITY_SCORE', 'DECLINING_REVENUE'], 'warnings' => ['Sustained revenue decline']],
                'advance' => null,
            ],
            [
                'seq' => 5,
                'slug' => 'E_WEEKLY',
                'lifecycle' => 'eligible_overdue',
                'pattern' => 'weekly',
                'history_days' => 150,
                'amount_minor_min' => 10000,
                'amount_minor_max' => 150000,
                'tx_min' => 3,
                'tx_max' => 8,
                'extras' => ['refunds' => 0, 'capital_injections' => 0, 'internal_transfers' => 0, 'reversals' => 0, 'large_sales' => 0, 'missing_ids' => 0, 'unknown_fraction' => 0],
                'business' => ['name' => 'Acme Boutique 005', 'type' => 'retail', 'category' => 'fashion', 'county' => 'Uasin Gishu', 'town' => 'Kapseret', 'operating_since_days' => 600, 'status' => 'active'],
                'assessment' => ['status' => 'eligible', 'reliability' => '64.0000', 'advance_rate' => 0.30, 'recovery_rate_bps' => 1000, 'reason_codes' => ['SUFFICIENT_TRANSACTION_HISTORY', 'WEEKLY_PATTERN'], 'warnings' => []],
                'advance' => ['state' => 'overdue', 'days_ago' => 45, 'repaid_fraction' => 0.3, 'adjust_terms' => false],
            ],
            [
                'seq' => 6,
                'slug' => 'F_MONTHLY',
                'lifecycle' => 'eligible_defaulted',
                'pattern' => 'monthly',
                'history_days' => 180,
                'amount_minor_min' => 10000,
                'amount_minor_max' => 300000,
                'tx_min' => 3,
                'tx_max' => 8,
                'extras' => ['refunds' => 0, 'capital_injections' => 0, 'internal_transfers' => 0, 'reversals' => 0, 'large_sales' => 0, 'missing_ids' => 0, 'unknown_fraction' => 0],
                'business' => ['name' => 'Acme Agrovet 006', 'type' => 'wholesale', 'category' => 'agrovet', 'county' => 'Kiambu', 'town' => 'Thika Town', 'operating_since_days' => 800, 'status' => 'active'],
                'assessment' => ['status' => 'eligible', 'reliability' => '61.0000', 'advance_rate' => 0.30, 'recovery_rate_bps' => 1000, 'reason_codes' => ['SUFFICIENT_TRANSACTION_HISTORY', 'MONTHLY_PATTERN'], 'warnings' => []],
                'advance' => ['state' => 'defaulted', 'days_ago' => 60, 'repaid_fraction' => 0.15, 'adjust_terms' => false],
            ],
            [
                'seq' => 7,
                'slug' => 'G_INTERMITTENT',
                'lifecycle' => 'ineligible',
                'pattern' => 'intermittent',
                'history_days' => 150,
                'amount_minor_min' => 10000,
                'amount_minor_max' => 400000,
                'tx_min' => 1,
                'tx_max' => 5,
                'extras' => ['refunds' => 0, 'capital_injections' => 0, 'internal_transfers' => 0, 'reversals' => 0, 'large_sales' => 0, 'missing_ids' => 0, 'unknown_fraction' => 0],
                'business' => ['name' => 'Acme Kiosk 007', 'type' => 'retail', 'category' => 'kiosk', 'county' => 'Kericho', 'town' => 'Ainamoi', 'operating_since_days' => 500, 'status' => 'active'],
                'assessment' => ['status' => 'ineligible', 'reliability' => '35.0000', 'advance_rate' => 0.0, 'recovery_rate_bps' => 1000, 'reason_codes' => ['LOW_RELIABILITY_SCORE', 'IRREGULAR_REVENUE'], 'warnings' => ['Many zero-revenue days']],
                'advance' => null,
            ],
            [
                'seq' => 8,
                'slug' => 'H_NONREV',
                'lifecycle' => 'eligible_non_revenue',
                'pattern' => 'stable',
                'history_days' => 120,
                'amount_minor_min' => 10000,
                'amount_minor_max' => 200000,
                'tx_min' => 3,
                'tx_max' => 6,
                'extras' => ['refunds' => 4, 'capital_injections' => 6, 'internal_transfers' => 5, 'reversals' => 3, 'large_sales' => 0, 'missing_ids' => 0, 'unknown_fraction' => 0],
                'business' => ['name' => 'Acme Funding 008', 'type' => 'retail', 'category' => 'general_store', 'county' => 'Nairobi', 'town' => 'Kasarani', 'operating_since_days' => 500, 'status' => 'active'],
                'assessment' => ['status' => 'eligible', 'reliability' => '68.0000', 'advance_rate' => 0.30, 'recovery_rate_bps' => 1000, 'reason_codes' => ['SUFFICIENT_TRANSACTION_HISTORY'], 'warnings' => []],
                'advance' => ['state' => 'active', 'days_ago' => 20, 'repaid_fraction' => 0.5, 'adjust_terms' => false],
            ],
            [
                'seq' => 9,
                'slug' => 'I_ANOMALY',
                'lifecycle' => 'manual_review',
                'pattern' => 'stable',
                'history_days' => 120,
                'amount_minor_min' => 10000,
                'amount_minor_max' => 300000,
                'tx_min' => 3,
                'tx_max' => 7,
                'extras' => ['refunds' => 0, 'capital_injections' => 0, 'internal_transfers' => 0, 'reversals' => 0, 'large_sales' => 3, 'missing_ids' => 2, 'unknown_fraction' => 0],
                'business' => ['name' => 'Acme Electronics 009', 'type' => 'retail', 'category' => 'electronics', 'county' => 'Mombasa', 'town' => 'Nyali', 'has_physical_location' => false, 'operating_since_days' => 600, 'status' => 'active'],
                'assessment' => ['status' => 'manual_review', 'reliability' => '55.0000', 'advance_rate' => 0.0, 'recovery_rate_bps' => 1000, 'reason_codes' => ['ANOMALOUS_TRANSACTIONS'], 'warnings' => ['Large sales and duplicate provider ids flagged for review']],
                'advance' => null,
            ],
            [
                'seq' => 10,
                'slug' => 'J_UNKNOWN',
                'lifecycle' => 'manual_review',
                'pattern' => 'stable',
                'history_days' => 120,
                'amount_minor_min' => 10000,
                'amount_minor_max' => 150000,
                'tx_min' => 4,
                'tx_max' => 8,
                'extras' => ['refunds' => 0, 'capital_injections' => 0, 'internal_transfers' => 0, 'reversals' => 0, 'large_sales' => 0, 'missing_ids' => 0, 'unknown_fraction' => 0.6],
                'business' => ['name' => 'Acme UnknownType 010', 'type' => 'retail', 'category' => 'misc', 'county' => 'Nakuru', 'town' => 'Naivasha', 'operating_since_days' => 400, 'status' => 'active'],
                'assessment' => ['status' => 'manual_review', 'reliability' => '50.0000', 'advance_rate' => 0.0, 'recovery_rate_bps' => 1000, 'reason_codes' => ['HIGH_UNCLASSIFIED_RATE'], 'warnings' => ['> 50% transactions unclassified']],
                'advance' => null,
            ],
            [
                'seq' => 11,
                'slug' => 'COLD_START',
                'lifecycle' => 'eligible_cold_start',
                'extra_offer' => 'pending',
                'pattern' => 'stable',
                'history_days' => 60,
                'amount_minor_min' => 10000,
                'amount_minor_max' => 120000,
                'tx_min' => 3,
                'tx_max' => 6,
                'extras' => ['refunds' => 0, 'capital_injections' => 0, 'internal_transfers' => 0, 'reversals' => 0, 'large_sales' => 0, 'missing_ids' => 0, 'unknown_fraction' => 0],
                'business' => ['name' => 'Acme ColdStart 011', 'type' => 'retail', 'category' => 'cafe', 'county' => 'Nairobi', 'town' => 'Langata', 'operating_since_days' => 75, 'status' => 'active'],
                'assessment' => ['status' => 'eligible', 'reliability' => '45.0000', 'advance_rate' => 0.10, 'recovery_rate_bps' => 1000, 'recommended_minor' => 500000, 'reason_codes' => ['COLD_START_CONSERVATIVE_ASSESSMENT'], 'warnings' => ['Conservative advance due to limited history']],
                'advance' => null,
            ],
            [
                'seq' => 12,
                'slug' => 'FRESH',
                'lifecycle' => 'insufficient_data',
                'pattern' => 'stable',
                'history_days' => 45,
                'amount_minor_min' => 10000,
                'amount_minor_max' => 100000,
                'tx_min' => 1,
                'tx_max' => 3,
                'extras' => ['refunds' => 0, 'capital_injections' => 0, 'internal_transfers' => 0, 'reversals' => 0, 'large_sales' => 0, 'missing_ids' => 0, 'unknown_fraction' => 0],
                'business' => ['name' => 'Acme Fresh 012', 'type' => null, 'category' => null, 'county' => 'Kisumu', 'town' => 'Nyando', 'operating_since_days' => 60, 'status' => 'active'],
                'assessment' => ['status' => 'insufficient_data', 'reliability' => '41.6700', 'advance_rate' => 0.0, 'recovery_rate_bps' => 1000, 'reason_codes' => ['LIMITED_HISTORY', 'INSUFFICIENT_HISTORY'], 'warnings' => ['Insufficient transaction history']],
                'advance' => null,
            ],
            [
                'seq' => 13,
                'slug' => 'EXPOSURE',
                'lifecycle' => 'ineligible_exposure',
                'pattern' => 'stable',
                'history_days' => 180,
                'amount_minor_min' => 10000,
                'amount_minor_max' => 200000,
                'tx_min' => 4,
                'tx_max' => 7,
                'extras' => ['refunds' => 0, 'capital_injections' => 0, 'internal_transfers' => 0, 'reversals' => 0, 'large_sales' => 0, 'missing_ids' => 0, 'unknown_fraction' => 0],
                'business' => ['name' => 'Acme Exposure 013', 'type' => 'retail', 'category' => 'supermarket', 'county' => 'Mombasa', 'town' => 'Changamwe', 'operating_since_days' => 900, 'status' => 'active'],
                'assessment' => ['status' => 'ineligible', 'reliability' => '70.0000', 'advance_rate' => 0.0, 'recovery_rate_bps' => 1000, 'reason_codes' => ['EXPOSURE_LIMIT_REACHED'], 'warnings' => ['Outstanding exposure at maximum']],
                'advance' => ['state' => 'active', 'days_ago' => 20, 'repaid_fraction' => 0.1, 'principal_minor' => 12000000, 'fee_minor' => 0, 'adjust_terms' => false],
            ],
            [
                'seq' => 14,
                'slug' => 'I_MICRO',
                'lifecycle' => 'eligible_active',
                'extra_offer' => 'rejected',
                'pattern' => 'micro_vendor',
                'history_days' => 150,
                'amount_minor_min' => 2000,
                'amount_minor_max' => 20000,
                'tx_min' => 8,
                'tx_max' => 15,
                'extras' => ['refunds' => 0, 'capital_injections' => 0, 'internal_transfers' => 0, 'reversals' => 0, 'large_sales' => 0, 'missing_ids' => 0, 'unknown_fraction' => 0],
                'business' => ['name' => 'Acme Mama Mboga 014', 'type' => 'service', 'category' => 'greengrocer', 'county' => 'Nairobi', 'town' => 'Embakasi Central', 'operating_since_days' => 600, 'status' => 'active'],
                'assessment' => ['status' => 'eligible', 'reliability' => '74.0000', 'advance_rate' => 0.30, 'recovery_rate_bps' => 1000, 'reason_codes' => ['HIGH_FREQUENCY', 'CONSISTENT_REVENUE'], 'warnings' => []],
                'advance' => ['state' => 'active', 'days_ago' => 18, 'repaid_fraction' => 0.6, 'adjust_terms' => false],
            ],
            [
                'seq' => 15,
                'slug' => 'J_MICROSRV',
                'lifecycle' => 'eligible_settled',
                'extra_offer' => 'expired',
                'pattern' => 'micro_service',
                'history_days' => 150,
                'amount_minor_min' => 50000,
                'amount_minor_max' => 1500000,
                'tx_min' => 1,
                'tx_max' => 3,
                'extras' => ['refunds' => 0, 'capital_injections' => 0, 'internal_transfers' => 0, 'reversals' => 0, 'large_sales' => 0, 'missing_ids' => 0, 'unknown_fraction' => 0],
                'business' => ['name' => 'Acme Boda Boda 015', 'type' => 'service', 'category' => 'transport', 'county' => 'Kisumu', 'town' => 'Kisumu Central', 'ward' => 'Kondele', 'operating_since_days' => 700, 'status' => 'active'],
                'assessment' => ['status' => 'eligible', 'reliability' => '66.0000', 'advance_rate' => 0.30, 'recovery_rate_bps' => 1000, 'reason_codes' => ['SUFFICIENT_TRANSACTION_HISTORY'], 'warnings' => []],
                'advance' => ['state' => 'settled', 'days_ago' => 50, 'repaid_fraction' => 1.0, 'adjust_terms' => false],
            ],
            [
                'seq' => 16,
                'slug' => 'PENDING',
                'lifecycle' => 'none',
                'pattern' => 'stable',
                'history_days' => 30,
                'amount_minor_min' => 10000,
                'amount_minor_max' => 100000,
                'tx_min' => 1,
                'tx_max' => 3,
                'extras' => ['refunds' => 0, 'capital_injections' => 0, 'internal_transfers' => 0, 'reversals' => 0, 'large_sales' => 0, 'missing_ids' => 0, 'unknown_fraction' => 0],
                'business' => ['name' => 'Acme Pending 016', 'type' => 'retail', 'category' => 'misc', 'county' => 'Nakuru', 'town' => 'Nakuru East', 'operating_since_days' => 45, 'status' => 'pending'],
                'assessment' => ['status' => 'eligible', 'reliability' => '50.0000', 'advance_rate' => 0.0, 'recovery_rate_bps' => 1000, 'reason_codes' => [], 'warnings' => []],
                'advance' => null,
            ],
            [
                'seq' => 17,
                'slug' => 'SUSPENDED',
                'lifecycle' => 'none',
                'pattern' => 'stable',
                'history_days' => 60,
                'amount_minor_min' => 10000,
                'amount_minor_max' => 150000,
                'tx_min' => 2,
                'tx_max' => 5,
                'extras' => ['refunds' => 0, 'capital_injections' => 0, 'internal_transfers' => 0, 'reversals' => 0, 'large_sales' => 0, 'missing_ids' => 0, 'unknown_fraction' => 0],
                'business' => ['name' => 'Acme Suspended 017', 'type' => 'retail', 'category' => 'misc', 'county' => 'Uasin Gishu', 'town' => 'Kapseret', 'operating_since_days' => 300, 'status' => 'suspended'],
                'assessment' => ['status' => 'eligible', 'reliability' => '50.0000', 'advance_rate' => 0.0, 'recovery_rate_bps' => 1000, 'reason_codes' => [], 'warnings' => []],
                'advance' => null,
            ],
        ];
    }
}
