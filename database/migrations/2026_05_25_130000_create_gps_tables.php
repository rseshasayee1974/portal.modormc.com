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
        // 1. GPS Devices
        Schema::create('mm_gps_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->constrained('mm_plants')->cascadeOnDelete();
            $table->foreignId('machine_id')->nullable()->unique()->constrained('mm_machines')->nullOnDelete();
            $table->string('imei', 50)->unique();
            $table->string('device_model', 100)->nullable();
            $table->string('sim_number', 50)->nullable();
            $table->string('phone_number', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_activity')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. GPS Positions (History Log)
        Schema::create('mm_gps_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('mm_gps_devices')->cascadeOnDelete();
            $table->foreignId('machine_id')->constrained('mm_machines')->cascadeOnDelete();
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->decimal('speed', 5, 2)->default(0.00); // km/h
            $table->decimal('heading', 5, 2)->default(0.00); // degrees 0-359
            $table->decimal('altitude', 6, 2)->nullable();
            $table->boolean('ignition')->default(false);
            $table->decimal('odometer', 12, 2)->nullable();
            $table->timestamp('recorded_at');
            $table->timestamp('created_at')->useCurrent();

            // Indexes for fast history retrieval
            $table->index(['machine_id', 'recorded_at']);
            $table->index(['device_id', 'recorded_at']);
        });

        // 3. GPS Latest Positions (Real-time tracking cache)
        Schema::create('mm_gps_latest_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->unique()->constrained('mm_gps_devices')->cascadeOnDelete();
            $table->foreignId('machine_id')->unique()->constrained('mm_machines')->cascadeOnDelete();
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->decimal('speed', 5, 2)->default(0.00);
            $table->decimal('heading', 5, 2)->default(0.00);
            $table->decimal('altitude', 6, 2)->nullable();
            $table->boolean('ignition')->default(false);
            $table->decimal('odometer', 12, 2)->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();
        });

        // 4. Geofences
        Schema::create('mm_geofences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->constrained('mm_plants')->cascadeOnDelete();
            $table->string('name', 250);
            $table->text('description')->nullable();
            $table->string('shape', 50)->default('circle'); // circle, polygon
            $table->json('coordinates'); // circular: {center: {lat, lng}, radius}, polygon: [{lat, lng}, ...]
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 5. Geofence Event Logs
        Schema::create('mm_geofence_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->constrained('mm_machines')->cascadeOnDelete();
            $table->foreignId('geofence_id')->constrained('mm_geofences')->cascadeOnDelete();
            $table->string('event_type', 20); // enter, exit
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->timestamp('recorded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_geofence_logs');
        Schema::dropIfExists('mm_geofences');
        Schema::dropIfExists('mm_gps_latest_positions');
        Schema::dropIfExists('mm_gps_positions');
        Schema::dropIfExists('mm_gps_devices');
    }
};
