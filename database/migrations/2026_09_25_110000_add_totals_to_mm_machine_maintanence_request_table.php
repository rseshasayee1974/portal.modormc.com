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
            if (!Schema::hasColumn('mm_machine_maintanence_request', 'amount_untaxed')) {
                $table->decimal('amount_untaxed', 17, 2)->default(0.00)->after('order_no');
            }
            if (!Schema::hasColumn('mm_machine_maintanence_request', 'amount_tax')) {
                $table->decimal('amount_tax', 17, 2)->default(0.00)->after('amount_untaxed');
            }
            if (!Schema::hasColumn('mm_machine_maintanence_request', 'amount_total')) {
                $table->decimal('amount_total', 17, 2)->default(0.00)->after('rounding_value');
            }
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
        $columns = array_values(array_filter(
            ['amount_untaxed', 'amount_tax', 'amount_total'],
            fn ($column) => Schema::hasColumn('mm_machine_maintanence_request', $column)
        ));
        if ($columns) {
            Schema::table('mm_machine_maintanence_request', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }
};
