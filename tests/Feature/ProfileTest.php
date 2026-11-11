<?php

use App\Mail\EmailVerificationCode as EmailVerificationCodeMail;
use App\Models\EmailVerificationCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
});

test('a user can update their email and it becomes unverified', function () {
    $user = User::factory()->create([
        'name' => 'Original Name',
        'email' => 'old@example.com',
        'email_verified_at' => now(),
    ]);

    Sanctum::actingAs($user);

    $this->patchJson('/api/user', [
        'email' => 'New@Example.com',
        'name' => 'Changed Name',
    ])->assertOk()
        ->assertJsonPath('user.email', 'new@example.com')
        ->assertJsonPath('user.email_verified_at', null);

    $user->refresh();

    expect($user->email)->toBe('new@example.com');
    expect($user->name)->toBe('Original Name');
    expect($user->email_verified_at)->toBeNull();

    Mail::assertSent(EmailVerificationCodeMail::class, fn (EmailVerificationCodeMail $mail) => $mail->hasTo('new@example.com'));
});

test('a user cannot update their email to their current email', function () {
    $user = User::factory()->create(['email' => 'user@example.com']);

    Sanctum::actingAs($user);

    $this->patchJson('/api/user', [
        'email' => 'user@example.com',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('email');
});

test('a user cannot use another users email', function () {
    User::factory()->create(['email' => 'taken@example.com']);
    $user = User::factory()->create(['email' => 'user@example.com']);

    Sanctum::actingAs($user);

    $this->patchJson('/api/user', [
        'email' => 'taken@example.com',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('email');
});

test('a user can request an email verification code', function () {
    $user = User::factory()->create([
        'email' => 'user@example.com',
        'email_verified_at' => null,
    ]);

    Sanctum::actingAs($user);

    $this->postJson('/api/user/email/verification-code')
        ->assertOk();

    Mail::assertSent(EmailVerificationCodeMail::class, fn (EmailVerificationCodeMail $mail) => $mail->hasTo('user@example.com'));

    expect(EmailVerificationCode::query()->where('user_id', $user->id)->count())->toBe(1);
});

test('a verified user cannot request another email verification code', function () {
    $user = User::factory()->create([
        'email' => 'user@example.com',
        'email_verified_at' => now(),
    ]);

    Sanctum::actingAs($user);

    $this->postJson('/api/user/email/verification-code')
        ->assertStatus(422)
        ->assertJsonPath('message', 'Your email address is already verified.');

    Mail::assertNotSent(EmailVerificationCodeMail::class);
});

test('a user can verify their email with the emailed code', function () {
    $user = User::factory()->create([
        'email' => 'user@example.com',
        'email_verified_at' => null,
    ]);

    Sanctum::actingAs($user);

    $this->postJson('/api/user/email/verification-code')->assertOk();

    $code = null;
    Mail::assertSent(EmailVerificationCodeMail::class, function (EmailVerificationCodeMail $mail) use (&$code): bool {
        $code = $mail->code;

        return true;
    });

    $record = EmailVerificationCode::query()->where('user_id', $user->id)->firstOrFail();

    expect($code)->not->toBeNull();
    expect($record->otp_hash)->not->toBe($code);
    expect(Hash::check($code, $record->otp_hash))->toBeTrue();

    $this->postJson('/api/user/email/verify', [
        'otp' => $code,
    ])->assertOk();

    expect($user->refresh()->email_verified_at)->not->toBeNull();
    expect($record->refresh()->verified_at)->not->toBeNull();
});

test('an invalid email verification code is rejected', function () {
    $user = User::factory()->create([
        'email' => 'user@example.com',
        'email_verified_at' => null,
    ]);

    Sanctum::actingAs($user);

    $this->postJson('/api/user/email/verify', [
        'otp' => '123456',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('otp');

    expect($user->refresh()->email_verified_at)->toBeNull();
});

test('changing email invalidates a previously sent code', function () {
    $user = User::factory()->create([
        'email' => 'first@example.com',
        'email_verified_at' => null,
    ]);

    Sanctum::actingAs($user);

    $this->postJson('/api/user/email/verification-code')->assertOk();

    $oldCode = null;
    Mail::assertSent(EmailVerificationCodeMail::class, function (EmailVerificationCodeMail $mail) use (&$oldCode): bool {
        $oldCode = $mail->code;

        return true;
    });

    $this->patchJson('/api/user', ['email' => 'second@example.com'])->assertOk();

    $this->postJson('/api/user/email/verify', [
        'otp' => $oldCode,
    ])->assertStatus(422)
        ->assertJsonValidationErrors('otp');
});
