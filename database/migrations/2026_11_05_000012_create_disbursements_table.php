<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disbursements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advance_id')->constrained('advances');
            $table->string('provider', 50)->nullable();
            $table->string('provider_reference', 150)->nullable();
            $table->bigInteger('amount_minor');
            $table->string('status', 30)->default('pending');
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('provider_response')->nullable();
            $table->timestamps();

            $table->unique('provider_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disbursements');
    }
};
