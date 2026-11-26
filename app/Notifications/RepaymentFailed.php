<?php

namespace App\Notifications;

use App\Models\Business;
use App\Support\Money;
use Illuminate\Notifications\Notification;

class RepaymentFailed extends Notification
{
    public function __construct(
        public Business $business,
        public int $requestedMinor,
        public int $availableMinor,
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
        return 'repayment_failed';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $requested = Money::of($this->requestedMinor, 'KES')->formatted('KES');
        $available = Money::of($this->availableMinor, 'KES')->formatted('KES');

        return [
            'type' => 'repayment_failed',
            'title' => 'Repayment failed',
            'body' => "A repayment of {$requested} could not be completed because your wallet balance of {$available} is insufficient.",
            'business_id' => $this->business->id,
            'business_public_id' => $this->business->public_id,
            'requested_amount_minor' => $this->requestedMinor,
            'available_balance_minor' => $this->availableMinor,
            'currency' => 'KES',
            'occurred_at' => now()->toIso8601String(),
        ];
    }
}
