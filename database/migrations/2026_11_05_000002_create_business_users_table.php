<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses');
            $table->foreignId('user_id')->constrained('users');
            $table->string('role', 30)->default('owner');
            $table->boolean('is_primary_contact')->default(false);
            $table->string('status', 30)->default('active');
            $table->timestamps();

            $table->unique(['business_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_users');
    }
};
