<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('business_withdrawals', function (Blueprint $table) {
            $table->timestamp('reconciliation_requested_at')->nullable()->after('completed_at');
            $table->unsignedInteger('reconciliation_attempts')->default(0)->after('reconciliation_requested_at');
            $table->string('reconciliation_conversation_id', 150)->nullable()->after('reconciliation_attempts');

            $table->index('reconciliation_conversation_id');
            $table->index(['status', 'reconciliation_requested_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business_withdrawals', function (Blueprint $table) {
            $table->dropIndex(['reconciliation_conversation_id']);
            $table->dropIndex(['status', 'reconciliation_requested_at']);
            $table->dropColumn([
                'reconciliation_requested_at',
                'reconciliation_attempts',
                'reconciliation_conversation_id',
            ]);
        });
    }
};
