<?php

namespace App\Services\Businesses;

use Illuminate\Support\Facades\Hash;
use Markt\LaravelAuth\Contracts\SmsSender;

class BusinessInvitationOtpService
{
    private const PURPOSE = 'business_invitation';

    public function __construct(private SmsSender $smsSender) {}

    public function send(string $phoneNumber): void
    {
        $this->invalidate($phoneNumber);

        $otpModel = config('laravel-auth.models.otp');
        $length = (int) config('laravel-auth.otp.length');
        $otp = (string) random_int(10 ** ($length - 1), (10 ** $length) - 1);

        $otpModel::create([
            'phone_number' => $phoneNumber,
            'purpose' => self::PURPOSE,
            'otp_hash' => Hash::make($otp),
            'expires_at' => now()->addMinutes((int) config('laravel-auth.otp.expires_in')),
            'attempts' => 0,
            'verified_at' => null,
        ]);

        $this->smsSender->send(
            $phoneNumber,
            "You have been invited to join a business on QCash. Your verification code is {$otp}"
        );
    }

    public function verify(string $phoneNumber, string $otp): bool
    {
        $otpModel = config('laravel-auth.models.otp');

        $otpRecord = $otpModel::where('phone_number', $phoneNumber)
            ->where('purpose', self::PURPOSE)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->where('attempts', '<', (int) config('laravel-auth.otp.max_attempts'))
            ->latest()
            ->first();

        if (! $otpRecord) {
            return false;
        }

        $otpRecord->increment('attempts');

        if (! Hash::check($otp, $otpRecord->otp_hash)) {
            return false;
        }

        $otpRecord->update(['verified_at' => now()]);

        return true;
    }

    public function invalidate(string $phoneNumber): void
    {
        $otpModel = config('laravel-auth.models.otp');

        $otpModel::where('phone_number', $phoneNumber)
            ->where('purpose', self::PURPOSE)
            ->whereNull('verified_at')
            ->update(['verified_at' => now()]);
    }
}
