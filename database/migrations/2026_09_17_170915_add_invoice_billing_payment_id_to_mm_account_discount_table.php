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
        Schema::table('mm_account_discount', function (Blueprint $table) {
            $table->integer('invoice_id')->nullable()->after('journal_id');
            $table->integer('billing_id')->nullable()->after('invoice_id');
            $table->integer('payment_id')->nullable()->after('billing_id');
            $table->index('invoice_id');
            $table->index('billing_id');
            $table->index('payment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_account_discount', function (Blueprint $table) {
            $table->dropIndex(['invoice_id']);
            $table->dropIndex(['billing_id']);
            $table->dropIndex(['payment_id']);
            $table->dropColumn(['invoice_id', 'billing_id', 'payment_id']);
        });
    }
};
