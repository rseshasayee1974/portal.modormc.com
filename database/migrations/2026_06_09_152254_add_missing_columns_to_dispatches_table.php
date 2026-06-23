<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('mm_dispatches', function (Blueprint $table) {
            if (!Schema::hasColumn('mm_dispatches', 'work_order_id')) {
                $table->unsignedBigInteger('work_order_id')->nullable()->index();
            }
            if (!Schema::hasColumn('mm_dispatches', 'transport_id')) {
                $table->foreignId('transport_id')->nullable()->constrained('mm_patrons')->nullOnDelete();
            }
            if (!Schema::hasColumn('mm_dispatches', 'mixdesign_id')) {
                $table->foreignId('mixdesign_id')->nullable()->constrained('mm_mix_designs')->nullOnDelete();
            }
            if (!Schema::hasColumn('mm_dispatches', 'customer_id')) {
                $table->foreignId('customer_id')->nullable()->constrained('mm_patrons')->nullOnDelete();
            }
            if (!Schema::hasColumn('mm_dispatches', 'load_site_id')) {
                $table->foreignId('load_site_id')->nullable()->constrained('mm_sites')->nullOnDelete();
            }
            if (!Schema::hasColumn('mm_dispatches', 'unload_site_id')) {
                $table->foreignId('unload_site_id')->nullable()->constrained('mm_sites')->nullOnDelete();
            }
            if (!Schema::hasColumn('mm_dispatches', 'driver_id')) {
                $table->foreignId('driver_id')->nullable()->constrained('mm_personnels')->nullOnDelete();
            }
            if (!Schema::hasColumn('mm_dispatches', 'dispatch_no')) {
                $table->string('dispatch_no')->nullable();
            }
            if (!Schema::hasColumn('mm_dispatches', 'dispatch_status')) {
                $table->string('dispatch_status', 50)->default('Draft');
            }
            if (!Schema::hasColumn('mm_dispatches', 'delivered_qty')) {
                $table->decimal('delivered_qty', 10, 3)->default(0);
            }
            if (!Schema::hasColumn('mm_dispatches', 'load_total_amount')) {
                $table->decimal('load_total_amount', 17, 2)->default(0);
            }
            if (!Schema::hasColumn('mm_dispatches', 'deleted_at')) {
                $table->softDeletes();
            }
            if (!Schema::hasColumn('mm_dispatches', 'payment_mode')) {
                $table->enum('payment_mode', ['cash', 'credit'])->default('credit');
            }
            if (!Schema::hasColumn('mm_dispatches', 'plant_sno')) {
                $table->string('plant_sno')->nullable();
            }
            if (!Schema::hasColumn('mm_dispatches', 'prefix')) {
                $table->string('prefix')->nullable();
            }
            if (!Schema::hasColumn('mm_dispatches', 'dispatch_reference')) {
                $table->string('dispatch_reference')->nullable();
            }
            if (!Schema::hasColumn('mm_dispatches', 'empty_time')) {
                $table->timestamp('empty_time')->nullable();
            }
            if (!Schema::hasColumn('mm_dispatches', 'load_time')) {
                $table->timestamp('load_time')->nullable();
            }
            if (!Schema::hasColumn('mm_dispatches', 'empty_weight_truck')) {
                $table->decimal('empty_weight_truck', 15, 3)->nullable();
            }
            if (!Schema::hasColumn('mm_dispatches', 'loaded_weight_truck')) {
                $table->decimal('loaded_weight_truck', 15, 3)->nullable();
            }
            if (!Schema::hasColumn('mm_dispatches', 'net_weight')) {
                $table->decimal('net_weight', 15, 3)->nullable();
            }
            if (!Schema::hasColumn('mm_dispatches', 'load_rate')) {
                $table->decimal('load_rate', 12, 2)->default(0);
            }
            if (!Schema::hasColumn('mm_dispatches', 'load_tax_id')) {
                $table->foreignId('load_tax_id')->nullable()->constrained('mm_taxes')->nullOnDelete();
            }
            if (!Schema::hasColumn('mm_dispatches', 'load_tax_amount')) {
                $table->decimal('load_tax_amount', 17, 2)->default(0);
            }
            if (!Schema::hasColumn('mm_dispatches', 'load_untax_amount')) {
                $table->decimal('load_untax_amount', 17, 2)->default(0);
            }
            if (!Schema::hasColumn('mm_dispatches', 'pass_amount')) {
                $table->decimal('pass_amount', 17, 2)->default(0);
            }
            if (!Schema::hasColumn('mm_dispatches', 'discount_amount')) {
                $table->decimal('discount_amount', 17, 2)->default(0);
            }
            if (!Schema::hasColumn('mm_dispatches', 'transport_expenses')) {
                $table->decimal('transport_expenses', 17, 2)->default(0);
            }
            if (!Schema::hasColumn('mm_dispatches', 'adjustment_amount')) {
                $table->decimal('adjustment_amount', 17, 2)->nullable();
            }
            if (!Schema::hasColumn('mm_dispatches', 'round_off')) {
                $table->decimal('round_off', 5, 2)->default(0);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_dispatches', function (Blueprint $table) {
            $isSqlite = DB::connection()->getDriverName() === 'sqlite';

            if (!$isSqlite) {
                if (Schema::hasColumn('mm_dispatches', 'transport_id')) {
                    $table->dropForeign(['transport_id']);
                }
                if (Schema::hasColumn('mm_dispatches', 'mixdesign_id')) {
                    $table->dropForeign(['mixdesign_id']);
                }
                if (Schema::hasColumn('mm_dispatches', 'load_tax_id')) {
                    $table->dropForeign(['load_tax_id']);
                }
            }

            $columns = [
                'work_order_id', 'transport_id', 'mixdesign_id', 'payment_mode', 'plant_sno',
                'prefix', 'dispatch_reference', 'empty_time', 'load_time',
                'empty_weight_truck', 'loaded_weight_truck', 'net_weight',
                'load_rate', 'load_tax_id', 'load_tax_amount', 'load_untax_amount',
                'pass_amount', 'discount_amount', 'transport_expenses', 'adjustment_amount', 'round_off'
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('mm_dispatches', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
