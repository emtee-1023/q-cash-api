<?php

namespace App\Services\Advances;

use App\Enums\AdvanceStatus;
use App\Models\Business;
use App\Support\Money;
use Illuminate\Validation\ValidationException;

class AdvanceExposureService
{
    /**
     * Outstanding exposure includes every advance that has been committed but
     * not yet settled, cancelled or written off.
     */
    public function outstandingExposureMinor(Business $business): int
    {
        return (int) $business->advances()
            ->whereIn('status', [
                AdvanceStatus::Pending->value,
                AdvanceStatus::Approved->value,
                AdvanceStatus::Disbursing->value,
                AdvanceStatus::Active->value,
                AdvanceStatus::Overdue->value,
            ])
            ->sum('outstanding_balance_minor');
    }

    /**
     * The approved advance amount is the business cap. Repaying reduces the
     * outstanding balance, so the remaining headroom can be borrowed again.
     */
    public function availableExposureMinor(Business $business, int $limitMinor): int
    {
        return max(0, $limitMinor - $this->outstandingExposureMinor($business));
    }

    public function ensureWithinExposure(
        Business $business,
        int $limitMinor,
        int $requestedMinor,
    ): void {
        if ($requestedMinor <= 0) {
            throw ValidationException::withMessages([
                'requested_amount_minor' => 'The requested amount must be greater than zero.',
            ]);
        }

        $available = $this->availableExposureMinor($business, $limitMinor);

        if ($requestedMinor > $available) {
            $formatted = Money::of($available, 'KES')->formatted('KES');

            throw ValidationException::withMessages([
                'requested_amount_minor' => "The requested amount exceeds your available exposure of {$formatted}.",
            ]);
        }
    }
}
