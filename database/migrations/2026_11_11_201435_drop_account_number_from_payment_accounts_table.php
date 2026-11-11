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
        $accountNumberIndex = collect(Schema::getIndexes('payment_accounts'))
            ->first(fn (array $index): bool => $index['unique']
                && in_array('account_number', $index['columns'], true));

        if ($accountNumberIndex !== null) {
            Schema::table('payment_accounts', function (Blueprint $table) use ($accountNumberIndex) {
                $table->dropUnique($accountNumberIndex['name']);
            });
        }

        Schema::table('payment_accounts', function (Blueprint $table) {
            $table->dropColumn('account_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_accounts', function (Blueprint $table) {
            $table->string('account_number', 100)
                ->nullable()
                ->after('channel_type');

            $table->unique(['provider', 'channel_type', 'account_number', 'account_reference']);
        });
    }
};
