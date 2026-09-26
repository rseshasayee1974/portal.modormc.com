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
        if (Schema::hasTable('mm_purchase_order_history')) {
            Schema::table('mm_purchase_order_history', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_purchase_order_history', 'conversion_quantity')) {
                    $table->decimal('conversion_quantity', 17, 4)->nullable()->default(0)->after('received_qty');
                }
                if (!Schema::hasColumn('mm_purchase_order_history', 'conversion_uom_id')) {
                    $table->unsignedInteger('conversion_uom_id')->nullable()->after('conversion_quantity');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('mm_purchase_order_history')) {
            Schema::table('mm_purchase_order_history', function (Blueprint $table) {
                if (Schema::hasColumn('mm_purchase_order_history', 'conversion_quantity')) {
                    $table->dropColumn('conversion_quantity');
                }
                if (Schema::hasColumn('mm_purchase_order_history', 'conversion_uom_id')) {
                    $table->dropColumn('conversion_uom_id');
                }
            });
        }
    }
};
