<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Support\PhoneNumber;
use Laravel\Sanctum\NewAccessToken;
use Markt\LaravelAuth\Services\AuthService as BaseAuthService;

class AuthService extends BaseAuthService
{
    public function register(string $phoneNumber, string $password, array $attributes = [])
    {
        return parent::register(PhoneNumber::normalize($phoneNumber), $password, $attributes);
    }

    public function verifyPhone(string $phoneNumber, string $otp): bool
    {
        return parent::verifyPhone(PhoneNumber::normalize($phoneNumber), $otp);
    }

    /**
     * Verify a registration OTP and immediately issue a Sanctum token so the
     * user is logged in without having to submit their credentials again.
     *
     * @return array{user: User, token: NewAccessToken}|null
     */
    public function verifyPhoneAndIssueToken(string $phoneNumber, string $otp): ?array
    {
        $phoneNumber = PhoneNumber::normalize($phoneNumber);

        if (! parent::verifyPhone($phoneNumber, $otp)) {
            return null;
        }

        $userModel = config('laravel-auth.models.user');

        $user = $userModel::where('phone_number', $phoneNumber)->first();

        if ($user === null) {
            return null;
        }

        return [
            'user' => $user,
            'token' => $user->createToken('auth-token'),
        ];
    }

    public function login(string $phoneNumber, string $password): array
    {
        return parent::login(PhoneNumber::normalize($phoneNumber), $password);
    }

    public function requestPasswordReset(string $phoneNumber): void
    {
        parent::requestPasswordReset(PhoneNumber::normalize($phoneNumber));
    }

    public function resendOtp(string $purpose, ?string $phoneNumber = null, ?string $challengeToken = null): bool
    {
        return parent::resendOtp(
            $purpose,
            $phoneNumber === null ? null : PhoneNumber::normalize($phoneNumber),
            $challengeToken,
        );
    }

    public function resetPassword(string $phoneNumber, string $otp, string $password): bool
    {
        return parent::resetPassword(PhoneNumber::normalize($phoneNumber), $otp, $password);
    }
}
