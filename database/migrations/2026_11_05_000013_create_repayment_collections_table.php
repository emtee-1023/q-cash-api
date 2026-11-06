<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repayment_collections', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('business_id')->constrained('businesses');
            $table->foreignId('source_transaction_id')->nullable()->constrained('transactions');
            $table->string('collection_method', 50);
            $table->string('provider', 50)->nullable();
            $table->string('provider_reference', 150)->nullable();
            $table->bigInteger('amount_minor');
            $table->string('currency', 3)->default('KES');
            $table->string('status', 30)->default('pending');
            $table->string('idempotency_key', 150)->unique();
            $table->timestamp('collected_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->json('provider_response')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'collected_at']);
            $table->index('provider_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repayment_collections');
    }
};
