<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Markt\LaravelAuth\Services\AuthService;

/**
 * @tags Auth
 */
class RegisterController extends Controller
{
    public function __construct(private AuthService $authService) {}

    /**
     * Create User Account
     *
     * Creates a phone number-based account, stores the optional email and KYC details,
     * and sends an OTP via sms for phone verification.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $attributes = [
            'name' => $request->string('name')->toString(),
            'id_type' => $request->string('id_type')->toString(),
            'identification_number' => $request->string('identification_number')->toString(),
            'date_of_birth' => Carbon::createFromFormat(
                'dmY',
                $request->string('date_of_birth')->toString()
            )->toDateString(),
        ];

        if ($request->filled('email')) {
            $attributes['email'] = $request->string('email')->toString();
        }

        $user = $this->authService->register(
            phoneNumber: $request->string('phone_number')->toString(),
            password: $request->string('password')->toString(),
            attributes: $attributes,
        );

        return response()->json([
            'message' => 'Registration successful. Please verify your phone number.',
            'user' => new UserResource($user),
        ], 201);
    }
}
