<?php

namespace App\Services\Repayments;

use App\Enums\AdvanceStatus;
use App\Models\Business;

class RepaymentAllocationPlanner
{
    /**
     * Plan a FIFO allocation across a business's repayable advances. Overdue
     * advances are repaid before current ones, then the oldest due advances.
     *
     * @return array{
     *     allocations: array<int, array{advance_id: int, amount_minor: int}>,
     *     unallocated_minor: int,
     * }
     */
    public function plan(Business $business, int $amountMinor, bool $lock = false): array
    {
        $remaining = $amountMinor;
        $allocations = [];

        $query = $business->advances()
            ->whereIn('status', [
                AdvanceStatus::Active->value,
                AdvanceStatus::Overdue->value,
            ])
            ->where('outstanding_balance_minor', '>', 0)
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [
                AdvanceStatus::Overdue->value,
            ])
            ->orderByRaw('due_at IS NULL')
            ->orderBy('due_at')
            ->orderBy('id');

        if ($lock) {
            $query->lockForUpdate();
        }

        foreach ($query->get(['id', 'outstanding_balance_minor']) as $advance) {
            if ($remaining <= 0) {
                break;
            }

            $allocated = min($remaining, (int) $advance->outstanding_balance_minor);

            $allocations[] = [
                'advance_id' => $advance->id,
                'amount_minor' => $allocated,
            ];

            $remaining -= $allocated;
        }

        return [
            'allocations' => $allocations,
            'unallocated_minor' => $remaining,
        ];
    }

    public function outstandingTotal(Business $business): int
    {
        return (int) $business->advances()
            ->whereIn('status', [
                AdvanceStatus::Active->value,
                AdvanceStatus::Overdue->value,
            ])
            ->sum('outstanding_balance_minor');
    }
}
