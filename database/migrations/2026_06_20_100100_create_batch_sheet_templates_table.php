<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mm_batch_sheet_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plant_id');
            $table->unsignedBigInteger('customer_id')->nullable();

            $table->string('name');
            $table->string('source_type', 20)->comment('pdf, image, excel, csv');
            $table->json('field_mapping')->comment('Canonical field → uploaded field label mapping');
            $table->string('layout_fingerprint')->nullable()->index()->comment('Hash of header structure for auto-detection');
            $table->json('keywords')->nullable()->comment('Keywords for template matching, e.g. ["schwing", "stetter"]');

            $table->unsignedInteger('usage_count')->default(0);
            $table->boolean('is_active')->default(true);

            // Audit
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('plant_id')->references('id')->on('mm_plants')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('mm_patrons')->nullOnDelete();

            // A customer can have multiple templates per plant (different report types)
            $table->index(['plant_id', 'customer_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mm_batch_sheet_templates');
    }
};
