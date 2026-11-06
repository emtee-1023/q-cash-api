<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advance_repayment_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advance_id')->constrained('advances');
            $table->integer('recovery_rate_bps');
            $table->integer('maximum_repayment_days');
            $table->json('fallback_terms')->nullable();
            $table->timestamp('effective_from');
            $table->timestamp('effective_until')->nullable();
            $table->text('adjustment_reason')->nullable();
            $table->string('policy_version', 50)->nullable();
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['advance_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advance_repayment_terms');
    }
};
