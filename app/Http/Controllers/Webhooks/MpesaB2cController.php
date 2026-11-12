<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Disbursements\MpesaB2cResultService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * @tags M-Pesa Webhooks
 */
class MpesaB2cController extends Controller
{
    public function __construct(private readonly MpesaB2cResultService $results) {}

    public function result(Request $request): JsonResponse
    {
        $payload = $this->payload($request);

        try {
            $this->results->handle($payload);
        } catch (Throwable $exception) {
            report($exception);
        }

        return $this->acknowledge();
    }

    public function timeout(Request $request): JsonResponse
    {
        $payload = $this->payload($request);

        try {
            $this->results->handleTimeout($payload);
        } catch (Throwable $exception) {
            report($exception);
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
}
