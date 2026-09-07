<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('mm_pump_boom_deployment_schedule')) {
            Schema::create('mm_pump_boom_deployment_schedule', function (Blueprint $table) {
                $table->id();

                $table->foreignId('plant_id')->nullable()->constrained('mm_plants')->nullOnDelete();
                $table->date('schedule_date')->index();
                $table->string('pour_reference', 100)->index();

                // Site & Location details
                $table->foreignId('site_id')->nullable()->constrained('mm_sites')->nullOnDelete();
                $table->string('site_name', 200)->nullable();
                $table->string('pour_location', 255)->nullable(); // e.g. "Raft Footing Grid A-D", "3rd Floor Slab Deck"

                // Mix & Volume
                $table->foreignId('mix_design_id')->nullable()->constrained('mm_mix_designs')->nullOnDelete();
                $table->string('grade', 100)->nullable(); // e.g. "M25", "M30 Pumpable", "M40"
                $table->decimal('planned_qty_m3', 10, 3)->default(0);

                // Pump & Rig Specifications
                $table->enum('pump_type', ['boom_pump', 'line_pump', 'crane_bucket', 'direct_pour', 'stationary_pump'])->default('boom_pump');
                $table->foreignId('pump_vehicle_id')->nullable()->constrained('mm_machines')->nullOnDelete();
                $table->string('pump_no', 100)->nullable(); // Equipment Reg No / Asset ID
                $table->decimal('boom_length_m', 6, 2)->nullable(); // Boom reach length or pipeline length in meters (e.g. 36m, 42m, 120m)

                // Operator / Crew
                $table->foreignId('operator_id')->nullable()->constrained('mm_personnels')->nullOnDelete();
                $table->string('operator_name', 150)->nullable();

                // Deployment Timelines
                $table->timestamp('pump_arrival_time')->nullable();
                $table->timestamp('setup_start_time')->nullable();
                $table->timestamp('setup_end_time')->nullable();
                $table->timestamp('pour_start_time')->nullable();
                $table->timestamp('planned_end_time')->nullable();
                $table->timestamp('actual_start_time')->nullable();
                $table->timestamp('actual_end_time')->nullable();

                // Status & Notes
                $table->string('status', 30)->default('scheduled')->index(); // scheduled, en_route, setup, ready, pumping, washout, completed, breakdown, cancelled
                $table->text('notes')->nullable();

                // Audit Trails & Soft Deletes
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->unsignedBigInteger('deleted_by')->nullable();
                $table->softDeletes();
                $table->timestamps();

                // Composite Index for fast plant querying
                $table->index(['plant_id', 'schedule_date']);
                $table->index(['plant_id', 'pour_reference']);
                $table->index(['plant_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mm_pump_boom_deployment_schedule');
    }
};
