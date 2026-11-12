<?php

namespace App\Services\Disbursements;

use App\Models\Advance;
use App\Models\Disbursement;
use App\Services\Advances\AdvanceService;
use App\Support\BusinessOwner;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class MpesaB2cDisbursementService
{
    public function __construct(
        private readonly MpesaB2cGateway $gateway,
        private readonly AdvanceService $advances,
    ) {}

    public function disburse(Advance $advance): Disbursement
    {
        $this->gateway->ensureConfigured();

        $owner = BusinessOwner::for($advance->business);

        if ($owner === null || blank($owner->phone_number)) {
            throw ValidationException::withMessages([
                'phone_number' => 'The business owner has no phone number for disbursement.',
            ]);
        }

        $disbursement = $this->advances->requestDisbursement($advance, 'mpesa_b2c');
        $originatorConversationId = (string) Str::uuid();

        $disbursement->update([
            'originator_conversation_id' => $originatorConversationId,
        ]);

        try {
            $response = $this->gateway->send(
                $owner->phone_number,
                $disbursement->amount_minor,
                $originatorConversationId,
                'QCash advance',
                'QCash advance',
            );
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
}
