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
        Schema::table('mm_products', function (Blueprint $table) {
            $table->string('hsn_code', 20)->nullable()->after('code');
            $table->boolean('tax_mode')->default(0)->comment('0: Exclusive, 1: Inclusive')->after('hsn_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_products', function (Blueprint $table) {
            $table->dropColumn(['hsn_code', 'tax_mode']);
        });
    }
};
