<?php

namespace App\Services\Withdrawals;

use App\Enums\BusinessWithdrawalStatus;
use App\Models\BusinessWithdrawal;
use App\Services\Sms\WithdrawalConfirmationSms;
use App\Services\Wallets\BusinessWalletService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class MpesaB2cWithdrawalResultService
{
    public function __construct(
        private readonly BusinessWalletService $wallets,
        private readonly WithdrawalConfirmationSms $confirmationSms,
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

        $withdrawal = $this->findWithdrawal($result);

        if ($withdrawal === null || in_array($withdrawal->status, [
            BusinessWithdrawalStatus::Successful,
            BusinessWithdrawalStatus::Failed,
        ], true)) {
            return $withdrawal;
        }

        $resultCode = (int) Arr::get($result, 'ResultCode', -1);
        $resultDesc = (string) Arr::get($result, 'ResultDesc', '');
        $parameters = $this->parameters($result);
        $receipt = (string) ($parameters['TransactionReceipt'] ?? Arr::get($result, 'TransactionID', ''));

        $providerResponse = $withdrawal->provider_response ?? [];
        $providerResponse['result'] = $payload;
        $providerResponse['result_parameters'] = $parameters;

        if ($resultCode === 0) {
            $withdrawal->update([
                'status' => BusinessWithdrawalStatus::Successful,
                'result_code' => '0',
                'result_desc' => $resultDesc,
                'provider_reference' => $receipt !== '' ? $receipt : $withdrawal->provider_reference,
                'provider_response' => $providerResponse,
                'completed_at' => now(),
            ]);

            $this->confirmationSms->send(
                $withdrawal->business,
                $withdrawal,
                $receipt !== '' ? $receipt : null,
            );

            return $withdrawal->refresh();
        }

        DB::transaction(function () use ($withdrawal, $resultCode, $resultDesc, $providerResponse) {
            $this->wallets->refund($withdrawal->business, $withdrawal->amount_minor, $withdrawal);

            $withdrawal->update([
                'status' => BusinessWithdrawalStatus::Failed,
                'result_code' => (string) $resultCode,
                'result_desc' => $resultDesc,
                'failure_reason' => $resultDesc !== '' ? $resultDesc : 'The B2C withdrawal failed.',
                'provider_response' => $providerResponse,
                'completed_at' => now(),
            ]);
        });

        return $withdrawal->refresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleTimeout(array $payload): ?BusinessWithdrawal
    {
        $result = Arr::get($payload, 'Result');

        if (! is_array($result)) {
            return null;
        }

        $withdrawal = $this->findWithdrawal($result);

        if ($withdrawal === null || $withdrawal->status !== BusinessWithdrawalStatus::Processing) {
            return $withdrawal;
        }

        $providerResponse = $withdrawal->provider_response ?? [];
        $providerResponse['timeout'] = $payload;

        $withdrawal->update([
            'status' => BusinessWithdrawalStatus::TimedOut,
            'failure_reason' => 'The B2C request timed out; the debit is held for reconciliation.',
            'provider_response' => $providerResponse,
        ]);

        return $withdrawal->refresh();
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function findWithdrawal(array $result): ?BusinessWithdrawal
    {
        $originatorId = (string) Arr::get($result, 'OriginatorConversationID', '');
        $conversationId = (string) Arr::get($result, 'ConversationID', '');

        if ($originatorId !== '') {
            $withdrawal = BusinessWithdrawal::query()
                ->where('originator_conversation_id', $originatorId)
                ->first();

            if ($withdrawal !== null) {
                return $withdrawal;
            }
        }

        if ($conversationId !== '') {
            return BusinessWithdrawal::query()
                ->where('provider_reference', $conversationId)
                ->first();
        }

        return null;
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
