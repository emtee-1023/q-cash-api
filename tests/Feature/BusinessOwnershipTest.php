<?php

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('creating a business attaches the authenticated user as owner', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/businesses', [
        'business_name' => 'Fresh Market',
        'county' => 'Nairobi',
    ]);

    $response->assertCreated();
    $business = Business::query()->first();

    expect($business->business_name)->toBe('Fresh Market');
    expect($business->businessUsers()->where('user_id', $user->id)->where('role', 'owner')->exists())->toBeTrue();
});

test('an owner can view their business', function () {
    $user = User::factory()->create();
    $business = Business::factory()->create();
    $business->businessUsers()->create([
        'user_id' => $user->id,
        'role' => 'owner',
        'is_primary_contact' => true,
        'status' => 'active',
    ]);

    Sanctum::actingAs($user);

    $this->getJson("/api/businesses/{$business->id}")
        ->assertOk()
        ->assertJsonPath('data.business_name', $business->business_name);
});

test('a non-member cannot view a business', function () {
    $user = User::factory()->create();
    $business = Business::factory()->create();

    Sanctum::actingAs($user);

    $this->getJson("/api/businesses/{$business->id}")->assertForbidden();
});

test('business endpoints require authentication', function () {
    $this->getJson('/api/businesses')->assertUnauthorized();
});

test('a member is listed in the authenticated users businesses', function () {
    $user = User::factory()->create();
    $business = Business::factory()->create();
    $business->businessUsers()->create([
        'user_id' => $user->id,
        'role' => 'owner',
        'is_primary_contact' => true,
        'status' => 'active',
    ]);

    Sanctum::actingAs($user);

    $this->getJson('/api/businesses')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
