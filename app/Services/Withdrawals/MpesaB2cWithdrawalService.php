<?php

namespace App\Services\Withdrawals;

use App\Enums\BusinessWithdrawalStatus;
use App\Models\Business;
use App\Models\BusinessWithdrawal;
use App\Services\Disbursements\MpesaB2cGateway;
use App\Services\Wallets\BusinessWalletService;
use App\Support\BusinessOwner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class MpesaB2cWithdrawalService
{
    public function __construct(
        private readonly MpesaB2cGateway $gateway,
        private readonly BusinessWalletService $wallets,
    ) {}

    public function withdraw(Business $business, int $amountMinor): BusinessWithdrawal
    {
        $this->gateway->ensureConfigured();

        $owner = BusinessOwner::for($business);

        if ($owner === null || blank($owner->phone_number)) {
            throw ValidationException::withMessages([
                'phone_number' => 'The business owner has no phone number for withdrawal.',
            ]);
        }

        $withdrawal = DB::transaction(function () use ($business, $owner, $amountMinor) {
            $withdrawal = $business->withdrawals()->create([
                'amount_minor' => $amountMinor,
                'currency' => 'KES',
                'recipient_phone_number' => $owner->phone_number,
                'status' => BusinessWithdrawalStatus::Processing,
                'provider' => 'mpesa_b2c',
                'requested_at' => now(),
            ]);

            $this->wallets->debit(
                $business,
                $amountMinor,
                $withdrawal,
                description: 'Withdrawal to owner M-Pesa',
                reference: 'withdrawal:'.$withdrawal->id,
            );

            return $withdrawal;
        });

        $originatorConversationId = (string) Str::uuid();

        $withdrawal->update([
            'originator_conversation_id' => $originatorConversationId,
        ]);

        try {
            $response = $this->gateway->send(
                $owner->phone_number,
                $withdrawal->amount_minor,
                $originatorConversationId,
                'QCash withdrawal',
                'QCash withdrawal',
            );
        } catch (Throwable $exception) {
            $this->wallets->refund($business, $withdrawal->amount_minor, $withdrawal);

            $withdrawal->update([
                'status' => BusinessWithdrawalStatus::Failed,
                'failure_reason' => $exception->getMessage(),
                'completed_at' => now(),
            ]);

            throw $exception;
        }

        if ((string) ($response['ResponseCode'] ?? '') !== '0') {
            $message = (string) ($response['ResponseDescription'] ?? 'The B2C request was not accepted.');

            $this->wallets->refund($business, $withdrawal->amount_minor, $withdrawal);

            $withdrawal->update([
                'status' => BusinessWithdrawalStatus::Failed,
                'failure_reason' => $message,
                'completed_at' => now(),
            ]);

            throw new RuntimeException($message);
        }

        $withdrawal->update([
            'provider_reference' => $response['ConversationID'] ?? null,
            'provider_response' => [
                ...$response,
                'recipient_phone_number' => $owner->phone_number,
            ],
        ]);

        return $withdrawal->refresh();
    }
}
