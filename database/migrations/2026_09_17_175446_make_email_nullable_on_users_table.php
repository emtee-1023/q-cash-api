<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone_number')
                ->nullable()
                ->unique()
                ->after('id');

            $table->timestamp('phone_verified_at')
                ->nullable()
                ->after('phone_number');

            $table->string('email')
                ->nullable()
                ->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        if (Schema::hasIndex('users', ['phone_number'], 'unique')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique(['phone_number']);
            });
        }

        $phoneColumns = array_values(array_filter(
            ['phone_number', 'phone_verified_at'],
            fn (string $column): bool => Schema::hasColumn('users', $column),
        ));

        if ($phoneColumns !== []) {
            Schema::table('users', function (Blueprint $table) use ($phoneColumns) {
                $table->dropColumn($phoneColumns);
            });
        }

        // Email stays nullable: the application supports phone-only accounts,
        // so restoring NOT NULL would fail whenever such a user exists.
    }
};
