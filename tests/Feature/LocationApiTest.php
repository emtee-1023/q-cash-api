<?php

use App\Models\County;
use App\Models\SubCounty;
use App\Models\User;
use App\Models\Ward;
use Database\Seeders\LocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('the location seeder seeds kenyan administrative locations', function () {
    $this->seed(LocationSeeder::class);

    expect(County::query()->count())->toBe(47);
    expect(SubCounty::query()->count())->toBe(302);
    expect(Ward::query()->count())->toBe(1450);

    $nairobi = County::query()->where('name', 'Nairobi')->firstOrFail();
    $langata = $nairobi->subCounties()->where('name', 'Langata')->firstOrFail();

    expect($langata->wards()->where('name', 'Nairobi West')->exists())->toBeTrue();
});

test('the location seeder is idempotent', function () {
    $this->seed(LocationSeeder::class);
    $this->seed(LocationSeeder::class);

    expect(County::query()->count())->toBe(47);
    expect(SubCounty::query()->count())->toBe(302);
    expect(Ward::query()->count())->toBe(1450);
});

test('counties can be listed', function () {
    $first = County::factory()->create();
    $second = County::factory()->create();
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/locations/counties')
        ->assertOk()
        ->assertJsonFragment(['name' => $first->name])
        ->assertJsonFragment(['name' => $second->name]);
});

test('sub counties are filtered by county', function () {
    $nairobi = County::factory()->create();
    $mombasa = County::factory()->create();
    $langata = SubCounty::factory()->create(['county_id' => $nairobi->id]);
    $mvita = SubCounty::factory()->create(['county_id' => $mombasa->id]);
    Sanctum::actingAs(User::factory()->create());

    $this->getJson("/api/locations/counties/{$nairobi->id}/sub-counties")
        ->assertOk()
        ->assertJsonFragment(['name' => $langata->name])
        ->assertJsonMissing(['name' => $mvita->name]);
});

test('wards are filtered by sub county', function () {
    $county = County::factory()->create();
    $langata = SubCounty::factory()->create(['county_id' => $county->id]);
    $kibra = SubCounty::factory()->create(['county_id' => $county->id]);
    $nairobiWest = Ward::factory()->create(['sub_county_id' => $langata->id]);
    $lindi = Ward::factory()->create(['sub_county_id' => $kibra->id]);
    Sanctum::actingAs(User::factory()->create());

    $this->getJson("/api/locations/sub-counties/{$langata->id}/wards")
        ->assertOk()
        ->assertJsonFragment(['name' => $nairobiWest->name])
        ->assertJsonMissing(['name' => $lindi->name]);
});
