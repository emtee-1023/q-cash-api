<?php

namespace App\Services\Businesses;

use App\Models\Business;
use App\Models\County;
use App\Models\SubCounty;

class UpdateBusinessProfile
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Business $business, array $attributes): Business
    {
        $updates = [];

        foreach ([
            'business_name',
            'business_type_id',
            'registration_number',
            'phone_number',
            'email',
            'physical_address',
        ] as $field) {
            if (array_key_exists($field, $attributes)) {
                $updates[$field] = $attributes[$field];
            }
        }

        $hasPhysicalLocation = array_key_exists('has_physical_location', $attributes)
            ? filter_var($attributes['has_physical_location'], FILTER_VALIDATE_BOOLEAN)
            : null;

        if ($hasPhysicalLocation === false) {
            $updates['has_physical_location'] = false;
            $updates['county_id'] = null;
            $updates['sub_county_id'] = null;
            $updates['ward_id'] = null;
            $updates['county'] = null;
            $updates['town'] = null;
            $updates['physical_address'] = null;
        } else {
            if ($hasPhysicalLocation === true) {
                $updates['has_physical_location'] = true;
            }

            if (array_key_exists('county_id', $attributes)) {
                $countyId = $attributes['county_id'];
                $updates['county_id'] = $countyId;
                $updates['county'] = $countyId !== null ? County::find($countyId)?->name : null;

                if ((int) $countyId !== (int) $business->county_id && ! array_key_exists('sub_county_id', $attributes)) {
                    $updates['sub_county_id'] = null;
                    $updates['town'] = null;
                    $updates['ward_id'] = null;
                }
            }

            if (array_key_exists('sub_county_id', $attributes)) {
                $subCountyId = $attributes['sub_county_id'];
                $updates['sub_county_id'] = $subCountyId;
                $updates['town'] = $subCountyId !== null ? SubCounty::find($subCountyId)?->name : null;

                if ((int) $subCountyId !== (int) $business->sub_county_id && ! array_key_exists('ward_id', $attributes)) {
                    $updates['ward_id'] = null;
                }
            }

            if (array_key_exists('ward_id', $attributes)) {
                $updates['ward_id'] = $attributes['ward_id'];
            }
        }

        $business->update($updates);

        return $business;
    }
}
