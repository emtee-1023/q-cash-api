<?php

namespace App\Services\Sms;

use App\Enums\BusinessUserRole;
use App\Enums\BusinessUserStatus;
use App\Models\Business;
use App\Models\Transaction;
use App\Models\User;
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
        $owner = $this->owner($business);

        if ($owner === null || blank($owner->phone_number)) {
            return;
        }

        try {
            $this->smsSender->send($owner->phone_number, $this->message($transaction));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function owner(Business $business): ?User
    {
        $owners = $business->businessUsers()
            ->where('role', BusinessUserRole::Owner->value)
            ->where('status', BusinessUserStatus::Active->value)
            ->with('user')
            ->get();

        $owner = $owners->firstWhere('is_primary_contact', true) ?? $owners->first();

        return $owner?->user;
    }

    private function message(Transaction $transaction): string
    {
        $amount = Money::of($transaction->amount_minor, $transaction->currency);
        $paidAt = $transaction->transaction_time
            ->copy()
            ->setTimezone((string) config('services.mpesa.timezone', 'Africa/Nairobi'));

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

    /**
     * M-Pesa no longer exposes payer phone numbers in C2B callbacks. Safaricom
     * sends either a hash or a masked value, so the number is never shown.
     */
    private function sender(Transaction $transaction): string
    {
        $name = trim((string) $transaction->counterparty_name);
        $sender = $name === '' ? 'M-PESA user' : $name;

        if (trim((string) $transaction->counterparty_reference) !== '') {
            $sender .= ' xxx';
        }

        return $sender;
    }
}
