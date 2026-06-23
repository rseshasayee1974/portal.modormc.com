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
        if (!Schema::hasTable('mm_sales_order_items')) {
            Schema::create('mm_sales_order_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sales_order_id')->constrained('mm_sales_orders')->cascadeOnDelete();
                $table->foreignId('mix_design_id')->constrained('mm_mix_designs');
                $table->decimal('quantity', 17, 2);
                $table->decimal('rate', 17, 2);
                $table->foreignId('tax_id')->nullable()->constrained('mm_taxes');
                $table->decimal('tax_amount', 17, 2)->default(0);
                $table->decimal('untaxed_amount', 17, 2)->default(0);
                $table->decimal('amount_total', 17, 2)->default(0);
                $table->auditColumns();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_sales_order_items');
    }
};
