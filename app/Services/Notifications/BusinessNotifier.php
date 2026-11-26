<?php

namespace App\Services\Notifications;

use App\Models\Business;
use App\Models\BusinessWithdrawal;
use App\Models\Disbursement;
use App\Models\RepaymentCollection;
use App\Models\Transaction;
use App\Notifications\AdvanceDisbursed;
use App\Notifications\PaymentReceived;
use App\Notifications\RepaymentFailed;
use App\Notifications\RepaymentReceived;
use App\Notifications\WithdrawalCompleted;
use App\Support\BusinessOwner;
use Illuminate\Notifications\Notification;
use Throwable;

class BusinessNotifier
{
    public function paymentReceived(Business $business, Transaction $transaction): void
    {
        $this->send($business, new PaymentReceived($business, $transaction));
    }

    public function withdrawalCompleted(
        Business $business,
        BusinessWithdrawal $withdrawal,
        ?string $receipt = null,
    ): void {
        $this->send($business, new WithdrawalCompleted($business, $withdrawal, $receipt));
    }

    public function repaymentFailed(
        Business $business,
        int $requestedMinor,
        int $availableMinor,
    ): void {
        $this->send($business, new RepaymentFailed($business, $requestedMinor, $availableMinor));
    }

    public function repaymentReceived(
        Business $business,
        RepaymentCollection $collection,
        int $outstandingMinor,
    ): void {
        $this->send($business, new RepaymentReceived($business, $collection, $outstandingMinor));
    }

    public function advanceDisbursed(
        Business $business,
        Disbursement $disbursement,
        ?string $receipt = null,
    ): void {
        $this->send($business, new AdvanceDisbursed($business, $disbursement, $receipt));
    }

    /**
     * A failure to store an in-app notification must never fail the operation
     * that triggered it, because the underlying payment or transfer is already
     * recorded at this point.
     */
    private function send(Business $business, Notification $notification): void
    {
        $owner = BusinessOwner::for($business);

        if ($owner === null) {
            return;
        }

        try {
            $owner->notify($notification);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
