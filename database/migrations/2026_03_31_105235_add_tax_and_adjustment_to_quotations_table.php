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
        Schema::table('mm_quotations', function (Blueprint $table) {
            $table->unsignedBigInteger('tax_id')->nullable()->after('amount_untaxed');
            $table->decimal('tax_amount', 15, 2)->default(0)->after('tax_id');
            $table->decimal('adjustment', 15, 2)->default(0)->after('tax_amount');
            
            $table->foreign('tax_id')->references('id')->on('mm_taxes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_quotations', function (Blueprint $table) {
            $table->dropForeign(['tax_id']);
            $table->dropColumn(['tax_id', 'tax_amount', 'adjustment']);
        });
    }
};
