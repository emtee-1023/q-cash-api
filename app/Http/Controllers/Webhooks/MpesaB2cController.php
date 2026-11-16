<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Disbursements\MpesaB2cResultService;
use App\Services\Withdrawals\MpesaB2cWithdrawalResultService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * @tags M-Pesa Webhooks
 */
class MpesaB2cController extends Controller
{
    public function __construct(
        private readonly MpesaB2cResultService $results,
        private readonly MpesaB2cWithdrawalResultService $withdrawalResults,
    ) {}

    public function result(Request $request): JsonResponse
    {
        $payload = $this->payload($request);

        $this->logIncoming('result', $request, $payload);

        try {
            $disbursement = $this->results->handle($payload);

            $withdrawal = null;

            if ($disbursement === null) {
                $withdrawal = $this->withdrawalResults->handle($payload);
            }

            $this->logMatched('result', $disbursement?->id, $withdrawal?->id, $payload);
        } catch (Throwable $exception) {
            report($exception);

            Log::error('M-Pesa B2C result webhook failed.', [
                'exception' => $exception,
                'payload' => $payload,
            ]);
        }

        return $this->acknowledge();
    }

    public function timeout(Request $request): JsonResponse
    {
        $payload = $this->payload($request);

        $this->logIncoming('timeout', $request, $payload);

        try {
            $disbursement = $this->results->handleTimeout($payload);

            $withdrawal = null;

            if ($disbursement === null) {
                $withdrawal = $this->withdrawalResults->handleTimeout($payload);
            }

            $this->logMatched('timeout', $disbursement?->id, $withdrawal?->id, $payload);
        } catch (Throwable $exception) {
            report($exception);

            Log::error('M-Pesa B2C timeout webhook failed.', [
                'exception' => $exception,
                'payload' => $payload,
            ]);
        }

        return $this->acknowledge();
    }

    private function acknowledge(): JsonResponse
    {
        return response()->json([
            'ResultCode' => '0',
            'ResultDesc' => 'Success',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        $payload = json_decode($request->getContent(), true);

        return is_array($payload) ? $payload : $request->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function logIncoming(string $action, Request $request, array $payload): void
    {
        $result = Arr::get($payload, 'Result');

        Log::info("M-Pesa B2C {$action} webhook received.", [
            'ip' => $request->ip(),
            'content_type' => $request->header('Content-Type'),
            'originator_conversation_id' => is_array($result) ? Arr::get($result, 'OriginatorConversationID') : null,
            'conversation_id' => is_array($result) ? Arr::get($result, 'ConversationID') : null,
            'result_code' => is_array($result) ? Arr::get($result, 'ResultCode') : null,
            'result_desc' => is_array($result) ? Arr::get($result, 'ResultDesc') : null,
            'payload' => $payload,
            'raw' => $payload === [] ? $request->getContent() : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function logMatched(string $action, ?int $disbursementId, ?int $withdrawalId, array $payload): void
    {
        if ($disbursementId === null && $withdrawalId === null) {
            Log::warning("M-Pesa B2C {$action} webhook did not match a disbursement or withdrawal.", [
                'payload' => $payload,
            ]);

            return;
        }

        Log::info("M-Pesa B2C {$action} webhook matched.", [
            'disbursement_id' => $disbursementId,
            'withdrawal_id' => $withdrawalId,
        ]);
    }
}
