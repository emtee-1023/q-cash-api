<?php

use App\Http\Middleware\NormalizePhoneNumberInput;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Markt\LaravelAuth\Contracts\SmsSender;
use Markt\LaravelAuth\Enums\OtpPurpose;
use Tests\Fixtures\FakeSmsSender;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->instance(SmsSender::class, new FakeSmsSender);
});

function phoneTestOtp(string $phoneNumber): string
{
    $message = app(SmsSender::class)->lastMessageFor($phoneNumber);
    preg_match('/code is (\d+)/', (string) $message, $matches);

    return $matches[1] ?? '';
}

function phoneTestRegistrationPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Jane Doe',
        'phone_number' => '254712345678',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'id_type' => 'kenyan_national_id',
        'identification_number' => '12345678',
        'date_of_birth' => '01011990',
    ], $overrides);
}

test('phone number input is normalized before reaching the controller', function () {
    $request = Request::create('/api/test', 'POST', ['phone_number' => '+254 792 314330']);
    $middleware = new NormalizePhoneNumberInput;

    $response = $middleware->handle($request, fn (Request $request) => response()->json([
        'phone_number' => $request->input('phone_number'),
    ]));

    expect($response->getData(true)['phone_number'])->toBe('254792314330');
});

test('phone verification accepts supported phone formats', function (string $phoneNumber) {
    $this->postJson('/api/auth/register', phoneTestRegistrationPayload())->assertCreated();

    $this->postJson('/api/auth/verify-phone', [
        'phone_number' => $phoneNumber,
        'otp' => phoneTestOtp('254712345678'),
    ])->assertOk();

    expect(User::query()->first()->phone_verified_at)->not->toBeNull();
})->with([
    '+254712345678',
    '0712345678',
    '254712345678',
    '712345678',
]);

test('login accepts supported phone formats', function (string $phoneNumber) {
    User::factory()->create([
        'phone_number' => '254712345678',
        'password' => 'password123',
        'phone_verified_at' => now(),
    ]);

    $this->postJson('/api/auth/login', [
        'phone_number' => $phoneNumber,
        'password' => 'password123',
    ])->assertOk()->assertJsonStructure(['token']);
})->with([
    '+254712345678',
    '0712345678',
    '254712345678',
    '712345678',
]);

test('resending a registration otp accepts supported phone formats', function (string $phoneNumber) {
    $this->postJson('/api/auth/register', phoneTestRegistrationPayload())->assertCreated();

    $this->postJson('/api/auth/resend-otp', [
        'purpose' => 'registration',
        'phone_number' => $phoneNumber,
    ])->assertOk();

    expect(app(SmsSender::class)->lastMessageFor('254712345678'))->not->toBeNull();
})->with([
    '+254712345678',
    '0712345678',
    '254712345678',
    '712345678',
]);

test('forgot password accepts supported phone formats', function (string $phoneNumber) {
    User::factory()->create(['phone_number' => '254712345678']);

    $this->postJson('/api/auth/forgot-password', [
        'phone_number' => $phoneNumber,
    ])->assertOk();

    expect(app(SmsSender::class)->lastMessageFor('254712345678'))->not->toBeNull();
    $this->assertDatabaseHas('otps', [
        'phone_number' => '254712345678',
        'purpose' => OtpPurpose::PasswordReset->value,
    ]);
})->with([
    '+254712345678',
    '0712345678',
    '254712345678',
    '712345678',
]);

test('reset password accepts supported phone formats', function (string $phoneNumber) {
    $user = User::factory()->create([
        'phone_number' => '254712345678',
        'password' => 'password123',
    ]);

    $this->postJson('/api/auth/forgot-password', ['phone_number' => '254712345678'])->assertOk();

    $this->postJson('/api/auth/reset-password', [
        'phone_number' => $phoneNumber,
        'otp' => phoneTestOtp('254712345678'),
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ])->assertOk();

    expect(Hash::check('newpassword123', $user->fresh()->password))->toBeTrue();
})->with([
    '+254712345678',
    '0712345678',
    '254712345678',
    '712345678',
]);

test('business phone numbers are normalized before storage', function (string $phoneNumber) {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/businesses', [
        'business_name' => 'Fresh Market',
        'phone_number' => $phoneNumber,
    ])->assertCreated();

    expect(Business::query()->first()->phone_number)->toBe('254792314330');
})->with([
    '+254792314330',
    '0792314330',
    '254792314330',
    '792314330',
]);
