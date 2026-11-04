<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('era_assessments', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('business_id')->constrained('businesses');
            $table->foreignId('model_version_id')->nullable()->constrained('model_versions');
            $table->foreignId('decision_policy_id')->nullable()->constrained('decision_policies');
            $table->string('assessment_status', 30);
            $table->timestamp('assessment_date');
            $table->integer('forecast_period_days')->default(30);
            $table->bigInteger('predicted_revenue_minor')->nullable();
            $table->decimal('reliability_score', 8, 4)->nullable();
            $table->integer('recommended_advance_rate_bps')->nullable();
            $table->bigInteger('recommended_advance_amount_minor')->nullable();
            $table->integer('recommended_recovery_rate_bps')->nullable();
            $table->integer('maximum_repayment_days')->nullable();
            $table->json('score_breakdown')->nullable();
            $table->json('reason_codes')->nullable();
            $table->json('warnings')->nullable();
            $table->json('input_snapshot')->nullable();
            $table->json('output_snapshot')->nullable();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['business_id', 'assessment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('era_assessments');
    }
};
