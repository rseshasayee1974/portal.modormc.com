<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Dispatch Main Table
        |--------------------------------------------------------------------------
        */
        Schema::create('mm_dispatches', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('work_order_id')->nullable()->index();
            $table->unsignedBigInteger('batch_id')->nullable()->index();

            $table->string('plant_sno')->nullable();
            $table->string('prefix')->nullable();
            $table->string('dispatch_no')->nullable();

            $table->timestamp('dispatch_time')->nullable();
            $table->decimal('delivered_qty', 10, 3)->default(0);

            // Relations
            $table->foreignId('truck_id')->nullable()->constrained('mm_machines')->nullOnDelete();
            $table->foreignId('transport_id')->nullable()->constrained('mm_patrons')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('mm_patrons')->nullOnDelete();
            $table->foreignId('mixdesign_id')->nullable()->constrained('mm_mixdesigns')->nullOnDelete();

            $table->foreignId('load_site_id')->nullable()->constrained('mm_sites')->nullOnDelete();
            $table->foreignId('unload_site_id')->nullable()->constrained('mm_sites')->nullOnDelete();

            // Personnel
            $table->foreignId('driver_id')->nullable()->constrained('mm_personnels')->nullOnDelete();
            $table->foreignId('cleaner_id')->nullable()->constrained('mm_personnels')->nullOnDelete();

            $table->string('receiver_name')->nullable();
            $table->string('receive_mobile')->nullable();
            $table->string('note')->nullable();

            $table->enum('payment_mode', ['cash', 'credit'])->default('credit');

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        /*
        |--------------------------------------------------------------------------
        | Dispatch Weights
        |--------------------------------------------------------------------------
        */
        Schema::create('mm_dispatch_weights', function (Blueprint $table) {
            $table->id();

            $table->foreignId('dispatch_id')
                ->constrained('mm_dispatches')
                ->cascadeOnDelete();

            // Load side
            $table->decimal('empty_weight_truck', 12, 2)->default(0);
            $table->decimal('loaded_weight_truck', 12, 2)->default(0);
            $table->timestamp('empty_weight_time_load')->nullable();
            $table->timestamp('loaded_weight_time_load')->nullable();

            // Unload side
            $table->decimal('empty_weight_unload', 12, 2)->default(0);
            $table->decimal('loaded_weight_unload', 12, 2)->default(0);

            $table->timestamp('empty_weight_time_unload')->nullable();
            $table->timestamp('loaded_weight_time_unload')->nullable();

            $table->string('empty_weight_image')->nullable();
            $table->string('loaded_weight_image')->nullable();

            $table->decimal('round_off', 5, 2)->default(0);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('dispatch_id');
        });

        /*
        |--------------------------------------------------------------------------
        | Dispatch Financials
        |--------------------------------------------------------------------------
        */
       Schema::create('mm_dispatch_financials', function (Blueprint $table) {
    $table->id();

    $table->foreignId('dispatch_id')
        ->constrained('mm_dispatches')
        ->cascadeOnDelete();

    /*
    |--------------------------------------------------------------------------
    | LOADING / PRODUCT
    |--------------------------------------------------------------------------
    */
    $table->integer('load_units')->nullable();
    $table->decimal('load_rate', 12, 2)->default(0);
    $table->decimal('load_amount', 17, 2)->default(0);

    $table->foreignId('load_tax_id')->nullable()->constrained('mm_taxes')->nullOnDelete();
    $table->decimal('load_tax_rate', 8, 2)->default(0);
    $table->decimal('load_tax_amount', 17, 2)->default(0);

    $table->decimal('load_total_amount', 17, 2)->default(0); // amount + tax


    /*
    |--------------------------------------------------------------------------
    | UNLOADING
    |--------------------------------------------------------------------------
    */
    $table->integer('unload_units')->nullable();
    $table->decimal('unload_rate', 12, 2)->default(0);
    $table->decimal('unload_amount', 17, 2)->default(0);

    $table->foreignId('unload_tax_id')->nullable()->constrained('mm_taxes')->nullOnDelete();
    $table->decimal('unload_tax_rate', 8, 2)->default(0);
    $table->decimal('unload_tax_amount', 17, 2)->default(0);

    $table->decimal('unload_total_amount', 17, 2)->default(0);

    $table->timestamp('unload_at_time')->nullable();
    $table->string('unload_reference')->nullable();


    /*
    |--------------------------------------------------------------------------
    | TRANSPORT
    |--------------------------------------------------------------------------
    */
    $table->integer('transport_units')->nullable();
    $table->decimal('transport_rate', 12, 2)->default(0);
    $table->decimal('transport_amount', 17, 2)->default(0);

    $table->foreignId('transport_tax_id')->nullable()->constrained('mm_taxes')->nullOnDelete();
    $table->decimal('transport_tax_rate', 8, 2)->default(0);
    $table->decimal('transport_tax_amount', 17, 2)->default(0);

    $table->decimal('transport_total_amount', 17, 2)->default(0);

    $table->string('transport_reference')->nullable();


    /*
    |--------------------------------------------------------------------------
    | ADJUSTMENTS
    |--------------------------------------------------------------------------
    */
    $table->decimal('pass_amount', 17, 2)->default(0);
    $table->decimal('discount_amount', 17, 2)->default(0);
    $table->decimal('transport_expenses', 17, 2)->default(0);
    $table->decimal('adjustment_amount', 17, 2)->nullable();

    $table->decimal('round_off', 5, 2)->default(0);


    /*
    |--------------------------------------------------------------------------
    | SECTION TOTALS
    |--------------------------------------------------------------------------
    */

    // Loading Final
    $table->decimal('load_untaxed_amount', 17, 2)->default(0);
    $table->decimal('load_tax_amount_total', 17, 2)->default(0);
    $table->decimal('load_final_amount', 17, 2)->default(0);

    // Unloading Final
    $table->decimal('unload_untaxed_amount', 17, 2)->default(0);
    $table->decimal('unload_tax_amount_total', 17, 2)->default(0);
    $table->decimal('unload_final_amount', 17, 2)->default(0);

    // Transport Final
    $table->decimal('transport_untaxed_amount', 17, 2)->default(0);
    $table->decimal('transport_tax_amount_total', 17, 2)->default(0);
    $table->decimal('transport_final_amount', 17, 2)->default(0);


    /*
    |--------------------------------------------------------------------------
    | GRAND TOTAL
    |--------------------------------------------------------------------------
    */
    $table->decimal('untaxed_amount', 17, 2)->default(0);
    $table->decimal('tax_amount', 17, 2)->default(0);
    $table->decimal('total_amount', 17, 2)->default(0);


    $table->auditColumns();
    $table->index('dispatch_id');
});

        /*
        |--------------------------------------------------------------------------
        | Dispatch Status
        |--------------------------------------------------------------------------
        */
        Schema::create('mm_dispatch_statuses', function (Blueprint $table) {
            $table->id();

            // 1:1 relationship (intentional)
            $table->foreignId('dispatch_id')
                ->unique()
                ->constrained('mm_dispatches')
                ->cascadeOnDelete();

            $table->string('dispatch_status',100)->default(0); // Draft → Completed
            $table->boolean('is_closed')->default(false);

          

            $table->tinyInteger('driver_salary_status')->default(0);
            $table->tinyInteger('cleaner_salary_status')->default(0);

            $table->boolean('is_load_tax_inclusive')->default(false);
            $table->boolean('is_unload_tax_inclusive')->default(false);

            // Invoice Tracking
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->date('invoice_date')->nullable();
            $table->string('invoice_number')->nullable();
            $table->tinyInteger('invoice_status')->default(0);
            $table->tinyInteger('transport_bill_status')->default(0);
            $table->date('transport_date')->nullable();
            $table->string('transport_invoice_number')->nullable();

            $table->decimal('transport_km', 10, 2)->default(0);

            $table->auditColumns();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mm_dispatch_statuses');
        Schema::dropIfExists('mm_dispatch_financials');
        Schema::dropIfExists('mm_dispatch_weights');
        Schema::dropIfExists('mm_dispatches');
    }
};