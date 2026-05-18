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
        Schema::table('mm_invoices', function (Blueprint $table) {
            $table->decimal('paid_amount', 15, 2)->default(0)->after('total_amount');
            $table->decimal('balance_amount', 15, 2)->default(0)->after('paid_amount');
        });

        // Initialize balance_amount for existing records
        DB::table('mm_invoices')->update([
            'balance_amount' => DB::raw('total_amount - paid_amount')
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_invoices', function (Blueprint $table) {
            $table->dropColumn(['paid_amount', 'balance_amount']);
        });
    }
};
