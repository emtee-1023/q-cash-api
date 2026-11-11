<?php

use App\Enums\BusinessKycStatus;
use App\Models\Business;
use App\Models\BusinessType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('creating a business attaches the authenticated user as owner', function () {
    $user = User::factory()->create();
    $businessType = BusinessType::factory()->create();
    config(['services.mpesa.paybill_number' => '456456']);
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/businesses', [
        'business_name' => 'Fresh Market',
        'business_type_id' => $businessType->id,
        'county' => 'Nairobi',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.business_type_id', $businessType->id)
        ->assertJsonPath('data.business_type.slug', $businessType->slug)
        ->assertJsonPath('data.kyc_status', 'unverified')
        ->assertJsonPath('data.has_physical_location', true)
        ->assertJsonPath('data.paybill_number', '456456');

    $business = Business::query()->first();

    expect($business->business_name)->toBe('Fresh Market');
    expect($business->business_type_id)->toBe($businessType->id);
    expect($business->kyc_status)->toBe(BusinessKycStatus::Unverified);
    expect($business->has_physical_location)->toBeTrue();
    expect($business->collection_account_number)->toMatch('/^[1-9]\d{5}$/');
    expect($response->json('data.collection_account_number'))->toBe($business->collection_account_number);
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

    config(['services.mpesa.paybill_number' => '456456']);
    Sanctum::actingAs($user);

    $this->getJson("/api/businesses/{$business->id}")
        ->assertOk()
        ->assertJsonPath('data.business_name', $business->business_name)
        ->assertJsonPath('data.paybill_number', '456456');
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
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.collection_account_number', $business->collection_account_number)
        ->assertJsonMissingPath('data.0.paybill_number');
});
