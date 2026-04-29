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
        Schema::table('mm_mix_designs', function (Blueprint $table) {
            $table->dropColumn('unit');
            $table->foreignId('unit_id')->nullable()->after('design_type')->constrained('mm_product_units')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_mix_designs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unit_id');
            $table->string('unit', 20)->nullable()->after('design_type');
        });
    }
};
