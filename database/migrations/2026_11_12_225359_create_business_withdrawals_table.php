<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses');
            $table->bigInteger('amount_minor');
            $table->string('currency', 3)->default('KES');
            $table->string('recipient_phone_number', 30);
            $table->string('status', 30)->default('processing');
            $table->string('provider', 50)->default('mpesa_b2c');
            $table->string('provider_reference', 150)->nullable()->unique();
            $table->string('originator_conversation_id', 100)->nullable()->unique();
            $table->string('result_code', 20)->nullable();
            $table->string('result_desc', 255)->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('provider_response')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_withdrawals');
    }
};
