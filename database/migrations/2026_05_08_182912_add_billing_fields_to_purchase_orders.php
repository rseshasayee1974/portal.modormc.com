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
        Schema::table('mm_purchase_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('billing_id')->nullable()->after('id');
            $table->enum('billing_status', ['Pending', 'Billed'])->default('Pending')->after('billing_id');
            
            $table->index('billing_id');
            $table->index('billing_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['billing_id', 'billing_status']);
        });
    }
};
