<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disbursements', function (Blueprint $table) {
            $table->string('originator_conversation_id', 100)
                ->nullable()
                ->unique()
                ->after('provider_reference');
        });
    }

    public function down(): void
    {
        Schema::table('disbursements', function (Blueprint $table) {
            $table->dropUnique(['originator_conversation_id']);
            $table->dropColumn('originator_conversation_id');
        });
    }
};
