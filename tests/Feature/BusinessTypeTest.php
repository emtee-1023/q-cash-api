<?php

use App\Models\Business;
use App\Models\BusinessType;
use App\Models\User;
use Database\Seeders\BusinessTypeSeeder;
use Database\Seeders\QCashSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('business types are seeded with small business benchmarks', function () {
    $this->seed(BusinessTypeSeeder::class);

    expect(BusinessType::query()->count())->toBeGreaterThanOrEqual(15);

    $mamaMboga = BusinessType::query()->where('slug', 'mama-mboga')->first();

    expect($mamaMboga)->not->toBeNull();
    expect($mamaMboga->name)->toBe('Mama Mboga');
    expect($mamaMboga->category)->toBe('food');
    expect($mamaMboga->average_monthly_revenue_minor)->toBe(3000000);
    expect($mamaMboga->is_active)->toBeTrue();
});

test('the business type seeder is idempotent', function () {
    $this->seed(BusinessTypeSeeder::class);
    $count = BusinessType::query()->count();

    $this->seed(BusinessTypeSeeder::class);

    expect(BusinessType::query()->count())->toBe($count);
});

test('the business type seeder backfills existing businesses', function () {
    $business = Business::factory()->create(['business_type_id' => null]);
    $business->forceFill([
        'business_type' => 'retail',
        'business_category' => 'general_store',
    ])->save();

    $this->seed(BusinessTypeSeeder::class);

    $business->refresh();

    expect($business->business_type_id)->not->toBeNull();
    expect($business->businessType->slug)->toBe('general-shop');
});

test('the qcash seeder links every business to a business type', function () {
    $this->seed(QCashSeeder::class);

    expect(Business::query()->count())->toBe(17);
    expect(Business::query()->whereNull('business_type_id')->count())->toBe(0);
    expect(Business::query()->whereHas('businessType')->count())->toBe(17);
});

test('business creation requires a business type', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/businesses', ['business_name' => 'Fresh Market'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('business_type_id');
});

test('a business cannot be created with an inactive business type', function () {
    $businessType = BusinessType::factory()->create(['is_active' => false]);
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/businesses', [
        'business_name' => 'Fresh Market',
        'business_type_id' => $businessType->id,
    ])->assertStatus(422)->assertJsonValidationErrors('business_type_id');
});

test('the business types endpoint lists active types', function () {
    $active = BusinessType::factory()->create(['name' => 'Active Type', 'slug' => 'active-type']);
    BusinessType::factory()->create(['name' => 'Inactive Type', 'slug' => 'inactive-type', 'is_active' => false]);
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/business-types')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $active->id)
        ->assertJsonPath('data.0.slug', 'active-type');
});
