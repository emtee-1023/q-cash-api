<?php

use App\Enums\BusinessKycStatus;
use App\Enums\BusinessStatus;
use App\Models\Business;
use App\Models\User;
use App\Support\AdminToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function adminBusinessToken(User $admin): string
{
    return $admin->createToken(AdminToken::NAME, [AdminToken::ABILITY])->plainTextToken;
}

test('admins can list every business', function () {
    $admin = User::factory()->admin()->create();
    $first = Business::factory()->create(['business_name' => 'First Business']);
    $second = Business::factory()->create(['business_name' => 'Second Business']);

    $this->withToken(adminBusinessToken($admin))
        ->getJson('/api/admin/businesses')
        ->assertOk()
        ->assertJsonFragment(['business_name' => $first->business_name])
        ->assertJsonFragment(['business_name' => $second->business_name]);
});

test('admins can view business details', function () {
    $admin = User::factory()->admin()->create();
    $business = Business::factory()->create();

    $this->withToken(adminBusinessToken($admin))
        ->getJson("/api/admin/businesses/{$business->id}")
        ->assertOk()
        ->assertJsonPath('data.business_name', $business->business_name)
        ->assertJsonPath('data.collection_account_number', $business->collection_account_number);
});

test('admins can update business names', function () {
    $admin = User::factory()->admin()->create();
    $business = Business::factory()->create(['business_name' => 'Old Name']);

    $this->withToken(adminBusinessToken($admin))
        ->patchJson("/api/admin/businesses/{$business->id}", [
            'business_name' => 'Admin Renamed',
        ])->assertOk()
        ->assertJsonPath('data.business_name', 'Admin Renamed');

    expect($business->refresh()->business_name)->toBe('Admin Renamed');
});

test('admins can update business status', function () {
    $admin = User::factory()->admin()->create();
    $business = Business::factory()->create();

    $this->withToken(adminBusinessToken($admin))
        ->patchJson("/api/admin/businesses/{$business->id}/status", [
            'status' => BusinessStatus::Suspended->value,
        ])->assertOk()
        ->assertJsonPath('data.status', BusinessStatus::Suspended->value);

    expect($business->refresh()->status)->toBe(BusinessStatus::Suspended);
});

test('admins can update business kyc status', function () {
    $admin = User::factory()->admin()->create();
    $business = Business::factory()->create();

    $this->withToken(adminBusinessToken($admin))
        ->patchJson("/api/admin/businesses/{$business->id}/kyc-status", [
            'kyc_status' => BusinessKycStatus::Verified->value,
        ])->assertOk()
        ->assertJsonPath('data.kyc_status', BusinessKycStatus::Verified->value);

    expect($business->refresh()->kyc_status)->toBe(BusinessKycStatus::Verified);
});

test('normal users cannot manage businesses in the admin api', function () {
    $user = User::factory()->create();
    $business = Business::factory()->create();

    Sanctum::actingAs($user, ['mobile']);

    $this->patchJson("/api/admin/businesses/{$business->id}/status", [
        'status' => BusinessStatus::Suspended->value,
    ])->assertForbidden();
});

test('business status updates reject invalid values', function () {
    $admin = User::factory()->admin()->create();
    $business = Business::factory()->create();

    $this->withToken(adminBusinessToken($admin))
        ->patchJson("/api/admin/businesses/{$business->id}/status", [
            'status' => 'invalid-status',
        ])->assertStatus(422)
        ->assertJsonValidationErrors('status');
});
