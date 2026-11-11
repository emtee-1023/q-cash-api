<?php

namespace App\Services\Auth;

use App\Mail\EmailVerificationCode as EmailVerificationCodeMail;
use App\Models\EmailVerificationCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmailOtpService
{
    public function send(User $user): void
    {
        if (blank($user->email)) {
            throw ValidationException::withMessages([
                'email' => 'Add an email address before requesting verification.',
            ]);
        }

        $email = $this->normalize($user->email);

        EmailVerificationCode::query()
            ->where('user_id', $user->id)
            ->whereNull('verified_at')
            ->update(['verified_at' => now()]);

        $length = (int) config('laravel-auth.otp.length');
        $expiresInMinutes = (int) config('laravel-auth.otp.expires_in');
        $otp = (string) random_int(10 ** ($length - 1), (10 ** $length) - 1);

        EmailVerificationCode::create([
            'user_id' => $user->id,
            'email' => $email,
            'otp_hash' => Hash::make($otp),
            'expires_at' => now()->addMinutes($expiresInMinutes),
            'attempts' => 0,
            'verified_at' => null,
        ]);

        Mail::to($email)->send(new EmailVerificationCodeMail($otp, $expiresInMinutes));
    }

    public function verify(User $user, string $otp): bool
    {
        $email = $this->normalize((string) $user->email);

        $verificationCode = EmailVerificationCode::query()
            ->where('user_id', $user->id)
            ->where('email', $email)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->where('attempts', '<', (int) config('laravel-auth.otp.max_attempts'))
            ->latest()
            ->first();

        if (! $verificationCode) {
            return false;
        }

        $verificationCode->increment('attempts');

        if (! Hash::check($otp, $verificationCode->otp_hash)) {
            return false;
        }

        DB::transaction(function () use ($verificationCode, $user): void {
            $verificationCode->update(['verified_at' => now()]);

            $user->forceFill(['email_verified_at' => now()])->save();
        });

        return true;
    }

    public function normalize(string $email): string
    {
        return Str::lower(trim($email));
    }
}
