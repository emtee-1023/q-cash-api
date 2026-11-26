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
        $advance = $this->disbursement->advance;

        $body = 'A cash advance of '.Money::of($this->disbursement->amount_minor, 'KES')->formatted('KES')
            .' has been credited to your QCash wallet.';

        if ($advance !== null && $advance->fee_amount_minor > 0) {
            $body .= ' Access fee: '
                .Money::of($advance->fee_amount_minor, 'KES')->formatted('KES')
                .'. Repay: '
                .Money::of($advance->total_repayable_minor, 'KES')->formatted('KES').'.';
        }

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
            'access_fee_minor' => $advance?->fee_amount_minor,
            'total_repayable_minor' => $advance?->total_repayable_minor,
            'currency' => 'KES',
            'reference' => $this->receipt,
            'occurred_at' => ($this->disbursement->completed_at ?? now())->toIso8601String(),
        ];
    }
}
