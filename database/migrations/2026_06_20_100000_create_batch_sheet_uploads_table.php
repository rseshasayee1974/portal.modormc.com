<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mm_batch_sheet_uploads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plant_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('batch_id')->nullable();

            // File metadata
            $table->string('original_filename');
            $table->string('stored_filename')->unique();
            $table->string('stored_path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->string('sha256_hash', 64)->index();
            $table->string('file_extension', 20);

            // Processing state
            $table->enum('status', [
                'uploaded',
                'validating',
                'processing',
                'ocr_running',
                'extracting',
                'review',
                'completed',
                'failed',
            ])->default('uploaded');

            $table->boolean('ocr_required')->default(false);
            $table->string('parser_used', 50)->nullable();

            // Extraction results
            $table->longText('raw_text')->nullable();
            $table->json('parsed_json')->nullable();
            $table->json('normalized_json')->nullable();
            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->json('field_scores')->nullable();

            // Template link
            $table->unsignedBigInteger('template_id')->nullable();

            // Processing metadata
            $table->json('processing_log')->nullable();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('processing_completed_at')->nullable();
            $table->text('error_message')->nullable();

            // Review
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            // Audit
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('plant_id')->references('id')->on('mm_plants')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('mm_patrons')->nullOnDelete();
            $table->foreign('batch_id')->references('id')->on('mm_batches')->nullOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mm_batch_sheet_uploads');
    }
};
