<?php

namespace App\Services\Withdrawals;

use App\Enums\BusinessWithdrawalStatus;
use App\Models\Business;
use App\Models\BusinessWithdrawal;
use App\Services\Disbursements\MpesaB2cGateway;
use App\Services\Wallets\BusinessWalletService;
use App\Support\BusinessOwner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        Log::info('M-Pesa B2C withdrawal requested.', [
            'business_id' => $business->id,
            'amount_minor' => $amountMinor,
        ]);

        $this->gateway->ensureConfigured();

        $owner = BusinessOwner::for($business);

        if ($owner === null || blank($owner->phone_number)) {
            throw ValidationException::withMessages([
                'phone_number' => 'The business owner has no phone number for withdrawal.',
            ]);
        }

        Log::info('M-Pesa B2C withdrawal owner resolved.', [
            'business_id' => $business->id,
            'owner_user_id' => $owner->id,
            'recipient_phone_number' => $owner->phone_number,
        ]);

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
            Log::error('M-Pesa B2C withdrawal request failed.', [
                'withdrawal_id' => $withdrawal->id,
                'exception' => $exception,
            ]);

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

            Log::warning('M-Pesa B2C withdrawal request rejected.', [
                'withdrawal_id' => $withdrawal->id,
                'response' => $response,
            ]);

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
