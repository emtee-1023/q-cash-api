<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stk_push_requests', function (Blueprint $table) {
            $table->string('purpose', 30)->default('payment')->after('business_id');
            $table->index('purpose');
        });
    }

    public function down(): void
    {
        Schema::table('stk_push_requests', function (Blueprint $table) {
            $table->dropIndex(['purpose']);
            $table->dropColumn('purpose');
        });
    }
};
