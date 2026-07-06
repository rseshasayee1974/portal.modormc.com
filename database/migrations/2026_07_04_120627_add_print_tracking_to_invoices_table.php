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
            $table->unsignedInteger('print_count')->default(0)->after('status');
            $table->timestamp('first_printed_at')->nullable()->after('print_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_invoices', function (Blueprint $table) {
            $table->dropColumn(['print_count', 'first_printed_at']);
        });
    }
};
