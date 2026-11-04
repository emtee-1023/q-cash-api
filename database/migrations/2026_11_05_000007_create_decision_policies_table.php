<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('decision_policies', function (Blueprint $table) {
            $table->id();
            $table->string('policy_name', 100);
            $table->string('version', 50);
            $table->json('scoring_weights');
            $table->json('scoring_bands')->nullable();
            $table->json('advance_rules');
            $table->json('repayment_rules');
            $table->json('eligibility_rules')->nullable();
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_until')->nullable();
            $table->string('status', 30)->default('draft');
            $table->timestamps();

            $table->unique(['policy_name', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decision_policies');
    }
};
