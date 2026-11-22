<?php

namespace App\Notifications;

use App\Models\Business;
use App\Models\Disbursement;
use App\Support\Money;
use Illuminate\Notifications\Notification;

class AdvanceDisbursed extends Notification
{
    public function __construct(
        public Business $business,
        public Disbursement $disbursement,
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
        return 'advance_disbursed';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $body = 'A cash advance of '.Money::of($this->disbursement->amount_minor, 'KES')->formatted('KES')
            .' has been credited to your business account.';

        if (filled($this->receipt)) {
            $body .= ' Ref: '.$this->receipt.'.';
        }

        return [
            'type' => 'advance_disbursed',
            'title' => 'Advance disbursed',
            'body' => $body,
            'business_id' => $this->business->id,
            'business_public_id' => $this->business->public_id,
            'advance_id' => $this->disbursement->advance_id,
            'disbursement_id' => $this->disbursement->id,
            'amount_minor' => $this->disbursement->amount_minor,
            'currency' => 'KES',
            'reference' => $this->receipt,
            'occurred_at' => ($this->disbursement->completed_at ?? now())->toIso8601String(),
        ];
    }
}
