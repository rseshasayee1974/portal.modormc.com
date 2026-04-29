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
        Schema::create('mm_journal_entry_lines', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('journal_entry_id');
            $table->unsignedBigInteger('plant_id'); // FK to ledgers
            $table->unsignedBigInteger('account_id');

            $table->decimal('debit_amount', 19, 4)->default(0);
            $table->decimal('credit_amount', 19, 4)->default(0);

            $table->unsignedBigInteger('cost_center_id')->nullable();
            $table->string('partner_type', 100)->nullable();
            $table->unsignedBigInteger('partner_id')->nullable();
            $table->unsignedBigInteger('tax_id')->nullable();

            $table->string('narration_name')->nullable();
            $table->string('narration_label')->nullable();
            $table->string('line_narration')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->tinyInteger('is_deleted')->default(0);

            // Constraints
            $table->foreign('journal_entry_id')->references('id')->on('mm_journal_entries')->onDelete('cascade');
            $table->foreign('account_id')->references('id')->on('mm_ledgers')->onDelete('restrict');
            $table->foreign('plant_id')->references('id')->on('mm_plants')->onDelete('cascade');
            $table->index('journal_entry_id', 'idx_entry');
            $table->index('account_id', 'idx_account');
            $table->index('cost_center_id', 'idx_cost_center');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mm_journal_entry_lines');
    }
};
