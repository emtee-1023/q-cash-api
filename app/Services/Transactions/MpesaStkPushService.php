<?php

namespace App\Services\Transactions;

use App\Enums\CollectionMethod;
use App\Enums\StkPushPurpose;
use App\Enums\StkPushStatus;
use App\Enums\TransactionDirection;
use App\Models\Business;
use App\Models\RepaymentCollection;
use App\Models\StkPushRequest;
use App\Models\Transaction;
use App\Services\Daraja\DarajaClient;
use App\Services\Notifications\BusinessNotifier;
use App\Services\Repayments\RepaymentAllocationPlanner;
use App\Services\Repayments\RepaymentService;
use App\Services\Sms\MpesaConfirmationSms;
use App\Services\Sms\RepaymentSms;
use App\Support\Money;
use App\Support\MpesaTimestamp;
use App\Support\PhoneNumber;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class MpesaStkPushService
{
    public function __construct(
        private readonly DarajaClient $daraja,
        private readonly TransactionIngestionService $ingestion,
        private readonly MpesaConfirmationSms $confirmationSms,
        private readonly BusinessNotifier $notifier,
        private readonly RepaymentAllocationPlanner $repaymentPlanner,
        private readonly RepaymentService $repayments,
        private readonly RepaymentSms $repaymentSms,
    ) {}

    /**
     * Initiate an STK push and persist the checkout request so the asynchronous
     * callback can be matched back to the business.
     */
    public function initiate(
        Business $business,
        string $phoneNumber,
        int $amountMinor,
        ?string $accountReference = null,
        ?string $description = null,
        StkPushPurpose $purpose = StkPushPurpose::Payment,
    ): StkPushRequest {
        $shortcode = (string) config('services.mpesa.shortcode');
        $passkey = (string) config('services.mpesa.passkey');
        $callbackUrl = (string) config('services.mpesa.stk_callback_url');

        if (blank($shortcode) || blank($passkey) || blank($callbackUrl)) {
            throw new RuntimeException('M-Pesa STK push is not configured.');
        }

        $phoneNumber = PhoneNumber::normalize($phoneNumber);
        $accountReference ??= $business->collection_account_number;
        $description ??= 'QCash payment';
        $timestamp = now('Africa/Nairobi')->format('YmdHis');

        $stkPush = $business->stkPushRequests()->create([
            'purpose' => $purpose,
            'phone_number' => $phoneNumber,
            'amount_minor' => $amountMinor,
            'currency' => 'KES',
            'account_reference' => $accountReference,
            'transaction_desc' => $description,
            'status' => StkPushStatus::Pending,
            'initiated_at' => now(),
        ]);

        $payload = [
            'BusinessShortCode' => $shortcode,
            'Password' => base64_encode($shortcode.$passkey.$timestamp),
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => Money::of($amountMinor, 'KES')->toMajor(),
            'PartyA' => $phoneNumber,
            'PartyB' => $shortcode,
            'PhoneNumber' => $phoneNumber,
            'CallBackURL' => $callbackUrl,
            'AccountReference' => $accountReference,
            'TransactionDesc' => $description,
        ];

        try {
            $response = $this->daraja->initiateStkPush($payload);
        } catch (Throwable $exception) {
            $stkPush->update([
                'status' => StkPushStatus::Failed,
                'result_desc' => $exception->getMessage(),
                'completed_at' => now(),
            ]);

            throw $exception;
        }

        $stkPush->update([
            'merchant_request_id' => $response['MerchantRequestID'] ?? null,
            'checkout_request_id' => $response['CheckoutRequestID'] ?? null,
            'result_code' => isset($response['ResponseCode']) ? (string) $response['ResponseCode'] : null,
            'result_desc' => $response['ResponseDescription'] ?? null,
            'raw_response' => $response,
        ]);

        return $stkPush->refresh();
    }

    /**
     * Process an asynchronous STK push callback.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleCallback(array $payload): ?StkPushRequest
    {
        $callback = Arr::get($payload, 'Body.stkCallback');

        if (! is_array($callback)) {
            return null;
        }

        $checkoutRequestId = (string) Arr::get($callback, 'CheckoutRequestID', '');

        if ($checkoutRequestId === '') {
            return null;
        }

        $stkPush = StkPushRequest::query()
            ->where('checkout_request_id', $checkoutRequestId)
            ->first();

        if ($stkPush === null) {
            return null;
        }

        if ($stkPush->status === StkPushStatus::Successful) {
            return $stkPush;
        }

        $resultCode = (int) Arr::get($callback, 'ResultCode', -1);
        $resultDesc = (string) Arr::get($callback, 'ResultDesc', '');
        $status = $this->statusForResultCode($resultCode);

        if ($status !== StkPushStatus::Successful) {
            $stkPush->update([
                'status' => $status,
                'result_code' => (string) $resultCode,
                'result_desc' => $resultDesc,
                'raw_callback' => $payload,
                'completed_at' => now(),
            ]);

            return $stkPush->refresh();
        }

        $metadata = $this->metadata($callback);
        $receipt = (string) ($metadata['MpesaReceiptNumber'] ?? '');
        $amount = (string) ($metadata['Amount'] ?? '');

        if ($receipt === '' || $amount === '') {
            $stkPush->update([
                'status' => StkPushStatus::Failed,
                'result_code' => (string) $resultCode,
                'result_desc' => 'The STK callback metadata is incomplete.',
                'raw_callback' => $payload,
                'completed_at' => now(),
            ]);

            return $stkPush->refresh();
        }

        $transaction = null;
        $repaymentCollection = null;

        DB::transaction(function () use (
            $stkPush,
            $payload,
            $resultDesc,
            $metadata,
            $receipt,
            $amount,
            &$transaction,
            &$repaymentCollection,
        ): void {
            $transaction = $this->ingestion->ingest([
                'business_id' => $stkPush->business_id,
                'payment_account_id' => null,
                'provider' => 'mpesa',
                'provider_transaction_id' => $receipt,
                'transaction_type' => 'stk_push',
                'direction' => TransactionDirection::Inflow->value,
                'amount_minor' => Money::fromMajor($amount, 'KES')->minor,
                'currency' => 'KES',
                'transaction_time' => MpesaTimestamp::parse((string) ($metadata['TransactionDate'] ?? '')),
                'provider_status' => 'completed',
                'counterparty_reference' => (string) ($metadata['PhoneNumber'] ?? ''),
                'counterparty_name' => null,
                'raw_payload' => $payload,
                'ingested_at' => now(),
            ]);

            $stkPush->update([
                'status' => StkPushStatus::Successful,
                'result_code' => '0',
                'result_desc' => $resultDesc,
                'raw_callback' => $payload,
                'transaction_id' => $transaction->id,
                'completed_at' => now(),
            ]);

            $repaymentCollection = $this->recordRepayment($stkPush, $transaction);
        });

        if ($transaction !== null && $transaction->wasRecentlyCreated) {
            if ($stkPush->purpose === StkPushPurpose::Repayment
                && $repaymentCollection !== null
                && $repaymentCollection->wasRecentlyCreated) {
                $outstanding = $this->repaymentPlanner->outstandingTotal($stkPush->business);

                $this->notifier->repaymentReceived(
                    $stkPush->business,
                    $repaymentCollection,
                    $outstanding,
                );

                $this->repaymentSms->paymentReceived(
                    $stkPush->business,
                    $repaymentCollection,
                    $outstanding,
                );
            } else {
                $this->confirmationSms->send($stkPush->business, $transaction);
                $this->notifier->paymentReceived($stkPush->business, $transaction);
            }
        }

        return $stkPush->refresh();
    }

    /**
     * Allocate a confirmed repayment STK push across the business's advances.
     * The checkout request id makes the allocation idempotent; any amount above
     * the outstanding balance is left unallocated and logged.
     */
    private function recordRepayment(StkPushRequest $stkPush, Transaction $transaction): ?RepaymentCollection
    {
        if ($stkPush->purpose !== StkPushPurpose::Repayment) {
            return null;
        }

        $plan = $this->repaymentPlanner->plan(
            $stkPush->business,
            $transaction->amount_minor,
            lock: true,
        );

        $allocated = $transaction->amount_minor - $plan['unallocated_minor'];

        if ($allocated <= 0) {
            Log::warning('An M-Pesa repayment did not match any outstanding advance.', [
                'stk_push_request_id' => $stkPush->id,
                'transaction_id' => $transaction->id,
                'paid_amount_minor' => $transaction->amount_minor,
            ]);

            return null;
        }

        if ($plan['unallocated_minor'] > 0) {
            Log::warning('An M-Pesa repayment exceeded the outstanding balance.', [
                'stk_push_request_id' => $stkPush->id,
                'transaction_id' => $transaction->id,
                'paid_amount_minor' => $transaction->amount_minor,
                'unallocated_minor' => $plan['unallocated_minor'],
            ]);
        }

        return $this->repayments->recordSuccessfulCollection([
            'business_id' => $stkPush->business_id,
            'source_transaction_id' => $transaction->id,
            'collection_method' => CollectionMethod::ManualPayment->value,
            'provider' => 'mpesa',
            'provider_reference' => $transaction->provider_transaction_id,
            'amount_minor' => $allocated,
            'currency' => $transaction->currency,
            'idempotency_key' => 'stk-repayment:'.$stkPush->checkout_request_id,
            'collected_at' => $transaction->transaction_time,
            'provider_response' => [
                'paid_amount_minor' => $transaction->amount_minor,
                'unallocated_minor' => $plan['unallocated_minor'],
            ],
        ], $plan['allocations']);
    }

    private function statusForResultCode(int $resultCode): StkPushStatus
    {
        return match ($resultCode) {
            0 => StkPushStatus::Successful,
            1032 => StkPushStatus::Cancelled,
            1037 => StkPushStatus::Timeout,
            default => StkPushStatus::Failed,
        };
    }

    /**
     * @param  array<string, mixed>  $callback
     * @return array<string, mixed>
     */
    private function metadata(array $callback): array
    {
        $items = Arr::get($callback, 'CallbackMetadata.Item', []);

        if (! is_array($items)) {
            return [];
        }

        $metadata = [];

        foreach ($items as $item) {
            if (is_array($item) && isset($item['Name'])) {
                $metadata[(string) $item['Name']] = $item['Value'] ?? null;
            }
        }

        return $metadata;
    }
}
