<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Withdrawals\MpesaB2cStatusResultService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * @tags M-Pesa Webhooks
 */
class MpesaB2cStatusController extends Controller
{
    public function __construct(
        private readonly MpesaB2cStatusResultService $results,
    ) {}

    public function result(Request $request): JsonResponse
    {
        $payload = $this->payload($request);

        $this->logIncoming('result', $request, $payload);

        try {
            $withdrawal = $this->results->handle($payload);

            Log::info('M-Pesa B2C status result webhook processed.', [
                'withdrawal_id' => $withdrawal?->id,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            Log::error('M-Pesa B2C status result webhook failed.', [
                'exception' => $exception,
                'payload' => $payload,
            ]);
        }

        return $this->acknowledge();
    }

    public function timeout(Request $request): JsonResponse
    {
        $payload = $this->payload($request);

        Log::warning('M-Pesa B2C status timeout webhook received.', [
            'ip' => $request->ip(),
            'payload' => $payload,
        ]);

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

        Log::info("M-Pesa B2C status {$action} webhook received.", [
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
}
