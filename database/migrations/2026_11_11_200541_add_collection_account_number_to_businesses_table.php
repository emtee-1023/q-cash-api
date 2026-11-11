<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('collection_account_number', 6)
                ->nullable()
                ->after('kyc_status');
        });

        $this->backfillCollectionAccountNumbers();

        Schema::table('businesses', function (Blueprint $table) {
            $table->unique('collection_account_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropUnique(['collection_account_number']);
            $table->dropColumn('collection_account_number');
        });
    }

    private function backfillCollectionAccountNumbers(): void
    {
        $usedNumbers = DB::table('businesses')
            ->whereNotNull('collection_account_number')
            ->pluck('collection_account_number')
            ->flip();

        $businessIds = DB::table('businesses')
            ->whereNull('collection_account_number')
            ->orderBy('id')
            ->pluck('id');

        foreach ($businessIds as $businessId) {
            do {
                $accountNumber = (string) random_int(100000, 999999);
            } while ($usedNumbers->has($accountNumber));

            DB::table('businesses')
                ->where('id', $businessId)
                ->update(['collection_account_number' => $accountNumber]);

            $usedNumbers->put($accountNumber, true);
        }
    }
};
