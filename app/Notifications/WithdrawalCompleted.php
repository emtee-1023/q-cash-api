<?php

namespace App\Notifications;

use App\Models\Business;
use App\Models\BusinessWithdrawal;
use App\Support\Money;
use Illuminate\Notifications\Notification;

class WithdrawalCompleted extends Notification
{
    public function __construct(
        public Business $business,
        public BusinessWithdrawal $withdrawal,
        public ?string $receipt = null,
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
        return 'withdrawal_completed';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $body = 'A withdrawal of '.Money::of($this->withdrawal->amount_minor, 'KES')->formatted('KES')
            .' has been sent to your M-Pesa account.';

        if (filled($this->receipt)) {
            $body .= ' Ref: '.$this->receipt.'.';
        }

        return [
            'type' => 'withdrawal_completed',
            'title' => 'Withdrawal sent',
            'body' => $body,
            'business_id' => $this->business->id,
            'business_public_id' => $this->business->public_id,
            'withdrawal_id' => $this->withdrawal->id,
            'amount_minor' => $this->withdrawal->amount_minor,
            'currency' => $this->withdrawal->currency,
            'reference' => $this->receipt,
            'occurred_at' => ($this->withdrawal->completed_at ?? now())->toIso8601String(),
        ];
    }
}
