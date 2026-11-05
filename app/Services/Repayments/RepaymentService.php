<?php

namespace App\Services\Repayments;

use App\Enums\AdvanceStatus;
use App\Enums\CollectionStatus;
use App\Models\Advance;
use App\Models\RepaymentCollection;
use App\Services\Ledger\AdvanceLedger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RepaymentService
{
    public function __construct(private readonly AdvanceLedger $ledger) {}

    /**
     * Record a successful collection, allocate it across one or more advances,
     * write ledger entries and update advance balances. The idempotency key
     * guarantees that duplicate provider callbacks never create duplicates.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $allocations
     */
    public function recordSuccessfulCollection(array $data, array $allocations): RepaymentCollection
    {
        return DB::transaction(function () use ($data, $allocations) {
            $existing = RepaymentCollection::where('idempotency_key', $data['idempotency_key'])->first();

            if ($existing !== null) {
                return $existing;
            }

            $collection = RepaymentCollection::create(array_merge($data, [
                'status' => CollectionStatus::Successful,
                'collected_at' => $data['collected_at'] ?? now(),
                'processed_at' => now(),
            ]));

            $this->applyAllocations($collection, $allocations);

            return $collection;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function recordFailedCollection(array $data): RepaymentCollection
    {
        return RepaymentCollection::create(array_merge($data, [
            'status' => CollectionStatus::Failed,
        ]));
    }

    public function reverse(RepaymentCollection $collection): RepaymentCollection
    {
        return DB::transaction(function () use ($collection) {
            if ($collection->status !== CollectionStatus::Successful) {
                throw new InvalidArgumentException('Only successful collections can be reversed.');
            }

            foreach ($collection->allocations as $allocation) {
                $advance = Advance::lockForUpdate()->findOrFail($allocation->advance_id);

                // Reversal of a negative repayment entry is a positive amount.
                $this->ledger->reversal($advance, abs($allocation->amount_minor), allocation: $allocation);

                $newRepaid = $advance->amount_repaid_minor - $allocation->amount_minor;
                $newOutstanding = $advance->outstanding_balance_minor + $allocation->amount_minor;

                $updates = [
                    'amount_repaid_minor' => $newRepaid,
                    'outstanding_balance_minor' => $newOutstanding,
                ];

                if ($advance->status === AdvanceStatus::Settled && $newOutstanding > 0) {
                    $updates['status'] = AdvanceStatus::Active;
                    $updates['settled_at'] = null;
                }

                $advance->update($updates);
            }

            $collection->update(['status' => CollectionStatus::Reversed]);

            return $collection;
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $allocations
     */
    protected function applyAllocations(RepaymentCollection $collection, array $allocations): void
    {
        $totalAllocated = (int) array_sum(array_column($allocations, 'amount_minor'));

        if ($totalAllocated !== (int) $collection->amount_minor) {
            throw new InvalidArgumentException('Allocated amounts must equal the collected amount.');
        }

        foreach ($allocations as $allocation) {
            $advance = Advance::lockForUpdate()->findOrFail($allocation['advance_id']);

            if ($advance->business_id !== $collection->business_id) {
                throw new InvalidArgumentException('Cannot allocate a repayment to an advance belonging to another business.');
            }

            $amount = (int) $allocation['amount_minor'];

            $allocationModel = $collection->allocations()->create([
                'advance_id' => $advance->id,
                'amount_minor' => $amount,
            ]);

            $this->ledger->repayment($advance, $allocationModel, $amount);

            $newRepaid = $advance->amount_repaid_minor + $amount;
            $newOutstanding = $advance->outstanding_balance_minor - $amount;

            $updates = [
                'amount_repaid_minor' => $newRepaid,
                'outstanding_balance_minor' => max(0, $newOutstanding),
            ];

            if ($newOutstanding <= 0) {
                $updates['status'] = AdvanceStatus::Settled;
                $updates['settled_at'] = now();
            }

            $advance->update($updates);
        }
    }
}
