<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mm_batch_sheet_field_dictionary', function (Blueprint $table) {
            $table->id();
            $table->string('canonical_name', 50)->unique()->comment('Internal field name, e.g. batch_number');
            $table->json('aliases')->comment('Array of known label variations');
            $table->string('category', 30)->comment('header, material, metadata, aggregate');
            $table->string('data_type', 20)->default('string')->comment('string, number, date, time');
            $table->string('db_column', 80)->nullable()->comment('Target column in batch/dispatch tables');
            $table->string('db_table', 80)->nullable()->comment('Target table, e.g. mm_batches, mm_dispatches');
            $table->boolean('is_system')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mm_batch_sheet_field_dictionary');
    }
};
