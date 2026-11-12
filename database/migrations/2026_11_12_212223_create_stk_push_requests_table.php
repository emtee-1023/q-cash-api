<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stk_push_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses');
            $table->string('merchant_request_id', 100)->nullable();
            $table->string('checkout_request_id', 100)->nullable()->unique();
            $table->string('phone_number', 30);
            $table->bigInteger('amount_minor');
            $table->string('currency', 3)->default('KES');
            $table->string('account_reference', 150)->nullable();
            $table->string('transaction_desc', 255)->nullable();
            $table->string('status', 30)->default('pending');
            $table->string('result_code', 20)->nullable();
            $table->string('result_desc', 255)->nullable();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->json('raw_response')->nullable();
            $table->json('raw_callback')->nullable();
            $table->timestamp('initiated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stk_push_requests');
    }
};
