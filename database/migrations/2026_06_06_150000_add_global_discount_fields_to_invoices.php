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
            $table->string('global_discount_type', 20)->default('₹')->after('subtotal');
            $table->decimal('global_discount', 17, 2)->default(0)->after('global_discount_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_invoices', function (Blueprint $table) {
            $table->dropColumn(['global_discount_type', 'global_discount']);
        });
    }
};
