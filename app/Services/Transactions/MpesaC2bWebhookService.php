<?php

namespace App\Services\Transactions;

use App\Enums\TransactionDirection;
use App\Enums\WebhookProcessingStatus;
use App\Models\Business;
use App\Models\ProviderWebhookEvent;
use App\Services\Sms\MpesaConfirmationSms;
use App\Support\Money;
use App\Support\MpesaTimestamp;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class MpesaC2bWebhookService
{
    public const PROVIDER = 'mpesa';

    public const EVENT_TYPE = 'c2b_confirmation';

    public function __construct(
        private readonly TransactionIngestionService $ingestion,
        private readonly MpesaConfirmationSms $confirmationSms,
    ) {}

    /**
     * Handle an M-Pesa C2B confirmation callback.
     *
     * The raw callback is always retained as a provider webhook event so that
     * unmatched or failed callbacks can be reconciled later. Duplicate callbacks
     * are ignored because the provider transaction id is idempotent.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleConfirmation(array $payload): ProviderWebhookEvent
    {
        $providerTransactionId = $this->providerTransactionId($payload);

        $event = $this->recordEvent($payload);

        if ($event->processing_status === WebhookProcessingStatus::Processed) {
            return $event;
        }

        if ($providerTransactionId === '') {
            $event->update([
                'processing_status' => WebhookProcessingStatus::Failed,
                'processing_error' => 'The confirmation payload is missing TransID.',
                'processed_at' => now(),
            ]);

            return $event->refresh();
        }

        $business = null;
        $transaction = null;

        try {
            DB::transaction(function () use ($event, $payload, $providerTransactionId, &$business, &$transaction): void {
                $business = $this->businessForPayload($payload);

                if ($business === null) {
                    $event->update([
                        'processing_status' => WebhookProcessingStatus::Failed,
                        'processing_error' => 'No business matches collection account number ['.$this->billRefNumber($payload).'].',
                        'processed_at' => now(),
                    ]);

                    return;
                }

                $event->update([
                    'processing_status' => WebhookProcessingStatus::Processing,
                    'processing_error' => null,
                ]);

                $transaction = $this->ingestion->ingest($this->transactionPayload(
                    $business,
                    $payload,
                    $providerTransactionId,
                ));

                $event->update([
                    'processing_status' => WebhookProcessingStatus::Processed,
                    'processing_error' => null,
                    'processed_at' => now(),
                ]);
            });
        } catch (Throwable $exception) {
            report($exception);

            $event->update([
                'processing_status' => WebhookProcessingStatus::Failed,
                'processing_error' => $exception->getMessage(),
                'processed_at' => now(),
            ]);

            return $event->refresh();
        }

        if ($business !== null && $transaction !== null && $transaction->wasRecentlyCreated) {
            $this->confirmationSms->send($business, $transaction);
        }

        return $event->refresh();
    }

    /**
     * Determine whether a C2B validation request belongs to one of our businesses.
     *
     * @param  array<string, mixed>  $payload
     */
    public function isKnownBillReference(array $payload): bool
    {
        $expectedShortCode = (string) config('services.mpesa.shortcode');
        $actualShortCode = (string) Arr::get($payload, 'BusinessShortCode', '');

        if ($expectedShortCode !== '' && $actualShortCode !== '' && $expectedShortCode !== $actualShortCode) {
            return false;
        }

        return $this->businessForPayload($payload) !== null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordEvent(array $payload): ProviderWebhookEvent
    {
        return ProviderWebhookEvent::firstOrCreate(
            [
                'provider' => self::PROVIDER,
                'external_event_id' => $this->externalEventId($payload),
            ],
            [
                'event_type' => self::EVENT_TYPE,
                'payload' => $payload,
                'processing_status' => WebhookProcessingStatus::Pending,
                'received_at' => now(),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function businessForPayload(array $payload): ?Business
    {
        $billRefNumber = $this->billRefNumber($payload);

        if ($billRefNumber === '') {
            return null;
        }

        return Business::query()
            ->where('collection_account_number', $billRefNumber)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function transactionPayload(
        Business $business,
        array $payload,
        string $providerTransactionId,
    ): array {
        return [
            'business_id' => $business->id,
            'payment_account_id' => null,
            'provider' => self::PROVIDER,
            'provider_transaction_id' => $providerTransactionId,
            'transaction_type' => $this->transactionType($payload),
            'direction' => TransactionDirection::Inflow->value,
            'amount_minor' => Money::fromMajor((string) Arr::get($payload, 'TransAmount', '0'), 'KES')->minor,
            'currency' => 'KES',
            'transaction_time' => MpesaTimestamp::parse((string) Arr::get($payload, 'TransTime', '')),
            'provider_status' => 'completed',
            'counterparty_reference' => Arr::get($payload, 'MSISDN'),
            'counterparty_name' => $this->counterpartyName($payload),
            'raw_payload' => $payload,
            'ingested_at' => now(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function transactionType(array $payload): ?string
    {
        $transactionType = Arr::get($payload, 'TransactionType');

        return filled($transactionType) ? Str::slug((string) $transactionType, '_') : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function counterpartyName(array $payload): ?string
    {
        $parts = array_filter([
            Arr::get($payload, 'FirstName'),
            Arr::get($payload, 'MiddleName'),
            Arr::get($payload, 'LastName'),
        ], fn (mixed $part): bool => filled($part));

        $name = Str::of(implode(' ', $parts))->squish()->toString();

        return $name === '' ? null : $name;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function billRefNumber(array $payload): string
    {
        return trim((string) Arr::get($payload, 'BillRefNumber', ''));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function providerTransactionId(array $payload): string
    {
        return trim((string) Arr::get($payload, 'TransID', ''));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function externalEventId(array $payload): string
    {
        $providerTransactionId = $this->providerTransactionId($payload);

        if ($providerTransactionId !== '') {
            return $providerTransactionId;
        }

        return 'unknown-'.hash('sha256', json_encode($payload) ?: '');
    }
}
