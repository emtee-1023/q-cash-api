<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\VerifyEmailRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\EmailOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @tags Profile
 */
class ProfileController extends Controller
{
    public function update(
        UpdateProfileRequest $request,
        EmailOtpService $otpService,
    ): JsonResponse {
        $user = $request->user();
        $email = $request->string('email')->toString();

        if ($user->email === $email) {
            throw ValidationException::withMessages([
                'email' => 'This is already your email address.',
            ]);
        }

        $user->forceFill([
            'email' => $email,
            'email_verified_at' => null,
        ])->save();

        $otpService->send($user);

        return response()->json([
            'message' => 'Email updated. A verification code has been sent to your new email address.',
            'user' => new UserResource($user),
        ]);
    }

    public function sendEmailVerification(
        Request $request,
        EmailOtpService $otpService,
    ): JsonResponse {
        $user = $request->user();

        if ($user->email_verified_at !== null) {
            return response()->json([
                'message' => 'Your email address is already verified.',
            ], 422);
        }

        $otpService->send($user);

        return response()->json([
            'message' => 'Verification code sent.',
        ]);
    }

    public function verifyEmail(
        VerifyEmailRequest $request,
        EmailOtpService $otpService,
    ): JsonResponse {
        $verified = $otpService->verify(
            user: $request->user(),
            otp: $request->string('otp')->toString(),
        );

        if (! $verified) {
            throw ValidationException::withMessages([
                'otp' => 'Invalid or expired verification code.',
            ]);
        }

        return response()->json([
            'message' => 'Email verified successfully.',
        ]);
    }
}
