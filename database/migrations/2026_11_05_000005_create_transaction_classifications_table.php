<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_classifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transactions');
            $table->string('classifier_version', 50);
            $table->string('category', 50);
            $table->boolean('revenue_eligible')->default(false);
            $table->string('anomaly_status', 30)->default('normal');
            $table->decimal('confidence', 6, 5)->nullable();
            $table->json('reason_codes')->nullable();
            $table->boolean('is_current')->default(true);
            $table->timestamp('classified_at');
            $table->timestamps();

            $table->unique(['transaction_id', 'classifier_version']);
            $table->index(['transaction_id', 'is_current']);
        });

        // PostgreSQL partial unique index guaranteeing at most one current
        // classification per transaction. SQLite does not support partial
        // indexes; the invariant is additionally enforced in the service layer.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'CREATE UNIQUE INDEX transaction_classifications_one_current '
                .'ON transaction_classifications (transaction_id) WHERE is_current = TRUE'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_classifications');
    }
};
