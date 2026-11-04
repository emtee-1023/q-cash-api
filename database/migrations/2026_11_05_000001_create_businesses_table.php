<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('business_name', 255);
            $table->string('business_type', 100)->nullable();
            $table->string('business_category', 100)->nullable();
            $table->string('registration_number', 100)->nullable();
            $table->string('phone_number', 30)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('county', 100)->nullable();
            $table->string('town', 100)->nullable();
            $table->text('physical_address')->nullable();
            $table->date('operating_since')->nullable();
            $table->string('status', 30)->default('pending');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
