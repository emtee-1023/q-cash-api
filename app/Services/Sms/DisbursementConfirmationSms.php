<?php

namespace App\Services\Sms;

use App\Models\Business;
use App\Models\Disbursement;
use App\Support\BusinessOwner;
use App\Support\Money;
use Markt\LaravelAuth\Contracts\SmsSender;
use Throwable;

class DisbursementConfirmationSms
{
    public function __construct(private readonly SmsSender $smsSender) {}

    public function send(Business $business, Disbursement $disbursement, ?string $receipt = null): void
    {
        $owner = BusinessOwner::for($business);

        if ($owner === null || blank($owner->phone_number)) {
            return;
        }

        $message = sprintf(
            'QCash: A cash advance of %s has been credited to your acct.',
            Money::of($disbursement->amount_minor, 'KES')->formatted('KES'),
        );

        if (filled($receipt)) {
            $message .= ' Ref: '.$receipt.'.';
        }

        $message .= ' Thank you for using QCash.';

        try {
            $this->smsSender->send($owner->phone_number, $message);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
