<?php

namespace App\Console\Commands;

use App\Enums\BusinessWithdrawalStatus;
use App\Exceptions\MpesaB2cStatusUnavailableException;
use App\Models\BusinessWithdrawal;
use App\Services\Withdrawals\MpesaB2cWithdrawalReconciliationService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Throwable;

class ReconcileMpesaB2cWithdrawals extends Command
{
    protected $signature = 'mpesa:reconcile-b2c-withdrawals
                            {--limit=50 : Maximum number of withdrawals to query per run}
                            {--id= : Reconcile a single withdrawal id}';

    protected $description = 'Query the M-Pesa transaction status API for stale processing B2C withdrawals';

    public function __construct(
        private readonly MpesaB2cWithdrawalReconciliationService $reconciliation,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $maxAttempts = (int) config('services.mpesa.b2c.reconcile_max_attempts', 6);

        $withdrawals = $this->withdrawalsToReconcile($maxAttempts);

        $queried = 0;
        $rejected = 0;
        $delayMs = max(0, (int) config('services.mpesa.b2c.reconcile_request_delay_ms', 1000));

        foreach ($withdrawals as $withdrawal) {
            try {
                $accepted = $this->reconciliation->requestStatus($withdrawal);

                if ($accepted) {
                    $queried++;
                } else {
                    $rejected++;
                }
            } catch (MpesaB2cStatusUnavailableException) {
                $this->warn('The M-Pesa transaction status API is unavailable; stopping this run.');

                break;
            } catch (Throwable $exception) {
                report($exception);

                $rejected++;
            }

            $withdrawal->refresh();

            if ($withdrawal->reconciliation_attempts >= $maxAttempts) {
                $this->reconciliation->markUnconfirmed($withdrawal);
            }

            if ($delayMs > 0) {
                usleep($delayMs * 1000);
            }
        }

        $this->info("Requested status for {$queried} withdrawals; {$rejected} were not accepted.");

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, BusinessWithdrawal>
     */
    private function withdrawalsToReconcile(int $maxAttempts): Collection
    {
        if ($withdrawalId = $this->option('id')) {
            return BusinessWithdrawal::query()
                ->whereKey($withdrawalId)
                ->where('status', BusinessWithdrawalStatus::Processing->value)
                ->get();
        }

        $reconcileAfter = now()->subMinutes((int) config('services.mpesa.b2c.reconcile_after_minutes', 5));
        $reconcileInterval = now()->subMinutes((int) config('services.mpesa.b2c.reconcile_interval_minutes', 10));

        return BusinessWithdrawal::query()
            ->where('status', BusinessWithdrawalStatus::Processing->value)
            ->where('created_at', '<=', $reconcileAfter)
            ->where('reconciliation_attempts', '<', $maxAttempts)
            ->where(function (Builder $query) use ($reconcileInterval) {
                $query->whereNull('reconciliation_requested_at')
                    ->orWhere('reconciliation_requested_at', '<=', $reconcileInterval);
            })
            ->orderBy('id')
            ->limit((int) $this->option('limit'))
            ->get();
    }
}
