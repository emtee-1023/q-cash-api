<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->backfillLocationIds();

        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn([
                'business_type',
                'business_category',
                'county',
                'town',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('business_type', 100)->nullable();
            $table->string('business_category', 100)->nullable();
            $table->string('county', 100)->nullable();
            $table->string('town', 100)->nullable();
        });
    }

    private function backfillLocationIds(): void
    {
        DB::table('businesses')
            ->whereNull('county_id')
            ->whereNotNull('county')
            ->orderBy('id')
            ->each(function (object $business): void {
                $countyId = DB::table('counties')
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower((string) $business->county)])
                    ->value('id');

                if ($countyId === null) {
                    return;
                }

                $subCountyId = $business->town !== null
                    ? DB::table('sub_counties')
                        ->where('county_id', $countyId)
                        ->whereRaw('LOWER(name) = ?', [mb_strtolower((string) $business->town)])
                        ->value('id')
                    : null;

                DB::table('businesses')
                    ->where('id', $business->id)
                    ->update([
                        'county_id' => $countyId,
                        'sub_county_id' => $subCountyId,
                    ]);
            });
    }
};
