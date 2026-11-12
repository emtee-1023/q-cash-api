<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Transactions\MpesaStkPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * @tags M-Pesa Webhooks
 */
class MpesaStkController extends Controller
{
    public function __construct(private readonly MpesaStkPushService $stkPush) {}

    /**
     * Handle an M-Pesa STK push callback
     *
     * Logs the raw callback, matches it to the originating business, records the
     * transaction and notifies the owner. Always acknowledges the callback.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $this->payload($request);

        try {
            $this->stkPush->handleCallback($payload);
        } catch (Throwable $exception) {
            report($exception);
        }

        return response()->json([
            'ResultCode' => '0',
            'ResultDesc' => 'Success',
        ]);
    }

    /**
     * Read the callback body directly so the raw payload is preserved verbatim.
     *
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        $payload = json_decode($request->getContent(), true);

        return is_array($payload) ? $payload : $request->all();
    }
}
