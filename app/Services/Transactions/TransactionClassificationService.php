<?php

namespace App\Services\Transactions;

use App\Enums\AnomalyStatus;
use App\Enums\TransactionCategory;
use App\Models\Transaction;
use App\Models\TransactionClassification;
use App\Services\Repayments\RevenueRepaymentService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class TransactionClassificationService
{
    public function __construct(
        private readonly RevenueRepaymentService $revenueRepayments,
    ) {}

    /**
     * Apply a new classification to a transaction, superseding any previous
     * current classification. Prior records are retained as history.
     */
    public function classify(
        Transaction $transaction,
        string $classifierVersion,
        TransactionCategory $category,
        bool $revenueEligible = false,
        AnomalyStatus $anomalyStatus = AnomalyStatus::Normal,
        ?string $confidence = null,
        array $reasonCodes = [],
        ?Carbon $classifiedAt = null,
    ): TransactionClassification {
        return DB::transaction(function () use (
            $transaction,
            $classifierVersion,
            $category,
            $revenueEligible,
            $anomalyStatus,
            $confidence,
            $reasonCodes,
            $classifiedAt,
        ) {
            $transaction->classifications()
                ->where('is_current', true)
                ->update(['is_current' => false]);

            $classification = $transaction->classifications()->create([
                'classifier_version' => $classifierVersion,
                'category' => $category,
                'revenue_eligible' => $revenueEligible,
                'anomaly_status' => $anomalyStatus,
                'confidence' => $confidence,
                'reason_codes' => $reasonCodes,
                'is_current' => true,
                'classified_at' => $classifiedAt ?? now(),
            ]);

            if ($revenueEligible) {
                try {
                    $this->revenueRepayments->collectForTransaction($transaction, $classification);
                } catch (Throwable $exception) {
                    report($exception);
                }
            }

            return $classification;
        });
    }
}
