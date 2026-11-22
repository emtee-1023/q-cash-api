<?php

namespace App\Notifications;

use App\Models\Business;
use App\Models\Transaction;
use App\Support\Money;
use Illuminate\Notifications\Notification;

class PaymentReceived extends Notification
{
    public function __construct(
        public Business $business,
        public Transaction $transaction,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function databaseType(object $notifiable): string
    {
        return 'payment_received';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $amount = Money::of($this->transaction->amount_minor, $this->transaction->currency);
        $source = $this->transaction->counterparty_name ?: $this->transaction->counterparty_reference;

        $body = 'A payment of '.$amount->formatted('KES').' was received';

        if (filled($source)) {
            $body .= ' from '.$source;
        }

        return [
            'type' => 'payment_received',
            'title' => 'Payment received',
            'body' => $body.'.',
            'business_id' => $this->business->id,
            'business_public_id' => $this->business->public_id,
            'transaction_id' => $this->transaction->id,
            'amount_minor' => $this->transaction->amount_minor,
            'currency' => $this->transaction->currency,
            'reference' => $this->transaction->provider_transaction_id,
            'occurred_at' => $this->transaction->transaction_time->toIso8601String(),
        ];
    }
}
