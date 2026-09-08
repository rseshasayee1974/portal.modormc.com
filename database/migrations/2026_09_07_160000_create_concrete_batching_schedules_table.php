<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('mm_concrete_batching_schedules')) {
            Schema::create('mm_concrete_batching_schedules', function (Blueprint $table) {
                $table->id();

                $table->foreignId('plant_id')->nullable()->constrained('mm_plants')->nullOnDelete();
                $table->foreignId('sales_order_id')->nullable()->constrained('mm_sales_orders')->nullOnDelete();
                $table->foreignId('batch_id')->nullable()->constrained('mm_batches')->nullOnDelete();
                $table->foreignId('dispatch_id')->nullable()->constrained('mm_dispatches')->nullOnDelete();

                $table->date('schedule_date')->index();
                $table->string('pour_reference', 100)->index();

                $table->foreignId('site_id')->nullable()->constrained('mm_sites')->nullOnDelete();
                $table->foreignId('mix_design_id')->nullable()->constrained('mm_mix_designs')->nullOnDelete();

                // Volume fields in cubic meters (m3)
                $table->decimal('qty_m3', 8, 3)->default(0);
                $table->decimal('order_volume_m3', 10, 3)->default(0);
                $table->decimal('remaining_volume_m3', 10, 3)->default(0);

                // Transport & Crew
                $table->foreignId('vehicle_id')->nullable()->constrained('mm_machines')->nullOnDelete();
                $table->foreignId('driver_id')->nullable()->constrained('mm_personnels')->nullOnDelete();

                // Pump / Placement Deployment
                $table->string('pump_type', 50)->default('boom_pump');
                $table->foreignId('pump_vehicle_id')->nullable()->constrained('mm_machines')->nullOnDelete();

                // Timestamps & Milestones
                $table->timestamp('batching_time')->nullable();
                $table->timestamp('dispatch_time')->nullable();
                $table->timestamp('eta_site')->nullable();
                $table->timestamp('unloading_start')->nullable();
                $table->timestamp('unloading_end')->nullable();

                // Lifecycle status
                $table->string('status', 30)->default('scheduled')->index(); // scheduled, batching, in_transit, on_site, pouring, completed, cancelled
                $table->text('notes')->nullable();

                // Audit Trails
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->unsignedBigInteger('deleted_by')->nullable();
                $table->softDeletes();
                $table->timestamps();

                // Composite indexes for fast queries
                $table->index(['plant_id', 'schedule_date']);
                $table->index(['plant_id', 'pour_reference']);
                $table->index(['plant_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mm_concrete_batching_schedules');
    }
};
