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
        Schema::table('mm_quotation_items', function (Blueprint $table) {
            if (!Schema::hasColumn('mm_quotation_items', 'concrete_pump')) {
                $table->string('concrete_pump', 100)->nullable()->after('amount_total');
            }
            if (!Schema::hasColumn('mm_quotation_items', 'pump_rate')) {
                $table->decimal('pump_rate', 15, 2)->nullable()->default(0.00)->after('concrete_pump');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_quotation_items', function (Blueprint $table) {
            if (Schema::hasColumn('mm_quotation_items', 'pump_rate')) {
                $table->dropColumn('pump_rate');
            }
            if (Schema::hasColumn('mm_quotation_items', 'concrete_pump')) {
                $table->dropColumn('concrete_pump');
            }
        });
    }
};
