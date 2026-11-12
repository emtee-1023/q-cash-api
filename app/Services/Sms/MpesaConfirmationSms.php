<?php

namespace App\Services\Sms;

use App\Models\Business;
use App\Models\Transaction;
use App\Support\BusinessOwner;
use App\Support\Money;
use Illuminate\Support\Str;
use Markt\LaravelAuth\Contracts\SmsSender;
use Throwable;

class MpesaConfirmationSms
{
    public function __construct(private readonly SmsSender $smsSender) {}

    /**
     * Notify the business owner with an M-Pesa style confirmation message.
     *
     * A failure to send the SMS must never fail the webhook, because the payment
     * has already been recorded at this point.
     */
    public function send(Business $business, Transaction $transaction): void
    {
        $owner = BusinessOwner::for($business);

        if ($owner === null || blank($owner->phone_number)) {
            return;
        }

        try {
            $this->smsSender->send($owner->phone_number, $this->message($transaction));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function message(Transaction $transaction): string
    {
        $amount = Money::of($transaction->amount_minor, $transaction->currency);
        $paidAt = $transaction->transaction_time
            ->copy()
            ->setTimezone((string) config('services.mpesa.timezone', 'Africa/Nairobi'));

        if ($transaction->transaction_type === 'stk_push') {
            return sprintf(
                'Confirmed. STK push for %s has been paid successfully by phone number %s on %s at %s.',
                $amount->formatted('KES'),
                $this->phoneNumber($transaction),
                $paidAt->format('d/m/y'),
                $paidAt->format('g:i A'),
            );
        }

        $sender = $this->sender($transaction);

        return Str::of(sprintf(
            '%s Confirmed. %s received from %s on %s at %s.',
            $transaction->provider_transaction_id,
            str_replace('Ksh ', 'Ksh', $amount->formatted('Ksh')),
            $sender,
            $paidAt->format('d/m/y'),
            $paidAt->format('g:i A'),
        ))->squish()->toString();
    }

    private function phoneNumber(Transaction $transaction): string
    {
        $reference = trim((string) $transaction->counterparty_reference);

        return preg_match('/^(?:\+?254|0)\d{9}$/', $reference) === 1 ? $reference : 'xxx';
    }

    /**
     * M-Pesa no longer exposes payer phone numbers in C2B callbacks. Safaricom
     * sends either a hash or a masked value, so the number is never shown.
     */
    private function sender(Transaction $transaction): string
    {
        $name = trim((string) $transaction->counterparty_name);

        if ($name === '') {
            return 'an M-PESA customer';
        }

        if (trim((string) $transaction->counterparty_reference) !== '') {
            return $name.' xxx';
        }

        return $name;
    }
}
