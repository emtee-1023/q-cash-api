<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_county_id')->constrained('sub_counties')->cascadeOnDelete();
            $table->string('code', 10)->unique();
            $table->string('name', 100);
            $table->string('slug', 120);
            $table->timestamps();

            $table->unique(['sub_county_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wards');
    }
};
