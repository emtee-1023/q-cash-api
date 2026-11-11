<?php

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('a business owner can update the business name', function () {
    $owner = User::factory()->create();
    $business = Business::factory()->create(['business_name' => 'Old Name']);
    $business->businessUsers()->create([
        'user_id' => $owner->id,
        'role' => 'owner',
        'is_primary_contact' => true,
        'status' => 'active',
    ]);

    Sanctum::actingAs($owner);

    $this->patchJson("/api/businesses/{$business->id}", [
        'business_name' => 'New Name',
    ])->assertOk()
        ->assertJsonPath('data.business_name', 'New Name')
        ->assertJsonPath('message', 'Business name updated.');

    expect($business->refresh()->business_name)->toBe('New Name');
});

test('a staff member cannot update the business name', function () {
    $staff = User::factory()->create();
    $business = Business::factory()->create();
    $business->businessUsers()->create([
        'user_id' => $staff->id,
        'role' => 'staff',
        'is_primary_contact' => false,
        'status' => 'active',
    ]);

    Sanctum::actingAs($staff);

    $this->patchJson("/api/businesses/{$business->id}", [
        'business_name' => 'New Name',
    ])->assertForbidden();
});

test('a non member cannot update the business name', function () {
    $user = User::factory()->create();
    $business = Business::factory()->create();

    Sanctum::actingAs($user);

    $this->patchJson("/api/businesses/{$business->id}", [
        'business_name' => 'New Name',
    ])->assertForbidden();
});

test('only the business name can be updated through this endpoint', function () {
    $owner = User::factory()->create();
    $business = Business::factory()->create([
        'business_name' => 'Old Name',
        'status' => 'pending',
    ]);
    $business->businessUsers()->create([
        'user_id' => $owner->id,
        'role' => 'owner',
        'is_primary_contact' => true,
        'status' => 'active',
    ]);

    Sanctum::actingAs($owner);

    $this->patchJson("/api/businesses/{$business->id}", [
        'business_name' => 'New Name',
        'status' => 'active',
    ])->assertOk();

    $business->refresh();

    expect($business->business_name)->toBe('New Name');
    expect($business->status->value)->toBe('pending');
});

test('business name is required when patching a business', function () {
    $owner = User::factory()->create();
    $business = Business::factory()->create();
    $business->businessUsers()->create([
        'user_id' => $owner->id,
        'role' => 'owner',
        'is_primary_contact' => true,
        'status' => 'active',
    ]);

    Sanctum::actingAs($owner);

    $this->patchJson("/api/businesses/{$business->id}", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('business_name');
});
