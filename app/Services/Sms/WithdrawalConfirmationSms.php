<?php

namespace App\Services\Sms;

use App\Models\Business;
use App\Models\BusinessWithdrawal;
use App\Support\BusinessOwner;
use App\Support\Money;
use Markt\LaravelAuth\Contracts\SmsSender;
use Throwable;

class WithdrawalConfirmationSms
{
    public function __construct(private readonly SmsSender $smsSender) {}

    public function send(Business $business, BusinessWithdrawal $withdrawal, ?string $receipt = null): void
    {
        $owner = BusinessOwner::for($business);

        if ($owner === null || blank($owner->phone_number)) {
            return;
        }

        $message = sprintf(
            'QCash: A withdrawal of %s has been sent to your M-Pesa account.',
            Money::of($withdrawal->amount_minor, 'KES')->formatted('KES'),
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
