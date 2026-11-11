<?php

use App\Models\User;
use App\Support\AdminToken;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
