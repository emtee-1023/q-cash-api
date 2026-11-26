<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advances', function (Blueprint $table) {
            $table->bigInteger('fee_repaid_minor')->default(0)->after('amount_repaid_minor');
            $table->bigInteger('principal_repaid_minor')->default(0)->after('fee_repaid_minor');
        });

        Schema::table('repayment_allocations', function (Blueprint $table) {
            $table->bigInteger('fee_amount_minor')->default(0)->after('amount_minor');
            $table->bigInteger('principal_amount_minor')->default(0)->after('fee_amount_minor');
        });
    }

    public function down(): void
    {
        Schema::table('repayment_allocations', function (Blueprint $table) {
            $table->dropColumn(['fee_amount_minor', 'principal_amount_minor']);
        });

        Schema::table('advances', function (Blueprint $table) {
            $table->dropColumn(['fee_repaid_minor', 'principal_repaid_minor']);
        });
    }
};
