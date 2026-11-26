<?php

namespace App\Services\Sms;

use App\Models\Business;
use App\Models\RepaymentCollection;
use App\Support\BusinessOwner;
use App\Support\Money;
use Markt\LaravelAuth\Contracts\SmsSender;
use Throwable;

class RepaymentSms
{
    public function __construct(private readonly SmsSender $smsSender) {}

    public function paymentReceived(
        Business $business,
        RepaymentCollection $collection,
        int $outstandingMinor,
    ): void {
        $owner = BusinessOwner::for($business);

        if ($owner === null || blank($owner->phone_number)) {
            return;
        }

        $amount = Money::of($collection->amount_minor, $collection->currency)->formatted('KES');

        $message = "QCash: Loan repayment of {$amount} received.";

        if ($outstandingMinor > 0) {
            $message .= ' Outstanding balance: '
                .Money::of($outstandingMinor, 'KES')->formatted('KES').'.';
        } else {
            $message .= ' Your loan is fully repaid.';
        }

        try {
            $this->smsSender->send($owner->phone_number, $message);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function insufficientWalletBalance(
        Business $business,
        int $requestedMinor,
        int $availableMinor,
    ): void {
        $owner = BusinessOwner::for($business);

        if ($owner === null || blank($owner->phone_number)) {
            return;
        }

        $message = sprintf(
            'QCash: Your repayment of %s could not be completed because your wallet balance of %s is insufficient. Top up your wallet or pay via M-Pesa.',
            Money::of($requestedMinor, 'KES')->formatted('KES'),
            Money::of($availableMinor, 'KES')->formatted('KES'),
        );

        try {
            $this->smsSender->send($owner->phone_number, $message);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
