<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mm_opening_balance_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plant_id')->unique();
            $table->date('cutover_date');
            $table->unsignedBigInteger('clearing_account_id')->nullable();
            $table->text('notes')->nullable();
            $table->json('lines');
            $table->string('status', 20)->default('DRAFT');
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->auditColumns();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mm_opening_balance_batches');
    }
};