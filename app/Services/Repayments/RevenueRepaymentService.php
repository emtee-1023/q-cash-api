<?php

namespace App\Services\Repayments;

use App\Enums\AdvanceStatus;
use App\Enums\CollectionMethod;
use App\Enums\CollectionStatus;
use App\Enums\TransactionDirection;
use App\Models\Business;
use App\Models\RepaymentCollection;
use App\Models\Transaction;
use App\Models\TransactionClassification;
use App\Services\Notifications\BusinessNotifier;
use App\Services\Sms\RepaymentSms;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RevenueRepaymentService
{
    public function __construct(
        private readonly RepaymentService $repayments,
        private readonly RepaymentAllocationPlanner $planner,
        private readonly BusinessNotifier $notifier,
        private readonly RepaymentSms $sms,
    ) {}

    /**
     * Apply the percentage recovery rule to an eligible incoming receipt.
     *
     * Ct = min(r x Et, Bt, Dt), additionally bounded by the working capital
     * floor, which caps the share of a single receipt that can be deducted.
     */
    public function collectForTransaction(
        Transaction $transaction,
        ?TransactionClassification $classification = null,
    ): ?RepaymentCollection {
        if ($transaction->direction !== TransactionDirection::Inflow) {
            return null;
        }

        $classification ??= $transaction->classifications()
            ->where('is_current', true)
            ->first();

        if ($classification === null || ! $classification->revenue_eligible) {
            return null;
        }

        $business = $transaction->business;

        if ($business === null) {
            return null;
        }

        $idempotencyKey = 'revenue-deduction:'.$transaction->id;

        $collection = DB::transaction(function () use ($transaction, $business, $idempotencyKey) {
            $existing = RepaymentCollection::query()
                ->where('business_id', $business->id)
                ->where(function ($query) use ($transaction, $idempotencyKey) {
                    $query->where('source_transaction_id', $transaction->id)
                        ->orWhere('idempotency_key', $idempotencyKey);
                })
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $advances = $this->openAdvances($business, $transaction->transaction_time);

            if ($advances->isEmpty()) {
                return null;
            }

            $dailyRemaining = $this->remainingDailyAllowance(
                $business,
                $transaction->transaction_time,
            );

            $maxDeductionBps = $this->maxDeductionBps();
            $perReceiptAllowance = Money::applyBasisPoints(
                $transaction->amount_minor,
                $maxDeductionBps,
            );

            $allowance = min(
                $perReceiptAllowance,
                $dailyRemaining,
                $transaction->amount_minor,
            );

            $allocations = [];

            foreach ($advances as $advance) {
                if ($allowance <= 0) {
                    break;
                }

                $rateBps = min((int) $advance->recovery_rate_bps, $maxDeductionBps);

                $amount = min(
                    Money::applyBasisPoints($transaction->amount_minor, $rateBps),
                    (int) $advance->outstanding_balance_minor,
                    $allowance,
                );

                if ($amount <= 0) {
                    continue;
                }

                $allocations[] = [
                    'advance_id' => $advance->id,
                    'amount_minor' => $amount,
                ];

                $allowance -= $amount;
            }

            if ($allocations === []) {
                return null;
            }

            $collected = (int) array_sum(array_column($allocations, 'amount_minor'));

            return $this->repayments->recordSuccessfulCollection([
                'business_id' => $business->id,
                'source_transaction_id' => $transaction->id,
                'collection_method' => CollectionMethod::RevenueDeduction->value,
                'provider' => $transaction->provider,
                'provider_reference' => $transaction->provider_transaction_id,
                'amount_minor' => $collected,
                'currency' => $transaction->currency,
                'idempotency_key' => $idempotencyKey,
                'collected_at' => $transaction->transaction_time,
                'provider_response' => [
                    'eligible_receipt_minor' => $transaction->amount_minor,
                    'recovery_rate_bps' => (int) $advances->first()->recovery_rate_bps,
                    'max_deduction_bps' => $maxDeductionBps,
                    'daily_cap_minor' => $this->dailyCapMinor(),
                    'daily_remaining_minor' => $dailyRemaining,
                    'collected_minor' => $collected,
                ],
            ], $allocations);
        });

        if ($collection !== null && $collection->wasRecentlyCreated) {
            $outstanding = $this->planner->outstandingTotal($business);

            $this->notifier->repaymentReceived($business, $collection, $outstanding);
            $this->sms->paymentReceived($business, $collection, $outstanding);
        }

        return $collection;
    }

    private function openAdvances(Business $business, Carbon $collectedAt)
    {
        return $business->advances()
            ->whereIn('status', [
                AdvanceStatus::Active->value,
                AdvanceStatus::Overdue->value,
            ])
            ->where('outstanding_balance_minor', '>', 0)
            ->where(function ($query) use ($collectedAt) {
                $query->whereNull('repayment_started_at')
                    ->orWhere('repayment_started_at', '<=', $collectedAt);
            })
            ->where(function ($query) use ($collectedAt) {
                $query->whereNull('disbursed_at')
                    ->orWhere('disbursed_at', '<=', $collectedAt);
            })
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [
                AdvanceStatus::Overdue->value,
            ])
            ->orderByRaw('due_at IS NULL')
            ->orderBy('due_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    private function remainingDailyAllowance(Business $business, Carbon $collectedAt): int
    {
        $alreadyCollected = (int) RepaymentCollection::query()
            ->where('business_id', $business->id)
            ->where('collection_method', CollectionMethod::RevenueDeduction->value)
            ->where('status', CollectionStatus::Successful->value)
            ->whereBetween('collected_at', [
                $collectedAt->copy()->startOfDay(),
                $collectedAt->copy()->endOfDay(),
            ])
            ->sum('amount_minor');

        return max(0, $this->dailyCapMinor() - $alreadyCollected);
    }

    private function dailyCapMinor(): int
    {
        return max(0, (int) config('repayment.daily_cap_minor', 400000));
    }

    private function maxDeductionBps(): int
    {
        $floorBps = min(10000, max(0, (int) config(
            'repayment.working_capital_floor_bps',
            8500,
        )));

        return 10000 - $floorBps;
    }
}
