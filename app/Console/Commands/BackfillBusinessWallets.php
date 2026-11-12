<?php

namespace App\Console\Commands;

use App\Enums\TransactionDirection;
use App\Models\Transaction;
use App\Services\Wallets\BusinessWalletService;
use Illuminate\Console\Command;

class BackfillBusinessWallets extends Command
{
    protected $signature = 'wallets:backfill {--business= : Backfill a single business id}';

    protected $description = 'Credit business wallets from existing inflow transactions';

    public function __construct(private readonly BusinessWalletService $wallets)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $query = Transaction::query()
            ->where('direction', TransactionDirection::Inflow->value);

        if ($businessId = $this->option('business')) {
            $query->where('business_id', $businessId);
        }

        $processed = 0;

        $query->orderBy('id')->chunkById(500, function ($transactions) use (&$processed): void {
            foreach ($transactions as $transaction) {
                $this->wallets->creditForTransaction($transaction);
                $processed++;
            }
        });

        $this->info("Credited wallet entries for {$processed} inflow transactions.");

        return self::SUCCESS;
    }
}
