<?php

namespace App\Services\Disbursements;

use App\Models\Advance;
use App\Models\Disbursement;
use App\Services\Advances\AdvanceService;
use App\Services\Daraja\DarajaClient;
use App\Support\BusinessOwner;
use App\Support\Money;
use App\Support\MpesaSecurityCredential;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class MpesaB2cDisbursementService
{
    public function __construct(
        private readonly DarajaClient $daraja,
        private readonly AdvanceService $advances,
    ) {}

    public function disburse(Advance $advance): Disbursement
    {
        $shortcode = (string) config('services.mpesa.b2c.shortcode');
        $initiatorName = (string) config('services.mpesa.b2c.initiator_name');
        $commandId = (string) config('services.mpesa.b2c.command_id', 'BusinessPayment');
        $resultUrl = (string) config('services.mpesa.b2c.result_url');
        $timeoutUrl = (string) config('services.mpesa.b2c.timeout_url');

        if (blank($shortcode) || blank($initiatorName) || blank($resultUrl) || blank($timeoutUrl)) {
            throw new RuntimeException('M-Pesa B2C is not configured.');
        }

        $securityCredential = $this->securityCredential();

        $owner = BusinessOwner::for($advance->business);

        if ($owner === null || blank($owner->phone_number)) {
            throw new RuntimeException('The business owner has no phone number for disbursement.');
        }

        $disbursement = $this->advances->requestDisbursement($advance, 'mpesa_b2c');
        $originatorConversationId = (string) Str::uuid();

        $disbursement->update([
            'originator_conversation_id' => $originatorConversationId,
        ]);

        $payload = [
            'OriginatorConversationID' => $originatorConversationId,
            'InitiatorName' => $initiatorName,
            'SecurityCredential' => $securityCredential,
            'CommandID' => $commandId,
            'Amount' => Money::of($disbursement->amount_minor, 'KES')->toMajor(),
            'PartyA' => $shortcode,
            'PartyB' => $owner->phone_number,
            'Remarks' => 'QCash advance',
            'QueueTimeOutURL' => $timeoutUrl,
            'ResultURL' => $resultUrl,
            'Occasion' => 'QCash advance',
        ];

        try {
            $response = $this->daraja->b2cPaymentRequest($payload);
        } catch (Throwable $exception) {
            $this->advances->markDisbursementFailed($disbursement, $exception->getMessage());

            throw $exception;
        }

        if ((string) ($response['ResponseCode'] ?? '') !== '0') {
            $message = (string) ($response['ResponseDescription'] ?? 'The B2C request was not accepted.');

            $this->advances->markDisbursementFailed($disbursement, $message);

            throw new RuntimeException($message);
        }

        $disbursement->update([
            'provider_reference' => $response['ConversationID'] ?? null,
            'provider_response' => [
                ...$response,
                'recipient_phone_number' => $owner->phone_number,
            ],
        ]);

        return $disbursement->refresh();
    }

    private function securityCredential(): string
    {
        $preEncrypted = trim(
            (string) config('services.mpesa.b2c.security_credential'),
            " \t\n\r\0\x0B\"'",
        );

        if (strlen($preEncrypted) >= 100) {
            return $preEncrypted;
        }

        $password = trim(
            (string) config('services.mpesa.b2c.initiator_password'),
            " \t\n\r\0\x0B\"'",
        );

        if ($password === '') {
            $password = $preEncrypted;
        }

        $certificatePath = (string) config('services.mpesa.b2c.certificate_path');

        if ($password === '' || blank($certificatePath)) {
            throw new RuntimeException('M-Pesa B2C is not configured.');
        }

        return MpesaSecurityCredential::encrypt($password, $certificatePath);
    }
}
