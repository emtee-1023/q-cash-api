<?php

namespace App\Services\Admin;

use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminActivationService
{
    public function __construct(private AdminOtpService $otpService) {}

    public function grant(User $user): void
    {
        $user->forceFill([
            'is_admin' => true,
            'admin_activated_at' => null,
        ])->save();

        $this->otpService->send($user->phone_number);
    }

    public function resendOtp(string $phoneNumber): void
    {
        $phoneNumber = PhoneNumber::normalize($phoneNumber);

        $user = User::query()
            ->where('phone_number', $phoneNumber)
            ->where('is_admin', true)
            ->whereNull('admin_activated_at')
            ->first();

        if ($user !== null) {
            $this->otpService->send($phoneNumber);
        }
    }

    public function activate(string $phoneNumber, string $otp, string $password): bool
    {
        $phoneNumber = PhoneNumber::normalize($phoneNumber);

        $user = User::query()
            ->where('phone_number', $phoneNumber)
            ->first();

        if (! $user || ! $user->is_admin || $user->admin_activated_at !== null) {
            return false;
        }

        return DB::transaction(function () use ($user, $phoneNumber, $otp, $password): bool {
            if (! $this->otpService->verify($phoneNumber, $otp)) {
                return false;
            }

            $user->forceFill([
                'password' => Hash::make($password),
                'phone_verified_at' => $user->phone_verified_at ?? now(),
                'admin_activated_at' => now(),
            ])->save();

            return true;
        });
    }

    public function revoke(User $user): void
    {
        $user->forceFill([
            'is_admin' => false,
            'admin_activated_at' => null,
        ])->save();

        if ($user->phone_number !== null) {
            $this->otpService->invalidate($user->phone_number);
        }
    }
}
