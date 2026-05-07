<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mm_invoices', function (Blueprint $table) {
            $table->decimal('tds_amount', 15, 2)->default(0)->after('round_off');
            $table->unsignedBigInteger('tds_tax_id')->nullable()->after('tds_amount');
        });

        Schema::table('mm_purchase_orders', function (Blueprint $table) {
            $table->decimal('tds_amount', 15, 2)->default(0)->after('rounding_value');
            $table->unsignedBigInteger('tds_tax_id')->nullable()->after('tds_amount');
        });
    }

    public function down(): void
    {
        Schema::table('mm_invoices', function (Blueprint $table) {
            $table->dropColumn(['tds_amount', 'tds_tax_id']);
        });

        Schema::table('mm_purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['tds_amount', 'tds_tax_id']);
        });
    }
};
