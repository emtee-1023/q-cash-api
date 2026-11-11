<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ActivateAdminRequest;
use App\Http\Requests\Admin\ResendAdminActivationOtpRequest;
use App\Services\Admin\AdminActivationService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

#[Group('Admin Actions')]
class AdminActivationController extends Controller
{
    public function resendOtp(
        ResendAdminActivationOtpRequest $request,
        AdminActivationService $activationService,
    ): JsonResponse {
        $activationService->resendOtp(
            phoneNumber: $request->string('phone_number')->toString(),
        );

        return response()->json([
            'message' => 'If an admin activation is pending, a new verification code has been sent.',
        ]);
    }

    public function store(
        ActivateAdminRequest $request,
        AdminActivationService $activationService,
    ): JsonResponse {
        $activated = $activationService->activate(
            phoneNumber: $request->string('phone_number')->toString(),
            otp: $request->string('otp')->toString(),
            password: $request->string('password')->toString(),
        );

        if (! $activated) {
            throw ValidationException::withMessages([
                'otp' => 'Invalid or expired admin activation code.',
            ]);
        }

        return response()->json([
            'message' => 'Admin account activated. You can now sign in.',
        ]);
    }
}
