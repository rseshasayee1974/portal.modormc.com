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
        Schema::create('mm_activity_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('log_name', 191)->nullable()->index();
            $table->text('description');
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_type', 191)->nullable();
            $table->string('event', 191)->nullable();
            $table->unsignedBigInteger('causer_id')->nullable();
            $table->string('causer_type', 191)->nullable();
            $table->json('mm_properties')->nullable();
            $table->char('batch_uuid', 36)->nullable();
            $table->timestamps();

            $table->index(['causer_type', 'causer_id'], 'causer');
            $table->index(['subject_type', 'subject_id'], 'subject');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_activity_log');
    }
};
