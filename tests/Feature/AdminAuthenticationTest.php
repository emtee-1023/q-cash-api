<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('the admin api requires an admin token', function () {
    $this->getJson('/api/admin/businesses')->assertUnauthorized();
});

test('normal users cannot access the admin api', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user, ['mobile']);

    $this->getJson('/api/admin/businesses')->assertForbidden();
});

test('normal users cannot log in through the admin login endpoint', function () {
    $user = User::factory()->create(['password' => 'password']);

    $this->postJson('/api/admin/login', [
        'phone_number' => $user->phone_number,
        'password' => 'password',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('phone_number');
});

test('pending admins cannot log in through the admin login endpoint', function () {
    $user = User::factory()->pendingAdmin()->create(['password' => 'password']);

    $this->postJson('/api/admin/login', [
        'phone_number' => $user->phone_number,
        'password' => 'password',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('phone_number');
});

test('activated admins can log in and use the admin api', function () {
    $admin = User::factory()->admin()->create(['password' => 'password']);

    $response = $this->postJson('/api/admin/login', [
        'phone_number' => $admin->phone_number,
        'password' => 'password',
    ])->assertOk()
        ->assertJsonStructure(['token', 'token_type', 'user']);

    $token = $response->json('token');

    $this->withToken($token)
        ->getJson('/api/admin/me')
        ->assertOk()
        ->assertJsonPath('data.id', $admin->id);
});

test('normal mobile tokens cannot access the admin api', function () {
    $admin = User::factory()->admin()->create([
        'password' => 'password',
        'phone_verified_at' => now(),
    ]);

    $token = $this->postJson('/api/auth/login', [
        'phone_number' => $admin->phone_number,
        'password' => 'password',
    ])->assertOk()->json('token');

    $this->withToken($token)
        ->getJson('/api/admin/businesses')
        ->assertForbidden();
});

test('admins can still use the mobile api as normal users', function () {
    $admin = User::factory()->admin()->create([
        'password' => 'password',
        'phone_verified_at' => now(),
    ]);

    $this->postJson('/api/auth/login', [
        'phone_number' => $admin->phone_number,
        'password' => 'password',
    ])->assertOk()
        ->assertJsonStructure(['token', 'token_type']);
});

test('admin flags are hidden from the mobile user response', function () {
    $admin = User::factory()->admin()->create();

    Sanctum::actingAs($admin);

    $this->getJson('/api/auth/user')
        ->assertOk()
        ->assertJsonMissingPath('user.is_admin')
        ->assertJsonMissingPath('user.admin_activated_at');
});

test('admin logout revokes the admin token', function () {
    $admin = User::factory()->admin()->create(['password' => 'password']);

    $token = $this->postJson('/api/admin/login', [
        'phone_number' => $admin->phone_number,
        'password' => 'password',
    ])->assertOk()->json('token');

    $this->withToken($token)
        ->postJson('/api/admin/logout')
        ->assertOk();

    expect($admin->tokens()->count())->toBe(0);
});
