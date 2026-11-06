<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advance_offers', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('assessment_id')->constrained('era_assessments');
            $table->foreignId('business_id')->constrained('businesses');
            $table->bigInteger('offered_amount_minor');
            $table->bigInteger('fee_amount_minor')->default(0);
            $table->bigInteger('total_repayable_minor');
            $table->integer('recovery_rate_bps');
            $table->integer('maximum_repayment_days');
            $table->json('offer_terms')->nullable();
            $table->string('status', 30)->default('pending');
            $table->timestamp('offered_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advance_offers');
    }
};
