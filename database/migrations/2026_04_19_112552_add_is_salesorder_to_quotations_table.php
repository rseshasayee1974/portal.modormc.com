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
            if (!Schema::hasColumn('mm_quotations', 'is_salesorder')) {
                $table->tinyInteger('is_salesorder')->default(0)->after('status')->comment('0=None, 1=Converted, -1=Rejected');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_quotations', function (Blueprint $table) {
            $table->dropColumn('is_salesorder');
        });
    }
};
