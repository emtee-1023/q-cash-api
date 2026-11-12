<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('the existing users table keeps the phone auth columns', function () {
    expect(Schema::hasColumns('users', ['phone_number', 'phone_verified_at', 'email', 'password']))->toBeTrue();
});

test('the users email column is nullable', function () {
    $user = User::factory()->create(['email' => null]);

    expect($user->email)->toBeNull();
});

test('the laravel-auth package tables are preserved', function () {
    foreach (['otps', 'two_factor_challenges', 'two_factor_settings'] as $table) {
        expect(Schema::hasTable($table))->toBeTrue("Missing auth package table: {$table}");
    }
});

test('sanctum personal access tokens still work', function () {
    $user = User::factory()->create();

    $token = $user->createToken('test-token');

    expect($token->plainTextToken)->toBeString();
    expect($user->tokens()->count())->toBe(1);
});

test('sanctum authentication guards the user endpoint', function () {
    $this->getJson('/api/user')->assertStatus(401);

    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/user')->assertOk()->assertJsonPath('data.id', $user->id);
});

test('a user can authenticate through the package login route', function () {
    $user = User::factory()->create([
        'phone_number' => '+254712345678',
        'phone_verified_at' => now(),
        'password' => 'password',
    ]);

    $response = $this->postJson('/api/auth/login', [
        'phone_number' => '+254712345678',
        'password' => 'password',
    ]);

    expect($response->status())->toBe(200);
});
