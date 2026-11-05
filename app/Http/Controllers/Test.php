<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendSmsRequest;
use Illuminate\Support\Facades\Http;

/**
 * @tags Test
 */
class Test extends Controller
{
    /**
     * Test endpoint
     *
     * Returns sample data from a mock external API.
     *
     * @unauthenticated
     */
    public function test()
    {
        $baseUrl = 'https://fake-json-api.mock.beeceptor.com';

        $response = Http::get($baseUrl.'/users');

        return response()->json([
            'success' => true,
            'message' => 'retrieved successfully',
            'data' => $response->json(),
        ], 200);
    }

    /**
     * Send Africa's Talking SMS
     *
     * Sends an SMS to the configured sandbox phone number.
     *
     * @unauthenticated
     */
    public function sendSms(SendSmsRequest $request)
    {
        $baseUrl = env('AFRICASTALKING_ENDPOINT');
        $userName = env('AFRICASTALKING_USERNAME');
        $apiKey = env('AFRICASTALKING_API_KEY');
        $validatedData = $request->validated();

        $payload = [
            'username' => $userName,
            'message' => $validatedData['message'],
            'to' => '+254792314330',
        ];

        $response = Http::asForm()
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/x-www-form-urlencoded',
                'apiKey' => $apiKey,
            ])
            ->post($baseUrl, $payload);

        if ($response->failed()) {
            return response()->json([
                'success' => false,
                'message' => 'External API Delivery Failed',
                'status_code' => $response->status(),
                'error_payload' => $response->json(),
            ], $response->status());
        }

        return response()->json([
            'success' => true,
            'message' => 'Process Executed Successfuly',
            'data' => $response->json(),
        ], $response->status());
    }
}
