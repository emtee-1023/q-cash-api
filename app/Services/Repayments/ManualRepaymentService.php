<?php

namespace App\Services\Repayments;

use App\Enums\CollectionMethod;
use App\Enums\StkPushPurpose;
use App\Exceptions\InsufficientWalletBalanceException;
use App\Models\Business;
use App\Models\RepaymentCollection;
use App\Models\StkPushRequest;
use App\Services\Notifications\BusinessNotifier;
use App\Services\Sms\RepaymentSms;
use App\Services\Transactions\MpesaStkPushService;
use App\Services\Wallets\BusinessWalletService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManualRepaymentService
{
    public function __construct(
        private readonly RepaymentService $repayments,
        private readonly RepaymentAllocationPlanner $planner,
        private readonly BusinessWalletService $wallets,
        private readonly BusinessNotifier $notifier,
        private readonly RepaymentSms $sms,
        private readonly MpesaStkPushService $stkPush,
    ) {}

    public function payFromWallet(
        Business $business,
        int $amountMinor,
        string $idempotencyKey,
    ): RepaymentCollection {
        try {
            $collection = DB::transaction(function () use ($business, $amountMinor, $idempotencyKey) {
                $existing = RepaymentCollection::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existing !== null) {
                    if ($existing->business_id !== $business->id) {
                        throw ValidationException::withMessages([
                            'idempotency_key' => 'This idempotency key has already been used.',
                        ]);
                    }

                    return $existing->load('allocations');
                }

                $plan = $this->planner->plan($business, $amountMinor, lock: true);

                if ($plan['allocations'] === []) {
                    throw ValidationException::withMessages([
                        'amount_minor' => 'There is no outstanding balance to repay.',
                    ]);
                }

                if ($plan['unallocated_minor'] > 0) {
                    $outstanding = Money::of(
                        $this->planner->outstandingTotal($business),
                        'KES',
                    )->formatted('KES');

                    throw ValidationException::withMessages([
                        'amount_minor' => "The amount exceeds the outstanding balance of {$outstanding}.",
                    ]);
                }

                $this->wallets->debit(
                    $business,
                    $amountMinor,
                    description: 'Loan repayment',
                    reference: 'repayment:'.$idempotencyKey,
                );

                $collection = $this->repayments->recordSuccessfulCollection([
                    'business_id' => $business->id,
                    'collection_method' => CollectionMethod::ManualPayment->value,
                    'provider' => 'qcash_wallet',
                    'provider_reference' => null,
                    'amount_minor' => $amountMinor,
                    'currency' => 'KES',
                    'idempotency_key' => $idempotencyKey,
                    'collected_at' => now(),
                    'source_transaction_id' => null,
                ], $plan['allocations']);

                return $collection->load('allocations');
            });
        } catch (InsufficientWalletBalanceException) {
            $available = $this->wallets->balance($business);

            $this->notifier->repaymentFailed($business, $amountMinor, $available);
            $this->sms->insufficientWalletBalance($business, $amountMinor, $available);

            throw ValidationException::withMessages([
                'amount_minor' => 'Insufficient wallet balance.',
            ]);
        }

        if ($collection->wasRecentlyCreated) {
            $outstanding = $this->planner->outstandingTotal($business);

            $this->notifier->repaymentReceived($business, $collection, $outstanding);
            $this->sms->paymentReceived($business, $collection, $outstanding);
        }

        return $collection;
    }

    public function initiateMpesa(
        Business $business,
        string $phoneNumber,
        int $amountMinor,
    ): StkPushRequest {
        $outstanding = $this->planner->outstandingTotal($business);

        if ($outstanding <= 0) {
            throw ValidationException::withMessages([
                'amount_minor' => 'There is no outstanding balance to repay.',
            ]);
        }

        if ($amountMinor > $outstanding) {
            $formatted = Money::of($outstanding, 'KES')->formatted('KES');

            throw ValidationException::withMessages([
                'amount_minor' => "The amount exceeds the outstanding balance of {$formatted}.",
            ]);
        }

        return $this->stkPush->initiate(
            $business,
            $phoneNumber,
            $amountMinor,
            description: 'QCash repay',
            purpose: StkPushPurpose::Repayment,
        );
    }
}
