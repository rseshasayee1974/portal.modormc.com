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
        Schema::table('mm_batches', function (Blueprint $table) {
            $table->longText('empty_weight_photo')->nullable()->after('empty_weight_truck');
            $table->longText('loaded_weight_photo')->nullable()->after('loaded_weight_truck');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_batches', function (Blueprint $table) {
            $table->dropColumn(['empty_weight_photo', 'loaded_weight_photo']);
        });
    }
};
