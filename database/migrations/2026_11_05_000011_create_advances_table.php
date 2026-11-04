<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advances', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('offer_id')->constrained('advance_offers')->unique();
            $table->foreignId('business_id')->constrained('businesses');
            $table->foreignId('financing_partner_id')->nullable()->constrained('financing_partners');
            $table->bigInteger('principal_amount_minor');
            $table->bigInteger('fee_amount_minor')->default(0);
            $table->bigInteger('total_repayable_minor');
            $table->bigInteger('amount_repaid_minor')->default(0);
            $table->bigInteger('outstanding_balance_minor');
            $table->integer('recovery_rate_bps');
            $table->integer('maximum_repayment_days');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('disbursed_at')->nullable();
            $table->timestamp('repayment_started_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->string('status', 30)->default('pending');
            $table->timestamps();

            $table->index(['business_id', 'status']);
            $table->index('due_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advances');
    }
};
