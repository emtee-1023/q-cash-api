<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Markt\LaravelAuth\Http\Requests\VerifyPhoneRequest;

/**
 * @tags Auth
 */
class VerifyPhoneController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    /**
     * Verify Phone Number
     *
     * Confirms the registration OTP and returns a bearer token so the newly
     * registered user is logged in immediately.
     */
    public function __invoke(VerifyPhoneRequest $request): JsonResponse
    {
        $result = $this->authService->verifyPhoneAndIssueToken(
            phoneNumber: $request->string('phone_number')->toString(),
            otp: $request->string('otp')->toString(),
        );

        if ($result === null) {
            return response()->json([
                'message' => 'Invalid or expired verification code.',
            ], 422);
        }

        return response()->json([
            'message' => 'Phone number verified successfully.',
            'user' => new UserResource($result['user']),
            'token' => $result['token']->plainTextToken,
            'token_type' => 'Bearer',
        ]);
    }
}
