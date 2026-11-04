<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advance_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advance_id')->constrained('advances');
            $table->foreignId('repayment_allocation_id')->nullable()->constrained('repayment_allocations');
            $table->foreignId('disbursement_id')->nullable()->constrained('disbursements');
            $table->string('entry_type', 40);
            $table->bigInteger('amount_minor');
            $table->text('description')->nullable();
            $table->string('reference', 150)->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advance_ledger_entries');
    }
};
