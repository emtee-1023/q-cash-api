<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ActivateBusinessInvitationRequest;
use App\Http\Requests\ResendBusinessInvitationOtpRequest;
use App\Http\Resources\UserResource;
use App\Services\Businesses\BusinessMembershipService;
use Illuminate\Http\JsonResponse;

/**
 * @tags Business Invitations
 */
class BusinessInvitationController extends Controller
{
    public function activate(
        ActivateBusinessInvitationRequest $request,
        BusinessMembershipService $membershipService,
    ): JsonResponse {
        $result = $membershipService->activate(
            phoneNumber: $request->string('phone_number')->toString(),
            otp: $request->string('otp')->toString(),
            password: $request->string('password')->toString(),
            name: $request->input('name'),
        );

        $token = $result['user']->createToken('auth-token');

        return response()->json([
            'message' => 'Invitation accepted successfully.',
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'user' => new UserResource($result['user']),
        ], 201);
    }

    public function resendOtp(
        ResendBusinessInvitationOtpRequest $request,
        BusinessMembershipService $membershipService,
    ): JsonResponse {
        $membershipService->resendOtp(
            phoneNumber: $request->string('phone_number')->toString(),
        );

        return response()->json([
            'message' => 'If an invitation exists, a new verification code has been sent.',
        ]);
    }
}
