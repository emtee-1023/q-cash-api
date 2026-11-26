<?php

namespace App\Notifications;

use App\Models\Business;
use App\Models\RepaymentCollection;
use App\Support\Money;
use Illuminate\Notifications\Notification;

class RepaymentReceived extends Notification
{
    public function __construct(
        public Business $business,
        public RepaymentCollection $collection,
        public int $outstandingMinor,
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
        return 'repayment_received';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $amount = Money::of($this->collection->amount_minor, $this->collection->currency)
            ->formatted('KES');

        $body = "A loan repayment of {$amount} was received.";

        if ($this->outstandingMinor > 0) {
            $outstanding = Money::of($this->outstandingMinor, 'KES')->formatted('KES');
            $body .= " Outstanding balance: {$outstanding}.";
        } else {
            $body .= ' Your loan is fully repaid.';
        }

        return [
            'type' => 'repayment_received',
            'title' => 'Loan repayment received',
            'body' => $body,
            'business_id' => $this->business->id,
            'business_public_id' => $this->business->public_id,
            'repayment_collection_id' => $this->collection->id,
            'amount_minor' => $this->collection->amount_minor,
            'outstanding_balance_minor' => $this->outstandingMinor,
            'currency' => $this->collection->currency,
            'reference' => $this->collection->provider_reference,
            'occurred_at' => ($this->collection->collected_at ?? now())->toIso8601String(),
        ];
    }
}
