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

test('a physical business stores county sub county and ward', function () {
    $user = User::factory()->create();
    $businessType = BusinessType::factory()->create();
    $county = County::factory()->create();
    $subCounty = SubCounty::factory()->create(['county_id' => $county->id]);
    $ward = Ward::factory()->create(['sub_county_id' => $subCounty->id]);

    Sanctum::actingAs($user);

    $this->postJson('/api/businesses', [
        'business_name' => 'Physical Shop',
        'business_type_id' => $businessType->id,
        'has_physical_location' => true,
        'county_id' => $county->id,
        'sub_county_id' => $subCounty->id,
        'ward_id' => $ward->id,
        'physical_address' => '12 Market Street',
    ])->assertCreated()
        ->assertJsonPath('data.has_physical_location', true)
        ->assertJsonPath('data.county_id', $county->id)
        ->assertJsonPath('data.county', $county->name)
        ->assertJsonPath('data.sub_county_id', $subCounty->id)
        ->assertJsonPath('data.town', $subCounty->name)
        ->assertJsonPath('data.ward_id', $ward->id)
        ->assertJsonPath('data.ward', $ward->name);

    $business = Business::query()->firstOrFail();

    expect($business->has_physical_location)->toBeTrue();
    expect($business->countyLocation?->name)->toBe($county->name);
    expect($business->subCounty?->name)->toBe($subCounty->name);
});

test('an online business stores false and clears location details', function () {
    $user = User::factory()->create();
    $businessType = BusinessType::factory()->create();
    $county = County::factory()->create();
    $subCounty = SubCounty::factory()->create(['county_id' => $county->id]);
    $ward = Ward::factory()->create(['sub_county_id' => $subCounty->id]);

    Sanctum::actingAs($user);

    $this->postJson('/api/businesses', [
        'business_name' => 'Online Shop',
        'business_type_id' => $businessType->id,
        'has_physical_location' => false,
        'county_id' => $county->id,
        'sub_county_id' => $subCounty->id,
        'ward_id' => $ward->id,
        'physical_address' => '12 Market Street',
    ])->assertCreated()
        ->assertJsonPath('data.has_physical_location', false)
        ->assertJsonPath('data.county_id', null)
        ->assertJsonPath('data.sub_county_id', null)
        ->assertJsonPath('data.ward_id', null)
        ->assertJsonPath('data.county', null)
        ->assertJsonPath('data.town', null)
        ->assertJsonPath('data.physical_address', null);

    $business = Business::query()->firstOrFail();

    expect($business->has_physical_location)->toBeFalse();
    expect($business->county_id)->toBeNull();
    expect($business->sub_county_id)->toBeNull();
    expect($business->ward_id)->toBeNull();
    expect($business->physical_address)->toBeNull();
});

test('a sub county must belong to the selected county', function () {
    $user = User::factory()->create();
    $businessType = BusinessType::factory()->create();
    $nairobi = County::factory()->create();
    $mombasa = County::factory()->create();
    $langata = SubCounty::factory()->create(['county_id' => $nairobi->id]);

    Sanctum::actingAs($user);

    $this->postJson('/api/businesses', [
        'business_name' => 'Mismatched Shop',
        'business_type_id' => $businessType->id,
        'has_physical_location' => true,
        'county_id' => $mombasa->id,
        'sub_county_id' => $langata->id,
    ])->assertStatus(422)
        ->assertJsonValidationErrors('sub_county_id');
});

test('a ward must belong to the selected sub county', function () {
    $user = User::factory()->create();
    $businessType = BusinessType::factory()->create();
    $county = County::factory()->create();
    $langata = SubCounty::factory()->create(['county_id' => $county->id]);
    $kibra = SubCounty::factory()->create(['county_id' => $county->id]);
    $lindi = Ward::factory()->create(['sub_county_id' => $kibra->id]);

    Sanctum::actingAs($user);

    $this->postJson('/api/businesses', [
        'business_name' => 'Mismatched Ward Shop',
        'business_type_id' => $businessType->id,
        'has_physical_location' => true,
        'county_id' => $county->id,
        'sub_county_id' => $langata->id,
        'ward_id' => $lindi->id,
    ])->assertStatus(422)
        ->assertJsonValidationErrors('ward_id');
});

test('businesses have a physical location by default', function () {
    $business = Business::factory()->create();

    expect($business->has_physical_location)->toBeTrue();
});
