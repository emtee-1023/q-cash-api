<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repayment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repayment_collection_id')->constrained('repayment_collections');
            $table->foreignId('advance_id')->constrained('advances');
            $table->bigInteger('amount_minor');
            $table->timestamps();

            $table->unique(['repayment_collection_id', 'advance_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repayment_allocations');
    }
};
