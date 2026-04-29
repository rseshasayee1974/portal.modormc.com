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
        Schema::create('mm_health_check_result_history_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('check_name', 191);
            $table->string('check_label', 191);
            $table->string('status', 191);
            $table->text('notification_message')->nullable();
            $table->string('short_summary', 191)->nullable();
            $table->json('mm_meta');
            $table->timestamp('ended_at');
            $table->char('batch', 36)->index();
            $table->timestamp('created_at')->nullable()->index();
            $table->timestamp('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_health_check_result_history_items');
    }
};
