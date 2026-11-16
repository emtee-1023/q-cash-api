<?php

namespace App\Services\Withdrawals;

use App\Models\BusinessWithdrawal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class MpesaB2cStatusResultService
{
    public function __construct(
        private readonly MpesaB2cWithdrawalResultService $withdrawals,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): ?BusinessWithdrawal
    {
        $result = Arr::get($payload, 'Result');

        if (! is_array($result)) {
            return null;
        }

        $parameters = $this->parameters($result);
        $withdrawal = $this->findWithdrawal($result, $parameters);

        if ($withdrawal === null) {
            Log::warning('M-Pesa B2C status result did not match a withdrawal.', [
                'payload' => $payload,
            ]);

            return null;
        }

        $resultCode = (int) Arr::get($result, 'ResultCode', -1);
        $transactionStatus = (string) ($parameters['TransactionStatus'] ?? '');

        if ($resultCode !== 0) {
            Log::warning('M-Pesa B2C status query did not return a result.', [
                'withdrawal_id' => $withdrawal->id,
                'response' => $result,
            ]);

            return $withdrawal;
        }

        $receipt = (string) ($parameters['ReceiptNo'] ?? Arr::get($result, 'TransactionID', ''));
        $description = (string) (
            $parameters['TransactionReason']
            ?? $parameters['ReasonType']
            ?? Arr::get($result, 'ResultDesc', '')
        );

        if ($transactionStatus === 'Completed') {
            return $this->recordOutcome($withdrawal, $payload, true, $receipt, $description);
        }

        if (in_array($transactionStatus, ['Cancelled', 'Declined', 'Expired', 'Failed'], true)) {
            return $this->recordOutcome(
                $withdrawal,
                $payload,
                false,
                null,
                $description !== '' ? $description : "The B2C transaction was {$transactionStatus}.",
            );
        }

        Log::info('M-Pesa B2C status result is not final yet.', [
            'withdrawal_id' => $withdrawal->id,
            'transaction_status' => $transactionStatus,
        ]);

        return $withdrawal;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordOutcome(
        BusinessWithdrawal $withdrawal,
        array $payload,
        bool $successful,
        ?string $receipt,
        string $description,
    ): ?BusinessWithdrawal {
        $statusResult = Arr::get($payload, 'Result', []);
        $result = Arr::get($payload, 'Result', []);

        $result['ResultCode'] = $successful ? 0 : 1;
        $result['ResultDesc'] = $description !== '' ? $description : 'The B2C transaction status was resolved.';
        $result['OriginatorConversationID'] = $withdrawal->originator_conversation_id;
        $result['ConversationID'] = $withdrawal->provider_reference;
        $result['TransactionID'] = $receipt;

        $parameters = Arr::get($result, 'ResultParameters.ResultParameter', []);
        $parameters = is_array($parameters) ? $parameters : [];
        $parameters[] = ['Key' => 'TransactionReceipt', 'Value' => $receipt];

        $result['ResultParameters'] = ['ResultParameter' => $parameters];
        $payload['Result'] = $result;
        $payload['StatusResult'] = $statusResult;

        return $this->withdrawals->handle($payload);
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $parameters
     */
    private function findWithdrawal(array $result, array $parameters): ?BusinessWithdrawal
    {
        $identifiers = array_values(array_unique(array_filter([
            (string) ($parameters['Originator Conversation ID'] ?? ''),
            (string) ($parameters['Conversation ID'] ?? ''),
            (string) Arr::get($result, 'OriginatorConversationID', ''),
            (string) Arr::get($result, 'ConversationID', ''),
        ])));

        if ($identifiers === []) {
            return null;
        }

        return BusinessWithdrawal::query()
            ->where(function (Builder $query) use ($identifiers) {
                $query->whereIn('originator_conversation_id', $identifiers)
                    ->orWhereIn('provider_reference', $identifiers)
                    ->orWhereIn('reconciliation_conversation_id', $identifiers);
            })
            ->first();
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function parameters(array $result): array
    {
        $items = Arr::get($result, 'ResultParameters.ResultParameter', []);

        if (! is_array($items)) {
            return [];
        }

        $parameters = [];

        foreach ($items as $item) {
            if (is_array($item) && isset($item['Key'])) {
                $parameters[(string) $item['Key']] = $item['Value'] ?? null;
            }
        }

        return $parameters;
    }
}
