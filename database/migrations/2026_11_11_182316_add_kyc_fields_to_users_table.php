<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('id_type', 30)->nullable();
            $table->string('identification_number', 50)->nullable();
            $table->date('date_of_birth')->nullable();

            $table->unique(['id_type', 'identification_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['id_type', 'identification_number']);
            $table->dropColumn([
                'id_type',
                'identification_number',
                'date_of_birth',
            ]);
        });
    }
};
