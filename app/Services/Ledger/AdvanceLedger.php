<?php

namespace App\Services\Ledger;

use App\Enums\LedgerEntryType;
use App\Models\Advance;
use App\Models\AdvanceLedgerEntry;
use App\Models\Disbursement;
use App\Models\RepaymentAllocation;

/**
 * Append-only financial ledger for advances.
 *
 * Sign convention (from the perspective of the amount owed by the business):
 *   - A positive amount_minor increases the outstanding balance
 *     (principal_disbursed, fee_assessed, debit adjustments).
 *   - A negative amount_minor decreases the outstanding balance
 *     (repayments, credit adjustments).
 *   - A reversal entry carries the negated sign of the entry it reverses.
 *
 * Ledger entries are never modified after creation; the cached balance columns
 * on advances are reconcilable against these entries and successful allocations.
 */
class AdvanceLedger
{
    public function principalDisbursed(Advance $advance, Disbursement $disbursement, int $amountMinor): AdvanceLedgerEntry
    {
        return $this->record(
            $advance,
            LedgerEntryType::PrincipalDisbursed,
            abs($amountMinor),
            disbursement: $disbursement,
        );
    }

    public function feeAssessed(Advance $advance, int $amountMinor): AdvanceLedgerEntry
    {
        return $this->record($advance, LedgerEntryType::FeeAssessed, abs($amountMinor));
    }

    public function repayment(Advance $advance, RepaymentAllocation $allocation, int $amountMinor): AdvanceLedgerEntry
    {
        return $this->record(
            $advance,
            LedgerEntryType::Repayment,
            -abs($amountMinor),
            allocation: $allocation,
        );
    }

    public function reversal(
        Advance $advance,
        int $signedAmountMinor,
        ?RepaymentAllocation $allocation = null,
        ?Disbursement $disbursement = null,
    ): AdvanceLedgerEntry {
        return $this->record(
            $advance,
            LedgerEntryType::Reversal,
            $signedAmountMinor,
            allocation: $allocation,
            disbursement: $disbursement,
        );
    }

    public function record(
        Advance $advance,
        LedgerEntryType $type,
        int $signedAmountMinor,
        ?RepaymentAllocation $allocation = null,
        ?Disbursement $disbursement = null,
        ?string $description = null,
        ?string $reference = null,
    ): AdvanceLedgerEntry {
        return AdvanceLedgerEntry::create([
            'advance_id' => $advance->id,
            'repayment_allocation_id' => $allocation?->id,
            'disbursement_id' => $disbursement?->id,
            'entry_type' => $type,
            'amount_minor' => $signedAmountMinor,
            'description' => $description,
            'reference' => $reference,
        ]);
    }
}
