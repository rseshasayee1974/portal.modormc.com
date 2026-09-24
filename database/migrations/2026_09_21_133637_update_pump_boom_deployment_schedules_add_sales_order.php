<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mm_pump_boom_deployment_schedule', function (Blueprint $table) {
            try {
                $table->dropIndex('mm_pump_boom_deployment_schedule_pour_reference_index');
            } catch (\Throwable $e) {}
            try {
                $table->dropIndex('mm_pump_boom_deployment_schedule_plant_id_pour_reference_index');
            } catch (\Throwable $e) {}

            if (Schema::hasColumn('mm_pump_boom_deployment_schedule', 'pour_reference')) {
                $table->dropColumn('pour_reference');
            }

            if (!Schema::hasColumn('mm_pump_boom_deployment_schedule', 'sales_order_id')) {
                $table->foreignId('sales_order_id')->nullable()->after('schedule_date')->constrained('mm_sales_orders')->nullOnDelete();
            }
            if (!Schema::hasColumn('mm_pump_boom_deployment_schedule', 'batch_id')) {
                $table->foreignId('batch_id')->nullable()->after('sales_order_id')->constrained('mm_batches')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('mm_pump_boom_deployment_schedule', function (Blueprint $table) {
            $table->string('pour_reference', 100)->nullable();
            $table->dropForeign(['sales_order_id']);
            $table->dropForeign(['batch_id']);
            $table->dropColumn(['sales_order_id', 'batch_id']);
        });
    }
};
