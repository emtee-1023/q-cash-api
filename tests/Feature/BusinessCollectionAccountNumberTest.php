<?php

use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('businesses receive a six digit collection account number on creation', function () {
    $business = Business::factory()->create();

    expect($business->collection_account_number)->toMatch('/^[1-9]\d{5}$/');
});

test('collection account numbers are unique', function () {
    $accountNumbers = Business::factory()
        ->count(25)
        ->create()
        ->pluck('collection_account_number');

    expect($accountNumbers->unique()->count())->toBe(25);
});
