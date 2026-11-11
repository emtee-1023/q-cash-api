<?php

use App\Enums\IdentificationType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Markt\LaravelAuth\Contracts\SmsSender;
use Markt\LaravelAuth\Enums\OtpPurpose;
use Tests\Fixtures\FakeSmsSender;

uses(RefreshDatabase::class);

function userRegistrationPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Jane Doe',
        'phone_number' => '0712345678',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'id_type' => IdentificationType::KenyanNationalId->value,
        'identification_number' => '12345678',
        'date_of_birth' => '01011990',
    ], $overrides);
}

beforeEach(function () {
    $this->instance(SmsSender::class, new FakeSmsSender);
});

test('a user can register with phone and kyc details without an email', function () {
    $response = $this->postJson('/api/auth/register', userRegistrationPayload());

    $response->assertCreated()
        ->assertJsonPath('user.phone_number', '254712345678')
        ->assertJsonPath('user.id_type', IdentificationType::KenyanNationalId->value)
        ->assertJsonPath('user.identification_number', '12345678')
        ->assertJsonMissingPath('user.password');

    $user = User::query()->first();

    expect($user->email)->toBeNull();
    expect($user->id_type)->toBe(IdentificationType::KenyanNationalId);
    expect($user->identification_number)->toBe('12345678');
    expect($user->date_of_birth->toDateString())->toBe('1990-01-01');
    expect($user->phone_verified_at)->toBeNull();

    $this->assertDatabaseHas('otps', [
        'phone_number' => '254712345678',
        'purpose' => OtpPurpose::Registration->value,
    ]);
});

test('an optional email is stored and normalized to lowercase', function () {
    $response = $this->postJson('/api/auth/register', userRegistrationPayload([
        'email' => 'Jane.Doe@Example.COM',
    ]));

    $response->assertCreated()->assertJsonPath('user.email', 'jane.doe@example.com');
    $this->assertDatabaseHas('users', ['email' => 'jane.doe@example.com']);
});

test('a blank email is stored as null', function () {
    $this->postJson('/api/auth/register', userRegistrationPayload(['email' => '']))->assertCreated();

    expect(User::query()->first()->email)->toBeNull();
});

test('a phone number is required', function () {
    $payload = userRegistrationPayload();
    unset($payload['phone_number']);

    $this->postJson('/api/auth/register', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors('phone_number');
});

test('phone numbers are normalized to the 254 format before storage', function (string $phoneNumber) {
    $this->postJson('/api/auth/register', userRegistrationPayload(['phone_number' => $phoneNumber]))
        ->assertCreated()
        ->assertJsonPath('user.phone_number', '254792314330');

    expect(User::query()->first()->phone_number)->toBe('254792314330');
    $this->assertDatabaseHas('otps', ['phone_number' => '254792314330']);
})->with([
    '+254792314330',
    '0792314330',
    '254792314330',
    '792314330',
    '+254 792 314330',
]);

test('the id type must be a kenyan national id or passport', function () {
    $this->postJson('/api/auth/register', userRegistrationPayload(['id_type' => 'driving_licence']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('id_type');
});

test('a passport is a valid identification type', function () {
    $this->postJson('/api/auth/register', userRegistrationPayload([
        'id_type' => IdentificationType::Passport->value,
        'identification_number' => 'A1234567',
    ]))->assertCreated();

    $this->assertDatabaseHas('users', [
        'id_type' => IdentificationType::Passport->value,
        'identification_number' => 'A1234567',
    ]);
});

test('the date of birth must use the ddmmyyyy format', function () {
    $this->postJson('/api/auth/register', userRegistrationPayload(['date_of_birth' => '1990-01-01']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('date_of_birth');
});

test('the date of birth must be in the past', function () {
    $this->postJson('/api/auth/register', userRegistrationPayload(['date_of_birth' => now()->addDay()->format('dmY')]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('date_of_birth');
});

test('an identification number must be unique for the selected id type', function () {
    User::factory()->create([
        'phone_number' => '254722000000',
        'id_type' => IdentificationType::KenyanNationalId->value,
        'identification_number' => '12345678',
    ]);

    $this->postJson('/api/auth/register', userRegistrationPayload())
        ->assertStatus(422)
        ->assertJsonValidationErrors('identification_number');
});

test('the same identification number can be used with a different id type', function () {
    User::factory()->create([
        'phone_number' => '254722000000',
        'id_type' => IdentificationType::Passport->value,
        'identification_number' => '12345678',
    ]);

    $this->postJson('/api/auth/register', userRegistrationPayload())->assertCreated();
});

test('an email can only be used once', function () {
    User::factory()->create(['email' => 'jane@example.com']);

    $this->postJson('/api/auth/register', userRegistrationPayload(['email' => 'jane@example.com']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');
});

test('a registered user must verify their phone before logging in', function () {
    $this->postJson('/api/auth/register', userRegistrationPayload())->assertCreated();

    $this->postJson('/api/auth/login', [
        'phone_number' => '0712345678',
        'password' => 'password123',
    ])->assertStatus(422);

    $message = app(SmsSender::class)->lastMessageFor('254712345678');
    preg_match('/code is (\d+)/', (string) $message, $matches);

    $this->postJson('/api/auth/verify-phone', [
        'phone_number' => '0712345678',
        'otp' => $matches[1] ?? '',
    ])->assertOk();

    $this->postJson('/api/auth/login', [
        'phone_number' => '0712345678',
        'password' => 'password123',
    ])->assertOk()->assertJsonStructure(['token']);
});
