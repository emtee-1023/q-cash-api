<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->boolean('has_physical_location')
                ->default(true)
                ->after('town');

            $table->foreignId('county_id')
                ->nullable()
                ->after('has_physical_location')
                ->constrained('counties')
                ->nullOnDelete();

            $table->foreignId('sub_county_id')
                ->nullable()
                ->after('county_id')
                ->constrained('sub_counties')
                ->nullOnDelete();

            $table->foreignId('ward_id')
                ->nullable()
                ->after('sub_county_id')
                ->constrained('wards')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ward_id');
            $table->dropConstrainedForeignId('sub_county_id');
            $table->dropConstrainedForeignId('county_id');
            $table->dropColumn('has_physical_location');
        });
    }
};
