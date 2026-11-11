<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Hash;
use Markt\LaravelAuth\Contracts\SmsSender;
use Markt\LaravelAuth\Enums\OtpPurpose;
use Markt\LaravelAuth\Services\OtpService as BaseOtpService;

class OtpService extends BaseOtpService
{
    public function __construct(private SmsSender $smsSender)
    {
        parent::__construct($smsSender);
    }

    public function send(string $phoneNumber, OtpPurpose $purpose): void
    {
        $otpModel = config('laravel-auth.models.otp');

        $otpModel::where('phone_number', $phoneNumber)
            ->where('purpose', $purpose->value)
            ->whereNull('verified_at')
            ->update([
                'verified_at' => now(),
            ]);

        $length = (int) config('laravel-auth.otp.length');
        $otp = (string) random_int(10 ** ($length - 1), (10 ** $length) - 1);

        $otpModel::create([
            'phone_number' => $phoneNumber,
            'purpose' => $purpose->value,
            'otp_hash' => Hash::make($otp),
            'expires_at' => now()->addMinutes((int) config('laravel-auth.otp.expires_in')),
            'attempts' => 0,
            'verified_at' => null,
        ]);

        $this->smsSender->send(
            $phoneNumber,
            "Your verification code is {$otp}"
        );
    }
}
