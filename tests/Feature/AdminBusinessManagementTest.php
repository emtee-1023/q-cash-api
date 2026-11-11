<?php

use App\Enums\BusinessKycStatus;
use App\Enums\BusinessStatus;
use App\Models\Business;
use App\Models\BusinessType;
use App\Models\County;
use App\Models\SubCounty;
use App\Models\User;
use App\Models\Ward;
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

test('admins can update business types', function () {
    $admin = User::factory()->admin()->create();
    $type = BusinessType::factory()->create();
    $business = Business::factory()->create();

    $this->withToken(adminBusinessToken($admin))
        ->patchJson("/api/admin/businesses/{$business->id}", [
            'business_type_id' => $type->id,
        ])->assertOk()
        ->assertJsonPath('data.business_type_id', $type->id);

    expect($business->refresh()->business_type_id)->toBe($type->id);
});

test('admins can update full business details including registration number', function () {
    $admin = User::factory()->admin()->create();
    $type = BusinessType::factory()->create();
    $county = County::factory()->create();
    $subCounty = SubCounty::factory()->create(['county_id' => $county->id]);
    $ward = Ward::factory()->create(['sub_county_id' => $subCounty->id]);
    $business = Business::factory()->create(['has_physical_location' => false]);

    $this->withToken(adminBusinessToken($admin))
        ->patchJson("/api/admin/businesses/{$business->id}", [
            'business_name' => 'Admin Updated Business',
            'business_type_id' => $type->id,
            'registration_number' => 'REG-ADMIN-001',
            'phone_number' => '0712345678',
            'email' => 'admin-updated@example.com',
            'has_physical_location' => true,
            'county_id' => $county->id,
            'sub_county_id' => $subCounty->id,
            'ward_id' => $ward->id,
            'physical_address' => '12 Market Street',
        ])->assertOk()
        ->assertJsonPath('data.business_name', 'Admin Updated Business')
        ->assertJsonPath('data.business_type_id', $type->id)
        ->assertJsonPath('data.registration_number', 'REG-ADMIN-001')
        ->assertJsonPath('data.phone_number', '254712345678')
        ->assertJsonPath('data.email', 'admin-updated@example.com')
        ->assertJsonPath('data.county_id', $county->id)
        ->assertJsonPath('data.sub_county_id', $subCounty->id)
        ->assertJsonPath('data.ward_id', $ward->id);

    $business->refresh();

    expect($business->business_name)->toBe('Admin Updated Business');
    expect($business->business_type_id)->toBe($type->id);
    expect($business->registration_number)->toBe('REG-ADMIN-001');
    expect($business->phone_number)->toBe('254712345678');
    expect($business->email)->toBe('admin-updated@example.com');
    expect($business->county_id)->toBe($county->id);
    expect($business->sub_county_id)->toBe($subCounty->id);
    expect($business->ward_id)->toBe($ward->id);
});

test('admin details patch does not change status or kyc status', function () {
    $admin = User::factory()->admin()->create();
    $business = Business::factory()->create([
        'status' => BusinessStatus::Pending->value,
        'kyc_status' => BusinessKycStatus::Unverified->value,
    ]);

    $this->withToken(adminBusinessToken($admin))
        ->patchJson("/api/admin/businesses/{$business->id}", [
            'business_name' => 'Details Only',
            'status' => BusinessStatus::Suspended->value,
            'kyc_status' => BusinessKycStatus::Verified->value,
        ])->assertOk();

    $business->refresh();

    expect($business->business_name)->toBe('Details Only');
    expect($business->status)->toBe(BusinessStatus::Pending);
    expect($business->kyc_status)->toBe(BusinessKycStatus::Unverified);
});
