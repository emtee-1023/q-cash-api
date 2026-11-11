<?php

namespace Database\Seeders;

use App\Models\County;
use App\Models\SubCounty;
use App\Models\Ward;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Seeds Kenyan counties, sub-counties/constituencies, and wards.
 *
 * Source: kenya-locations npm package (MIT), https://github.com/davidamunga/kenya-locations
 */
class LocationSeeder extends Seeder
{
    public function run(): void
    {
        /** @var array{counties: array<int, array<string, mixed>>, sub_counties: array<int, array<string, mixed>>, wards: array<int, array<string, mixed>>} $locations */
        $locations = json_decode(
            File::get(database_path('data/kenya_locations.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        DB::transaction(function () use ($locations): void {
            foreach ($locations['counties'] as $county) {
                County::updateOrCreate(
                    ['code' => $county['code']],
                    [
                        'name' => $county['name'],
                        'slug' => Str::slug($county['name']),
                        'capital' => $county['capital'],
                        'region' => $county['region'],
                        'iso_code' => $county['iso_code'],
                        'postal_code' => $county['postal_code'],
                    ],
                );
            }

            $countyIds = County::query()->pluck('id', 'code');

            foreach ($locations['sub_counties'] as $subCounty) {
                SubCounty::updateOrCreate(
                    ['code' => $subCounty['code']],
                    [
                        'county_id' => $countyIds[$subCounty['county_code']],
                        'name' => $subCounty['name'],
                        'slug' => Str::slug($subCounty['name']),
                    ],
                );
            }

            $subCountyIds = SubCounty::query()->pluck('id', 'code');

            foreach ($locations['wards'] as $ward) {
                Ward::updateOrCreate(
                    ['code' => $ward['code']],
                    [
                        'sub_county_id' => $subCountyIds[$ward['sub_county_code']],
                        'name' => $ward['name'],
                        'slug' => Str::slug($ward['name']),
                    ],
                );
            }
        });
    }
}
