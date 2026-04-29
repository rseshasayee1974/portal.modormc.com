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
        Schema::table('mm_batches', function (Blueprint $table) {
            $table->foreignId('transport_id')->nullable()->after('truck_id')->constrained('mm_patrons');
            $table->foreignId('driver_id')->nullable()->after('transport_id')->constrained('mm_personnel');
            $table->foreignId('uom_id')->nullable()->after('empty_weight_truck')->constrained('mm_product_units');
            // $table->decimal('load_rate', 15, 2)->default(0)->after('load_uom_id');
            // $table->foreignId('load_tax_id')->nullable()->after('load_rate')->constrained('mm_taxes');
            $table->foreignId('site_id')->nullable()->constrained('mm_sites');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_batches', function (Blueprint $table) {
            $table->dropForeign(['transport_id']);
            $table->dropForeign(['driver_id']);
            $table->dropForeign(['load_uom_id']);
            $table->dropForeign(['load_tax_id']);
            $table->dropForeign(['load_site_id']);
            $table->dropColumn(['transport_id', 'driver_id', 'load_uom_id', 'load_rate', 'load_tax_id', 'load_site_id']);
        });
    }
};
