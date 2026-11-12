<?php

namespace App\Services\Disbursements;

use App\Enums\DisbursementStatus;
use App\Models\Disbursement;
use App\Services\Advances\AdvanceService;
use App\Services\Sms\DisbursementConfirmationSms;
use Illuminate\Support\Arr;

class MpesaB2cResultService
{
    public function __construct(
        private readonly AdvanceService $advances,
        private readonly DisbursementConfirmationSms $confirmationSms,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): ?Disbursement
    {
        $result = Arr::get($payload, 'Result');

        if (! is_array($result)) {
            return null;
        }

        $disbursement = $this->findDisbursement($result);

        if ($disbursement === null) {
            return null;
        }

        if (in_array($disbursement->status, [DisbursementStatus::Successful, DisbursementStatus::Reversed], true)) {
            return $disbursement;
        }

        $resultCode = (int) Arr::get($result, 'ResultCode', -1);
        $resultDesc = (string) Arr::get($result, 'ResultDesc', '');
        $parameters = $this->parameters($result);
        $receipt = (string) ($parameters['TransactionReceipt'] ?? Arr::get($result, 'TransactionID', ''));

        $providerResponse = $disbursement->provider_response ?? [];
        $providerResponse['result'] = $payload;
        $providerResponse['result_parameters'] = $parameters;

        if ($resultCode === 0) {
            $alreadyDisbursed = Disbursement::query()
                ->where('advance_id', $disbursement->advance_id)
                ->where('status', DisbursementStatus::Successful)
                ->where('id', '!=', $disbursement->id)
                ->exists();

            if ($alreadyDisbursed) {
                $disbursement->update([
                    'status' => DisbursementStatus::Failed,
                    'result_code' => (string) $resultCode,
                    'result_desc' => $resultDesc,
                    'failure_reason' => 'Duplicate disbursement; this advance was already disbursed.',
                    'raw_callback' => $payload,
                    'provider_response' => $providerResponse,
                    'completed_at' => now(),
                ]);

                return $disbursement->refresh();
            }

            $this->advances->markDisbursementSuccessful(
                $disbursement,
                $receipt !== '' ? $receipt : null,
                $providerResponse,
            );

            $this->confirmationSms->send(
                $disbursement->advance->business,
                $disbursement,
                $receipt !== '' ? $receipt : null,
            );

            return $disbursement->refresh();
        }

        $this->advances->markDisbursementFailed(
            $disbursement,
            $resultDesc !== '' ? $resultDesc : 'The B2C disbursement failed.',
        );

        return $disbursement->refresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleTimeout(array $payload): ?Disbursement
    {
        $result = Arr::get($payload, 'Result');

        if (! is_array($result)) {
            return null;
        }

        $disbursement = $this->findDisbursement($result);

        if ($disbursement === null || $disbursement->status !== DisbursementStatus::Processing) {
            return $disbursement;
        }

        $this->advances->markDisbursementFailed($disbursement, 'The B2C request timed out.');

        return $disbursement->refresh();
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function findDisbursement(array $result): ?Disbursement
    {
        $originatorId = (string) Arr::get($result, 'OriginatorConversationID', '');
        $conversationId = (string) Arr::get($result, 'ConversationID', '');

        if ($originatorId !== '') {
            $disbursement = Disbursement::query()
                ->where('originator_conversation_id', $originatorId)
                ->first();

            if ($disbursement !== null) {
                return $disbursement;
            }
        }

        if ($conversationId !== '') {
            return Disbursement::query()
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
