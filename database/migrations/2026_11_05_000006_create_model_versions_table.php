<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_versions', function (Blueprint $table) {
            $table->id();
            $table->string('model_name', 100);
            $table->string('model_type', 50);
            $table->string('version', 50);
            $table->string('artifact_uri', 500)->nullable();
            $table->json('evaluation_metrics')->nullable();
            $table->json('training_data_description')->nullable();
            $table->timestamp('trained_at')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->unique(['model_name', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_versions');
    }
};
