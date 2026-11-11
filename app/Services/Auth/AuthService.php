<?php

namespace App\Services\Auth;

use App\Support\PhoneNumber;
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
