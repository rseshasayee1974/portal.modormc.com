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
        Schema::table('mm_machine_maintanence_request', function (Blueprint $table) {
            $table->decimal('amount_untaxed', 17, 2)->default(0.00)->after('order_no');
            $table->decimal('amount_tax', 17, 2)->default(0.00)->after('amount_untaxed');
            $table->decimal('amount_total', 17, 2)->default(0.00)->after('rounding_value');
        });

        Schema::table('mm_machine_maintanence_lines', function (Blueprint $table) {
            $table->unsignedBigInteger('product_uom')->nullable()->change();
            $table->unsignedBigInteger('product_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_machine_maintanence_request', function (Blueprint $table) {
            $table->dropColumn(['amount_untaxed', 'amount_tax', 'amount_total']);
        });
    }
};
