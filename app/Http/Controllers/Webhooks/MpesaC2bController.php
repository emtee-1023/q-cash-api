<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Transactions\MpesaC2bWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * @tags M-Pesa Webhooks
 */
class MpesaC2bController extends Controller
{
    public function __construct(private readonly MpesaC2bWebhookService $mpesa) {}

    /**
     * Validate an M-Pesa C2B payment
     *
     * Called by Safaricom before accepting a paybill payment. Unknown collection
     * account numbers are rejected so payments cannot be attributed incorrectly.
     */
    public function validation(Request $request): JsonResponse
    {
        if (! config('services.mpesa.validation_enabled')) {
            return $this->result('0', 'Accepted');
        }

        if ($this->mpesa->isKnownBillReference($this->payload($request))) {
            return $this->result('0', 'Accepted');
        }

        return $this->result('C2B00012', 'Invalid account number');
    }

    /**
     * Confirm an M-Pesa C2B payment
     *
     * Records the raw callback and creates an inflow transaction for the business
     * matching the collection account number. Always acknowledges the callback so
     * Safaricom does not retry a payment that we have already accepted.
     */
    public function confirmation(Request $request): JsonResponse
    {
        $payload = $this->payload($request);

        try {
            $this->mpesa->handleConfirmation($payload);
        } catch (Throwable $exception) {
            report($exception);
        }

        return $this->result('0', 'Success');
    }

    /**
     * Read the callback body directly so the raw payload is preserved verbatim
     * instead of being normalised by Laravel's request middleware.
     *
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        $payload = json_decode($request->getContent(), true);

        return is_array($payload) ? $payload : $request->all();
    }

    private function result(string $code, string $description): JsonResponse
    {
        return response()->json([
            'ResultCode' => $code,
            'ResultDesc' => $description,
        ]);
    }
}
