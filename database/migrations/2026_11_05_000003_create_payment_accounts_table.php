<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses');
            $table->string('provider', 50);
            $table->string('channel_type', 50);
            $table->string('account_number', 100);
            $table->string('account_reference', 150)->nullable();
            $table->string('account_name', 255)->nullable();
            $table->string('status', 30)->default('pending');
            $table->timestamp('verified_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'channel_type', 'account_number', 'account_reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_accounts');
    }
};
