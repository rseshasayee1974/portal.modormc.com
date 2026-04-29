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
        Schema::create('mm_journal_entries', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('entity_id');
            $table->unsignedBigInteger('plant_id');
            $table->string('voucher_type', 20); // INV, JV, PAY, REC, etc
            $table->string('voucher_number', 50);

            $table->string('ref_module', 100)->nullable(); // invoice, payment, purchase
            $table->string('ref_name', 100)->nullable();
            $table->unsignedBigInteger('ref_id')->nullable();

            $table->date('voucher_date');
            $table->date('posting_date');

            $table->text('narration')->nullable();

            $table->decimal('total_debit', 19, 4)->default(0);
            $table->decimal('total_credit', 19, 4)->default(0);

            $table->string('is_status', 20)->default('DRAFT'); // DRAFT, APPROVED, POSTED, CANCELLED, REVERSED
            $table->unsignedBigInteger('reversal_of_id')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->tinyInteger('is_deleted')->default(0);

            // Constraints
            $table->foreign('plant_id')->references('id')->on('mm_plants')->onDelete('cascade');
            $table->foreign('reversal_of_id')->references('id')->on('mm_journal_entries')->onDelete('set null');

            // Unique voucher per entity + type
            $table->unique(['plant_id', 'voucher_type', 'voucher_number'], 'uk_voucher');

            $table->index(['plant_id', 'posting_date'], 'idx_entity_date');
            $table->index('is_status', 'idx_is_status');
            $table->index(['ref_module', 'ref_id'], 'idx_ref');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mm_journal_entries');
    }
};
