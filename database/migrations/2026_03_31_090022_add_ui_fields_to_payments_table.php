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
        Schema::table('mm_payments', function (Blueprint $table) {
            $table->string('partner_type')->nullable()->after('patron_id');
            $table->decimal('excess_amount', 12, 2)->default(0)->after('amount');
            $table->boolean('use_excess_amount')->default(false)->after('excess_amount');
            $table->string('transaction_mode')->nullable()->after('transaction_type');
            $table->boolean('reconcile_opening_balance')->default(false)->after('transaction_mode');
            $table->boolean('batch_deposit')->default(false)->after('reconcile_opening_balance');
            $table->date('transaction_date')->nullable()->after('plant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_payments', function (Blueprint $table) {
            $table->dropColumn([
                'partner_type', 'excess_amount', 'use_excess_amount', 
                'transaction_mode', 'reconcile_opening_balance', 
                'batch_deposit', 'transaction_date'
            ]);
        });
    }
};
