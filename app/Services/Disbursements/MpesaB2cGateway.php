<?php

namespace App\Services\Disbursements;

use App\Services\Daraja\DarajaClient;
use App\Support\Money;
use App\Support\MpesaSecurityCredential;
use Illuminate\Support\Facades\Log;
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

        Log::info('M-Pesa B2C payment request sending.', [
            'originator_conversation_id' => $originatorConversationId,
            'recipient_phone_number' => $recipientPhoneNumber,
            'amount_minor' => $amountMinor,
            'command_id' => $configuration['command_id'],
            'shortcode' => $configuration['shortcode'],
            'result_url' => $configuration['result_url'],
            'timeout_url' => $configuration['timeout_url'],
        ]);

        $response = $this->daraja->b2cPaymentRequest([
            'OriginatorConversationID' => $originatorConversationId,
            'InitiatorName' => $configuration['initiator_name'],
            'SecurityCredential' => $configuration['security_credential'],
            'CommandID' => $configuration['command_id'],
            'Amount' => (int) Money::of($amountMinor, 'KES')->toMajor(),
            'PartyA' => $configuration['shortcode'],
            'PartyB' => $recipientPhoneNumber,
            'Remarks' => $remarks,
            'QueueTimeOutURL' => $configuration['timeout_url'],
            'ResultURL' => $configuration['result_url'],
            'Occasion' => $occasion,
        ]);

        Log::info('M-Pesa B2C payment request accepted.', [
            'originator_conversation_id' => $originatorConversationId,
            'response_originator_conversation_id' => $response['OriginatorConversationID'] ?? null,
            'conversation_id' => $response['ConversationID'] ?? null,
            'response_code' => $response['ResponseCode'] ?? null,
            'response_description' => $response['ResponseDescription'] ?? null,
        ]);

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    public function queryTransactionStatus(string $transactionId): array
    {
        $configuration = $this->configuration();
        $resultUrl = (string) config('services.mpesa.b2c.status_result_url');
        $timeoutUrl = (string) config('services.mpesa.b2c.status_timeout_url');

        if (blank($resultUrl) || blank($timeoutUrl)) {
            throw new RuntimeException('M-Pesa B2C transaction status is not configured.');
        }

        Log::info('M-Pesa B2C transaction status query sending.', [
            'transaction_id' => $transactionId,
            'result_url' => $resultUrl,
            'timeout_url' => $timeoutUrl,
        ]);

        $response = $this->daraja->transactionStatus([
            'Initiator' => $configuration['initiator_name'],
            'SecurityCredential' => $configuration['security_credential'],
            'CommandID' => 'TransactionStatusQuery',
            'TransactionID' => $transactionId,
            'PartyA' => $configuration['shortcode'],
            'IdentifierType' => '4',
            'ResultURL' => $resultUrl,
            'QueueTimeOutURL' => $timeoutUrl,
            'Remarks' => 'QCash reconciliation',
            'Occasion' => 'QCash reconciliation',
        ]);

        Log::info('M-Pesa B2C transaction status query accepted.', [
            'transaction_id' => $transactionId,
            'conversation_id' => $response['ConversationID'] ?? null,
            'originator_conversation_id' => $response['OriginatorConversationID'] ?? null,
            'response_code' => $response['ResponseCode'] ?? null,
            'response_description' => $response['ResponseDescription'] ?? null,
        ]);

        return $response;
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
