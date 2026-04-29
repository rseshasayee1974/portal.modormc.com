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
        Schema::table('mm_taxes', function (Blueprint $table) {
            $table->enum('tax_group', ['GST', 'CGST', 'SGST', 'IGST', 'TDS', 'TCS', 'CESS', 'OTHER'])->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_taxes', function (Blueprint $table) {
            $table->enum('tax_group', ['GST', 'CGST', 'SGST', 'IGST', 'TDS', 'OTHER'])->change();
        });
    }
};
