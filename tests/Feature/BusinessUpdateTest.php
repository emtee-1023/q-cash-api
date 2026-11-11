<?php

use App\Models\Business;
use App\Models\BusinessType;
use App\Models\County;
use App\Models\SubCounty;
use App\Models\User;
use App\Models\Ward;
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
        ->assertJsonPath('message', 'Business updated.');

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

test('other business fields cannot be updated through this endpoint', function () {
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

test('at least one business field is required when patching', function () {
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

test('a business owner can update the business type', function () {
    $owner = User::factory()->create();
    $type = BusinessType::factory()->create();
    $business = Business::factory()->create();
    $business->businessUsers()->create([
        'user_id' => $owner->id,
        'role' => 'owner',
        'is_primary_contact' => true,
        'status' => 'active',
    ]);

    Sanctum::actingAs($owner);

    $this->patchJson("/api/businesses/{$business->id}", [
        'business_type_id' => $type->id,
    ])->assertOk()
        ->assertJsonPath('data.business_type_id', $type->id);

    expect($business->refresh()->business_type_id)->toBe($type->id);
});

test('a business administrator can update the business type', function () {
    $administrator = User::factory()->create();
    $type = BusinessType::factory()->create();
    $business = Business::factory()->create();
    $business->businessUsers()->create([
        'user_id' => $administrator->id,
        'role' => 'administrator',
        'is_primary_contact' => false,
        'status' => 'active',
    ]);

    Sanctum::actingAs($administrator);

    $this->patchJson("/api/businesses/{$business->id}", [
        'business_type_id' => $type->id,
    ])->assertOk();

    expect($business->refresh()->business_type_id)->toBe($type->id);
});

test('a business administrator can update the business name', function () {
    $administrator = User::factory()->create();
    $business = Business::factory()->create(['business_name' => 'Old Name']);
    $business->businessUsers()->create([
        'user_id' => $administrator->id,
        'role' => 'administrator',
        'is_primary_contact' => false,
        'status' => 'active',
    ]);

    Sanctum::actingAs($administrator);

    $this->patchJson("/api/businesses/{$business->id}", [
        'business_name' => 'New Name',
    ])->assertOk();

    expect($business->refresh()->business_name)->toBe('New Name');
});

test('a staff member cannot update the business type', function () {
    $staff = User::factory()->create();
    $type = BusinessType::factory()->create();
    $business = Business::factory()->create();
    $business->businessUsers()->create([
        'user_id' => $staff->id,
        'role' => 'staff',
        'is_primary_contact' => false,
        'status' => 'active',
    ]);

    Sanctum::actingAs($staff);

    $this->patchJson("/api/businesses/{$business->id}", [
        'business_type_id' => $type->id,
    ])->assertForbidden();
});

test('an inactive business type cannot be used when patching', function () {
    $owner = User::factory()->create();
    $type = BusinessType::factory()->create(['is_active' => false]);
    $business = Business::factory()->create();
    $business->businessUsers()->create([
        'user_id' => $owner->id,
        'role' => 'owner',
        'is_primary_contact' => true,
        'status' => 'active',
    ]);

    Sanctum::actingAs($owner);

    $this->patchJson("/api/businesses/{$business->id}", [
        'business_type_id' => $type->id,
    ])->assertStatus(422)
        ->assertJsonValidationErrors('business_type_id');
});

test('an owner can update the full business profile in one patch', function () {
    $owner = User::factory()->create();
    $type = BusinessType::factory()->create();
    $county = County::factory()->create();
    $subCounty = SubCounty::factory()->create(['county_id' => $county->id]);
    $ward = Ward::factory()->create(['sub_county_id' => $subCounty->id]);
    $business = Business::factory()->create(['has_physical_location' => false]);
    $business->businessUsers()->create([
        'user_id' => $owner->id,
        'role' => 'owner',
        'is_primary_contact' => true,
        'status' => 'active',
    ]);

    Sanctum::actingAs($owner);

    $this->patchJson("/api/businesses/{$business->id}", [
        'business_name' => 'Updated Business',
        'business_type_id' => $type->id,
        'phone_number' => '0712345678',
        'email' => 'business@example.com',
        'has_physical_location' => true,
        'county_id' => $county->id,
        'sub_county_id' => $subCounty->id,
        'ward_id' => $ward->id,
        'physical_address' => '12 Market Street',
    ])->assertOk()
        ->assertJsonPath('data.business_name', 'Updated Business')
        ->assertJsonPath('data.business_type_id', $type->id)
        ->assertJsonPath('data.phone_number', '254712345678')
        ->assertJsonPath('data.email', 'business@example.com')
        ->assertJsonPath('data.has_physical_location', true)
        ->assertJsonPath('data.county_id', $county->id)
        ->assertJsonPath('data.sub_county_id', $subCounty->id)
        ->assertJsonPath('data.ward_id', $ward->id)
        ->assertJsonPath('data.physical_address', '12 Market Street');

    $business->refresh();

    expect($business->business_name)->toBe('Updated Business');
    expect($business->business_type_id)->toBe($type->id);
    expect($business->phone_number)->toBe('254712345678');
    expect($business->email)->toBe('business@example.com');
    expect($business->county_id)->toBe($county->id);
    expect($business->sub_county_id)->toBe($subCounty->id);
    expect($business->ward_id)->toBe($ward->id);
});

test('switching to online clears location details but keeps other profile fields', function () {
    $owner = User::factory()->create();
    $county = County::factory()->create();
    $subCounty = SubCounty::factory()->create(['county_id' => $county->id]);
    $ward = Ward::factory()->create(['sub_county_id' => $subCounty->id]);
    $business = Business::factory()->create([
        'has_physical_location' => true,
        'county_id' => $county->id,
        'sub_county_id' => $subCounty->id,
        'ward_id' => $ward->id,
        'county' => $county->name,
        'town' => $subCounty->name,
        'physical_address' => '12 Market Street',
    ]);
    $business->businessUsers()->create([
        'user_id' => $owner->id,
        'role' => 'owner',
        'is_primary_contact' => true,
        'status' => 'active',
    ]);

    Sanctum::actingAs($owner);

    $this->patchJson("/api/businesses/{$business->id}", [
        'has_physical_location' => false,
        'phone_number' => '0722000000',
        'email' => 'online@example.com',
    ])->assertOk()
        ->assertJsonPath('data.has_physical_location', false)
        ->assertJsonPath('data.county_id', null)
        ->assertJsonPath('data.sub_county_id', null)
        ->assertJsonPath('data.ward_id', null)
        ->assertJsonPath('data.physical_address', null)
        ->assertJsonPath('data.phone_number', '254722000000')
        ->assertJsonPath('data.email', 'online@example.com');

    $business->refresh();

    expect($business->has_physical_location)->toBeFalse();
    expect($business->county_id)->toBeNull();
    expect($business->sub_county_id)->toBeNull();
    expect($business->ward_id)->toBeNull();
    expect($business->county)->toBeNull();
    expect($business->town)->toBeNull();
    expect($business->physical_address)->toBeNull();
    expect($business->phone_number)->toBe('254722000000');
    expect($business->email)->toBe('online@example.com');
});

test('changing county without a sub county clears the old hierarchy', function () {
    $owner = User::factory()->create();
    $oldCounty = County::factory()->create();
    $newCounty = County::factory()->create();
    $oldSubCounty = SubCounty::factory()->create(['county_id' => $oldCounty->id]);
    $oldWard = Ward::factory()->create(['sub_county_id' => $oldSubCounty->id]);
    $business = Business::factory()->create([
        'county_id' => $oldCounty->id,
        'sub_county_id' => $oldSubCounty->id,
        'ward_id' => $oldWard->id,
        'county' => $oldCounty->name,
        'town' => $oldSubCounty->name,
    ]);
    $business->businessUsers()->create([
        'user_id' => $owner->id,
        'role' => 'owner',
        'is_primary_contact' => true,
        'status' => 'active',
    ]);

    Sanctum::actingAs($owner);

    $this->patchJson("/api/businesses/{$business->id}", [
        'county_id' => $newCounty->id,
    ])->assertOk();

    $business->refresh();

    expect($business->county_id)->toBe($newCounty->id);
    expect($business->sub_county_id)->toBeNull();
    expect($business->ward_id)->toBeNull();
    expect($business->town)->toBeNull();
});
