<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_wallet_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_wallet_id')->constrained('business_wallets');
            $table->foreignId('transaction_id')->nullable()->unique()->constrained('transactions');
            $table->foreignId('withdrawal_id')->nullable()->constrained('business_withdrawals');
            $table->string('entry_type', 30);
            $table->bigInteger('amount_minor');
            $table->bigInteger('balance_after_minor');
            $table->string('description', 255)->nullable();
            $table->string('reference', 150)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['withdrawal_id', 'entry_type']);
            $table->index(['business_wallet_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_wallet_entries');
    }
};
