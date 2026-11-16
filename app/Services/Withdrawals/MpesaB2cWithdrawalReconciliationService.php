<?php

namespace App\Services\Withdrawals;

use App\Enums\BusinessWithdrawalStatus;
use App\Exceptions\MpesaB2cStatusUnavailableException;
use App\Models\BusinessWithdrawal;
use App\Services\Disbursements\MpesaB2cGateway;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Throwable;

class MpesaB2cWithdrawalReconciliationService
{
    public function __construct(private readonly MpesaB2cGateway $gateway) {}

    public function requestStatus(BusinessWithdrawal $withdrawal): bool
    {
        if ($withdrawal->status !== BusinessWithdrawalStatus::Processing) {
            return false;
        }

        $identifier = $withdrawal->originator_conversation_id ?: $withdrawal->provider_reference;

        if (blank($identifier)) {
            Log::warning('M-Pesa B2C withdrawal has no transaction reference to reconcile.', [
                'withdrawal_id' => $withdrawal->id,
            ]);

            $withdrawal->update([
                'status' => BusinessWithdrawalStatus::TimedOut,
                'failure_reason' => 'The B2C transaction reference is missing; the debit is held for reconciliation.',
            ]);

            return false;
        }

        try {
            $response = $this->gateway->queryTransactionStatus((string) $identifier);
        } catch (RequestException $exception) {
            if ($this->isUnavailable($exception)) {
                Log::warning('M-Pesa B2C transaction status API is unavailable; skipping this run.', [
                    'withdrawal_id' => $withdrawal->id,
                    'status' => $exception->response->status(),
                ]);

                throw new MpesaB2cStatusUnavailableException(
                    'The M-Pesa transaction status API is unavailable.',
                    previous: $exception,
                );
            }

            $this->recordAttempt($withdrawal);

            Log::error('M-Pesa B2C transaction status query failed.', [
                'withdrawal_id' => $withdrawal->id,
                'response' => $exception->response->body(),
            ]);

            throw $exception;
        } catch (Throwable $exception) {
            $this->recordAttempt($withdrawal);

            Log::error('M-Pesa B2C transaction status query failed.', [
                'withdrawal_id' => $withdrawal->id,
                'exception' => $exception,
            ]);

            throw $exception;
        }

        $accepted = (string) ($response['ResponseCode'] ?? '') === '0';

        $this->recordAttempt(
            $withdrawal,
            $accepted ? ($response['ConversationID'] ?? null) : null,
        );

        if (! $accepted) {
            Log::warning('M-Pesa B2C transaction status query was rejected.', [
                'withdrawal_id' => $withdrawal->id,
                'response' => $response,
            ]);

            return false;
        }

        return true;
    }

    public function markUnconfirmed(BusinessWithdrawal $withdrawal): void
    {
        if ($withdrawal->status !== BusinessWithdrawalStatus::Processing) {
            return;
        }

        $withdrawal->update([
            'status' => BusinessWithdrawalStatus::TimedOut,
            'failure_reason' => 'The B2C transaction status could not be confirmed; the debit is held for reconciliation.',
        ]);

        Log::warning('M-Pesa B2C withdrawal could not be confirmed and was marked as timed out.', [
            'withdrawal_id' => $withdrawal->id,
            'reconciliation_attempts' => $withdrawal->reconciliation_attempts,
        ]);
    }

    private function recordAttempt(BusinessWithdrawal $withdrawal, ?string $conversationId = null): void
    {
        $attributes = [
            'reconciliation_requested_at' => now(),
        ];

        if ($conversationId !== null) {
            $attributes['reconciliation_conversation_id'] = $conversationId;
        }

        $withdrawal->increment('reconciliation_attempts', 1, $attributes);
        $withdrawal->refresh();
    }

    private function isUnavailable(RequestException $exception): bool
    {
        return in_array($exception->response->status(), [403, 429, 500, 502, 503, 504], true);
    }
}
