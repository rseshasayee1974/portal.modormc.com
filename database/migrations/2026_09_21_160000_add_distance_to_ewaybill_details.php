<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('mm_ewaybill_details', 'distance_km')) {
            Schema::table('mm_ewaybill_details', function (Blueprint $table) {
                $table->unsignedInteger('distance_km')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('mm_ewaybill_details', fn (Blueprint $table) => $table->dropColumn('distance_km'));
    }
};
