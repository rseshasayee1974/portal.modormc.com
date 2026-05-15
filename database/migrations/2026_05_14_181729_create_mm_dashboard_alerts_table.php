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
        Schema::create('mm_dashboard_alerts', function (Blueprint $table) {
            $table->id();
            $table->dateTime('date_time_off')->nullable();
            $table->dateTime('date_time_on')->nullable();
            $table->string('type'); // e.g., Service Maintenance
            $table->string('status')->default('normal'); // critical, warning, normal
            $table->text('message')->nullable();
            $table->unsignedBigInteger('plant_id')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('plant_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mm_dashboard_alerts');
    }
};
