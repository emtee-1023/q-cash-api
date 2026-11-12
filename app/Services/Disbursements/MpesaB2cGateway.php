<?php

namespace App\Services\Disbursements;

use App\Services\Daraja\DarajaClient;
use App\Support\Money;
use App\Support\MpesaSecurityCredential;
use RuntimeException;

class MpesaB2cGateway
{
    public function __construct(private readonly DarajaClient $daraja) {}

    public function ensureConfigured(): void
    {
        $this->configuration();
    }

    /**
     * @return array<string, mixed>
     */
    public function send(
        string $recipientPhoneNumber,
        int $amountMinor,
        string $originatorConversationId,
        string $remarks,
        string $occasion,
    ): array {
        $configuration = $this->configuration();

        return $this->daraja->b2cPaymentRequest([
            'OriginatorConversationID' => $originatorConversationId,
            'InitiatorName' => $configuration['initiator_name'],
            'SecurityCredential' => $configuration['security_credential'],
            'CommandID' => $configuration['command_id'],
            'Amount' => Money::of($amountMinor, 'KES')->toMajor(),
            'PartyA' => $configuration['shortcode'],
            'PartyB' => $recipientPhoneNumber,
            'Remarks' => $remarks,
            'QueueTimeOutURL' => $configuration['timeout_url'],
            'ResultURL' => $configuration['result_url'],
            'Occasion' => $occasion,
        ]);
    }

    /**
     * @return array{
     *     shortcode: string,
     *     initiator_name: string,
     *     security_credential: string,
     *     command_id: string,
     *     result_url: string,
     *     timeout_url: string
     * }
     */
    private function configuration(): array
    {
        $shortcode = (string) config('services.mpesa.b2c.shortcode');
        $initiatorName = (string) config('services.mpesa.b2c.initiator_name');
        $commandId = (string) config('services.mpesa.b2c.command_id', 'BusinessPayment');
        $resultUrl = (string) config('services.mpesa.b2c.result_url');
        $timeoutUrl = (string) config('services.mpesa.b2c.timeout_url');

        if (blank($shortcode) || blank($initiatorName) || blank($resultUrl) || blank($timeoutUrl)) {
            throw new RuntimeException('M-Pesa B2C is not configured.');
        }

        return [
            'shortcode' => $shortcode,
            'initiator_name' => $initiatorName,
            'security_credential' => $this->securityCredential(),
            'command_id' => $commandId,
            'result_url' => $resultUrl,
            'timeout_url' => $timeoutUrl,
        ];
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
