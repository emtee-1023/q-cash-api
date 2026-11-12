<?php

namespace App\Services\Wallets;

use App\Enums\BusinessWalletEntryType;
use App\Exceptions\InsufficientWalletBalanceException;
use App\Models\Business;
use App\Models\BusinessWallet;
use App\Models\BusinessWalletEntry;
use App\Models\BusinessWithdrawal;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BusinessWalletService
{
    public function walletFor(Business $business): BusinessWallet
    {
        return BusinessWallet::firstOrCreate(
            ['business_id' => $business->id],
            ['balance_minor' => 0, 'currency' => 'KES'],
        );
    }

    public function balance(Business $business): int
    {
        return (int) BusinessWallet::query()
            ->where('business_id', $business->id)
            ->value('balance_minor') ?? 0;
    }

    public function creditForTransaction(Transaction $transaction): BusinessWalletEntry
    {
        return $this->credit(
            Business::withTrashed()->findOrFail($transaction->business_id),
            $transaction->amount_minor,
            $transaction,
            description: 'Payment received',
            reference: $transaction->provider_transaction_id,
        );
    }

    public function credit(
        Business $business,
        int $amountMinor,
        ?Transaction $transaction = null,
        ?BusinessWithdrawal $withdrawal = null,
        BusinessWalletEntryType $entryType = BusinessWalletEntryType::Credit,
        ?string $description = null,
        ?string $reference = null,
    ): BusinessWalletEntry {
        if ($amountMinor <= 0) {
            throw new InvalidArgumentException('Wallet credit amount must be positive.');
        }

        return DB::transaction(function () use (
            $business,
            $amountMinor,
            $transaction,
            $withdrawal,
            $entryType,
            $description,
            $reference,
        ) {
            $wallet = $this->lockedWallet($business);

            if ($transaction !== null) {
                $existing = $wallet->entries()
                    ->where('transaction_id', $transaction->id)
                    ->first();

                if ($existing !== null) {
                    return $existing;
                }
            }

            if ($withdrawal !== null) {
                $existing = $wallet->entries()
                    ->where('withdrawal_id', $withdrawal->id)
                    ->where('entry_type', $entryType->value)
                    ->first();

                if ($existing !== null) {
                    return $existing;
                }
            }

            $wallet->increment('balance_minor', $amountMinor);
            $wallet->refresh();

            return $wallet->entries()->create([
                'transaction_id' => $transaction?->id,
                'withdrawal_id' => $withdrawal?->id,
                'entry_type' => $entryType,
                'amount_minor' => $amountMinor,
                'balance_after_minor' => $wallet->balance_minor,
                'description' => $description,
                'reference' => $reference,
            ]);
        });
    }

    public function debit(
        Business $business,
        int $amountMinor,
        ?BusinessWithdrawal $withdrawal = null,
        ?string $description = null,
        ?string $reference = null,
    ): BusinessWalletEntry {
        if ($amountMinor <= 0) {
            throw new InvalidArgumentException('Wallet debit amount must be positive.');
        }

        return DB::transaction(function () use (
            $business,
            $amountMinor,
            $withdrawal,
            $description,
            $reference,
        ) {
            $wallet = $this->lockedWallet($business);

            if ($withdrawal !== null) {
                $existing = $wallet->entries()
                    ->where('withdrawal_id', $withdrawal->id)
                    ->where('entry_type', BusinessWalletEntryType::Debit->value)
                    ->first();

                if ($existing !== null) {
                    return $existing;
                }
            }

            if ($wallet->balance_minor < $amountMinor) {
                throw new InsufficientWalletBalanceException('Insufficient wallet balance.');
            }

            $wallet->decrement('balance_minor', $amountMinor);
            $wallet->refresh();

            return $wallet->entries()->create([
                'withdrawal_id' => $withdrawal?->id,
                'entry_type' => BusinessWalletEntryType::Debit,
                'amount_minor' => -$amountMinor,
                'balance_after_minor' => $wallet->balance_minor,
                'description' => $description,
                'reference' => $reference,
            ]);
        });
    }

    public function refund(
        Business $business,
        int $amountMinor,
        BusinessWithdrawal $withdrawal,
        ?string $description = null,
        ?string $reference = null,
    ): BusinessWalletEntry {
        return $this->credit(
            $business,
            $amountMinor,
            withdrawal: $withdrawal,
            entryType: BusinessWalletEntryType::Refund,
            description: $description ?? 'Withdrawal refund',
            reference: $reference,
        );
    }

    private function lockedWallet(Business $business): BusinessWallet
    {
        $wallet = $this->walletFor($business);

        return BusinessWallet::query()
            ->whereKey($wallet->id)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
