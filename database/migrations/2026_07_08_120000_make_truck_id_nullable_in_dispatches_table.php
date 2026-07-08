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
        Schema::table('mm_dispatches', function (Blueprint $table) {
            $table->unsignedBigInteger('truck_id')->nullable()->change();
            $table->dateTime('dispatch_time')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_dispatches', function (Blueprint $table) {
            $table->unsignedBigInteger('truck_id')->nullable(false)->change();
            $table->dateTime('dispatch_time')->nullable(false)->change();
        });
    }
};
