<?php

use App\Models\User;
use App\Support\AdminToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Markt\LaravelAuth\Contracts\SmsSender;
use Tests\Fixtures\FakeSmsSender;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->instance(SmsSender::class, new FakeSmsSender);
});

function adminBearerToken(User $admin): string
{
    return $admin->createToken(AdminToken::NAME, [AdminToken::ABILITY])->plainTextToken;
}

test('an admin can grant admin access and send an activation otp', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $this->withToken(adminBearerToken($admin))
        ->postJson(route('admin.users.grant-admin', $user))
        ->assertOk();

    $user->refresh();

    expect($user->is_admin)->toBeTrue();
    expect($user->admin_activated_at)->toBeNull();
    expect(app(SmsSender::class)->lastMessageFor($user->phone_number))
        ->toContain('admin activation code');
});

test('a pending admin can activate their account and set a password', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $this->withToken(adminBearerToken($admin))
        ->postJson(route('admin.users.grant-admin', $user))
        ->assertOk();

    $message = app(SmsSender::class)->lastMessageFor($user->phone_number);
    preg_match('/(\d{6})/', (string) $message, $matches);

    $this->postJson('/api/admin/activate', [
        'phone_number' => $user->phone_number,
        'otp' => $matches[1],
        'password' => 'new-admin-password',
        'password_confirmation' => 'new-admin-password',
    ])->assertOk();

    $user->refresh();

    expect($user->admin_activated_at)->not->toBeNull();
    expect($user->phone_verified_at)->not->toBeNull();
    expect(Hash::check('new-admin-password', $user->password))->toBeTrue();
});

test('activation fails without a valid otp', function () {
    $user = User::factory()->pendingAdmin()->create();

    $this->postJson('/api/admin/activate', [
        'phone_number' => $user->phone_number,
        'otp' => '123456',
        'password' => 'new-admin-password',
        'password_confirmation' => 'new-admin-password',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('otp');

    expect($user->refresh()->admin_activated_at)->toBeNull();
});

test('normal users cannot activate an admin account', function () {
    $user = User::factory()->create();

    $this->postJson('/api/admin/activate', [
        'phone_number' => $user->phone_number,
        'otp' => '123456',
        'password' => 'new-admin-password',
        'password_confirmation' => 'new-admin-password',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('otp');

    expect($user->refresh()->is_admin)->toBeFalse();
});

test('an admin can revoke another admins access', function () {
    $admin = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->create();

    $this->withToken(adminBearerToken($admin))
        ->postJson(route('admin.users.revoke-admin', $otherAdmin))
        ->assertOk();

    $otherAdmin->refresh();

    expect($otherAdmin->is_admin)->toBeFalse();
    expect($otherAdmin->admin_activated_at)->toBeNull();
});

test('the bootstrap command promotes an existing user to activated admin', function () {
    $user = User::factory()->create();

    $this->artisan('admin:promote', ['phone' => $user->phone_number])
        ->assertSuccessful();

    $user->refresh();

    expect($user->is_admin)->toBeTrue();
    expect($user->admin_activated_at)->not->toBeNull();
});
