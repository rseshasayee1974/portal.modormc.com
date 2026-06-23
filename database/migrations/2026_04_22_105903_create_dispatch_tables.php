<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('mm_dispatches')) {
            Schema::create('mm_dispatches', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('work_order_id')->nullable()->index();
                $table->unsignedBigInteger('batch_id')->nullable()->index();
                $table->unsignedBigInteger('plant_id')->nullable()->index();

                // Relations
                $table->foreignId('truck_id')->nullable()->constrained('mm_machines')->nullOnDelete();
                $table->foreignId('transport_id')->nullable()->constrained('mm_patrons')->nullOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained('mm_patrons')->nullOnDelete();
                $table->foreignId('mixdesign_id')->nullable()->constrained('mm_mix_designs')->nullOnDelete();
                $table->foreignId('load_site_id')->nullable()->constrained('mm_sites')->nullOnDelete();
                $table->foreignId('unload_site_id')->nullable()->constrained('mm_sites')->nullOnDelete();

                // Personnel
                $table->foreignId('driver_id')->nullable()->constrained('mm_personnels')->nullOnDelete();
                $table->enum('payment_mode', ['cash', 'credit'])->default('credit');
                $table->string('plant_sno')->nullable();
                $table->string('prefix')->nullable();
                $table->string('dispatch_no')->nullable();
                $table->string('dispatch_reference')->nullable();
                $table->timestamp('dispatch_time')->nullable();
                $table->timestamp('empty_time')->nullable();
                $table->timestamp('load_time')->nullable();
                $table->decimal('empty_weight_truck', 15, 3)->nullable()->after('truck_id');
                $table->decimal('loaded_weight_truck', 15, 3)->nullable()->after('empty_weight_truck');
                $table->decimal('net_weight', 15, 3)->nullable()->after('loaded_weight_truck');

                $table->decimal('delivered_qty', 10, 3)->default(0);
                $table->decimal('load_rate', 12, 2)->default(0);
                $table->foreignId('load_tax_id')->nullable()->constrained('mm_taxes')->nullOnDelete();
                $table->decimal('load_tax_amount', 17, 2)->default(0);
                $table->decimal('load_untax_amount', 17, 2)->default(0);
                $table->decimal('load_total_amount', 17, 2)->default(0);
                $table->decimal('pass_amount', 17, 2)->default(0);
                $table->decimal('discount_amount', 17, 2)->default(0);
                $table->decimal('transport_expenses', 17, 2)->default(0);
                $table->decimal('adjustment_amount', 17, 2)->nullable();
                $table->decimal('round_off', 5, 2)->default(0);

                $table->string('dispatch_status', 50)->default('Draft');

                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->unsignedBigInteger('deleted_by')->nullable();

                $table->timestamps();
            });
        } else {
            Schema::table('mm_dispatches', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_dispatches', 'work_order_id')) {
                    $table->unsignedBigInteger('work_order_id')->nullable()->index();
                }
                if (!Schema::hasColumn('mm_dispatches', 'batch_id')) {
                    $table->unsignedBigInteger('batch_id')->nullable()->index();
                }
                if (!Schema::hasColumn('mm_dispatches', 'plant_id')) {
                    $table->unsignedBigInteger('plant_id')->nullable()->index();
                }
                if (!Schema::hasColumn('mm_dispatches', 'truck_id')) {
                    $table->foreignId('truck_id')->nullable()->constrained('mm_machines')->nullOnDelete();
                }
                if (!Schema::hasColumn('mm_dispatches', 'transport_id')) {
                    $table->foreignId('transport_id')->nullable()->constrained('mm_patrons')->nullOnDelete();
                }
                if (!Schema::hasColumn('mm_dispatches', 'customer_id')) {
                    $table->foreignId('customer_id')->nullable()->constrained('mm_patrons')->nullOnDelete();
                }
                if (!Schema::hasColumn('mm_dispatches', 'mixdesign_id')) {
                    $table->foreignId('mixdesign_id')->nullable()->constrained('mm_mix_designs')->nullOnDelete();
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
                if (!Schema::hasColumn('mm_dispatches', 'payment_mode')) {
                    $table->enum('payment_mode', ['cash', 'credit'])->default('credit');
                }
                if (!Schema::hasColumn('mm_dispatches', 'plant_sno')) {
                    $table->string('plant_sno')->nullable();
                }
                if (!Schema::hasColumn('mm_dispatches', 'prefix')) {
                    $table->string('prefix')->nullable();
                }
                if (!Schema::hasColumn('mm_dispatches', 'dispatch_no')) {
                    $table->string('dispatch_no')->nullable();
                }
                if (!Schema::hasColumn('mm_dispatches', 'dispatch_reference')) {
                    $table->string('dispatch_reference')->nullable();
                }
                if (!Schema::hasColumn('mm_dispatches', 'dispatch_time')) {
                    $table->timestamp('dispatch_time')->nullable();
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
                if (!Schema::hasColumn('mm_dispatches', 'delivered_qty')) {
                    $table->decimal('delivered_qty', 10, 3)->default(0);
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
                if (!Schema::hasColumn('mm_dispatches', 'load_total_amount')) {
                    $table->decimal('load_total_amount', 17, 2)->default(0);
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
                if (!Schema::hasColumn('mm_dispatches', 'dispatch_status')) {
                    $table->string('dispatch_status', 50)->default('Draft');
                }
                if (!Schema::hasColumn('mm_dispatches', 'created_by')) {
                    $table->unsignedBigInteger('created_by')->nullable();
                }
                if (!Schema::hasColumn('mm_dispatches', 'updated_by')) {
                    $table->unsignedBigInteger('updated_by')->nullable();
                }
                if (!Schema::hasColumn('mm_dispatches', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
            });
        }

        if (!Schema::hasTable('mm_dispatch_statuses')) {
            Schema::create('mm_dispatch_statuses', function (Blueprint $table) {
                $table->id();

                // 1:1 relationship
                $table->foreignId('dispatch_id')
                    ->unique()
                    ->constrained('mm_dispatches')
                    ->cascadeOnDelete();

                $table->boolean('is_tax_inclusive')->default(false);
        $table->unsignedBigInteger('plant_id');

                // Invoice Tracking
                $table->unsignedBigInteger('invoice_id')->nullable();
                $table->date('invoice_date')->nullable();
                $table->string('invoice_number')->nullable();
                $table->tinyInteger('invoice_status')->default(0);

                $table->integer('transport_units')->nullable();
                $table->decimal('transport_rate', 12, 2)->default(0);
                $table->foreignId('transport_tax_id')->nullable()->constrained('mm_taxes')->nullOnDelete();
                $table->decimal('transport_tax_rate', 8, 2)->default(0);
                $table->decimal('transport_tax_amount', 17, 2)->default(0);

                $table->decimal('transport_total_amount', 17, 2)->default(0);

                $table->decimal('total_amount', 17, 2)->default(0);
                $table->string('transport_reference')->nullable();
                $table->decimal('transport_km', 10, 2)->default(0);
                $table->string('receiver_name')->nullable();
                $table->string('receive_mobile')->nullable();
                $table->string('note')->nullable();

                $table->auditColumns();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_dispatch_statuses');
        Schema::dropIfExists('mm_dispatches');
    }
};
