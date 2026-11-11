<?php

use App\Enums\IdentificationType;
use App\Mail\EmailVerificationCode;
use App\Models\User;
use App\Support\AdminToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function adminUserToken(User $admin): string
{
    return $admin->createToken(AdminToken::NAME, [AdminToken::ABILITY])->plainTextToken;
}

test('admins can list and search users', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create(['name' => 'Searchable User']);

    $this->withToken(adminUserToken($admin))
        ->getJson('/api/admin/users?q=Searchable')
        ->assertOk()
        ->assertJsonFragment(['name' => $user->name]);
});

test('admins cannot revoke their own admin access', function () {
    $admin = User::factory()->admin()->create();

    $this->withToken(adminUserToken($admin))
        ->postJson("/api/admin/users/{$admin->id}/revoke-admin")
        ->assertStatus(422)
        ->assertJsonPath('message', 'You cannot revoke your own admin access.');

    expect($admin->refresh()->is_admin)->toBeTrue();
    expect($admin->admin_activated_at)->not->toBeNull();
});

test('admins can update user profile details', function () {
    Mail::fake();

    $admin = User::factory()->admin()->create();
    $user = User::factory()->create([
        'name' => 'Old Name',
        'email' => 'old@example.com',
        'email_verified_at' => now(),
        'phone_number' => '254700000001',
        'phone_verified_at' => now(),
        'id_type' => IdentificationType::KenyanNationalId->value,
        'identification_number' => '11111111',
        'date_of_birth' => '1990-01-01',
    ]);

    $this->withToken(adminUserToken($admin))
        ->patchJson("/api/admin/users/{$user->id}", [
            'name' => 'New Name',
            'email' => 'New@Example.com',
            'phone_number' => '0712345678',
            'id_type' => IdentificationType::Passport->value,
            'identification_number' => 'P1234567',
            'date_of_birth' => '1991-02-03',
        ])->assertOk()
        ->assertJsonPath('data.name', 'New Name')
        ->assertJsonPath('data.email', 'new@example.com')
        ->assertJsonPath('data.email_verified_at', null)
        ->assertJsonPath('data.phone_number', '254712345678')
        ->assertJsonPath('data.id_type', IdentificationType::Passport->value)
        ->assertJsonPath('data.identification_number', 'P1234567')
        ->assertJsonPath('data.date_of_birth', '1991-02-03');

    $user->refresh();

    expect($user->name)->toBe('New Name');
    expect($user->email)->toBe('new@example.com');
    expect($user->email_verified_at)->toBeNull();
    expect($user->phone_number)->toBe('254712345678');
    expect($user->phone_verified_at)->not->toBeNull();
    expect($user->id_type)->toBe(IdentificationType::Passport);
    expect($user->identification_number)->toBe('P1234567');
    expect($user->date_of_birth?->toDateString())->toBe('1991-02-03');

    Mail::assertSent(EmailVerificationCode::class, fn (EmailVerificationCode $mail) => $mail->hasTo('new@example.com'));
});

test('admin user profile patch cannot change admin access', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $this->withToken(adminUserToken($admin))
        ->patchJson("/api/admin/users/{$user->id}", [
            'name' => 'Updated Name',
            'is_admin' => true,
        ])->assertOk();

    $user->refresh();

    expect($user->name)->toBe('Updated Name');
    expect($user->is_admin)->toBeFalse();
});

test('admin user profile patch validates unique email phone and identification number', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->create([
        'email' => 'taken@example.com',
        'phone_number' => '254700000009',
        'id_type' => IdentificationType::Passport->value,
        'identification_number' => 'P9999999',
    ]);
    $user = User::factory()->create();

    $this->withToken(adminUserToken($admin));

    $this->patchJson("/api/admin/users/{$user->id}", [
        'email' => 'taken@example.com',
    ])->assertStatus(422)->assertJsonValidationErrors('email');

    $this->patchJson("/api/admin/users/{$user->id}", [
        'phone_number' => '254700000009',
    ])->assertStatus(422)->assertJsonValidationErrors('phone_number');

    $this->patchJson("/api/admin/users/{$user->id}", [
        'id_type' => IdentificationType::Passport->value,
        'identification_number' => 'P9999999',
    ])->assertStatus(422)->assertJsonValidationErrors('identification_number');
});

test('admin user profile patch requires at least one field', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $this->withToken(adminUserToken($admin))
        ->patchJson("/api/admin/users/{$user->id}", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');
});

test('normal users cannot patch user profiles through the admin api', function () {
    $user = User::factory()->create();
    $target = User::factory()->create();

    Sanctum::actingAs($user, ['mobile']);

    $this->patchJson("/api/admin/users/{$target->id}", [
        'name' => 'Hacked',
    ])->assertForbidden();
});
