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
        Schema::table('mm_payslips', function (Blueprint $table) {
            $table->decimal('working_days', 8, 2)->change();
            $table->decimal('present_days', 8, 2)->change();
            $table->decimal('absent_days', 8, 2)->change();
            $table->decimal('paid_leave_days', 8, 2)->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_payslips', function (Blueprint $table) {
            $table->integer('working_days')->change();
            $table->integer('present_days')->change();
            $table->integer('absent_days')->change();
            $table->integer('paid_leave_days')->default(0)->change();
        });
    }
};
