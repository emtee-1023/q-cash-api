<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminLoginRequest;
use App\Http\Resources\AdminUserResource;
use App\Models\User;
use App\Support\AdminToken;
use App\Support\PhoneNumber;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

#[Group('Admin Actions')]
class AdminLoginController extends Controller
{
    public function login(AdminLoginRequest $request): JsonResponse
    {
        $phoneNumber = PhoneNumber::normalize($request->string('phone_number')->toString());

        $user = User::query()
            ->where('phone_number', $phoneNumber)
            ->first();

        if (! $user || ! Hash::check($request->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages([
                'phone_number' => 'The provided admin credentials are incorrect.',
            ]);
        }

        if (! $user->isActivatedAdmin()) {
            throw ValidationException::withMessages([
                'phone_number' => 'This account cannot access the admin portal.',
            ]);
        }

        $token = $user->createToken(AdminToken::NAME, [AdminToken::ABILITY]);

        return response()->json([
            'message' => 'Admin login successful.',
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'user' => new AdminUserResource($user),
        ]);
    }

    public function me(Request $request): AdminUserResource
    {
        return new AdminUserResource($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Admin logout successful.',
        ]);
    }
}
