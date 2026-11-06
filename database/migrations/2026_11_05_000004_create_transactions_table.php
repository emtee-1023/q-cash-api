<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses');
            $table->foreignId('payment_account_id')->nullable()->constrained('payment_accounts');
            $table->string('provider', 50);
            $table->string('provider_transaction_id', 150);
            $table->string('transaction_type', 50)->nullable();
            $table->string('direction', 20);
            $table->bigInteger('amount_minor');
            $table->string('currency', 3)->default('KES');
            $table->timestamp('transaction_time');
            $table->string('provider_status', 30)->nullable();
            $table->string('counterparty_reference', 150)->nullable();
            $table->string('counterparty_name', 255)->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('ingested_at');
            $table->timestamps();

            $table->unique(['provider', 'provider_transaction_id']);
            $table->index(['business_id', 'transaction_time']);
            $table->index(['payment_account_id', 'transaction_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
