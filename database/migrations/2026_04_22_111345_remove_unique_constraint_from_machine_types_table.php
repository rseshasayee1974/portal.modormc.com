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
        Schema::table('mm_machine_types', function (Blueprint $table) {
            $table->dropUnique('mm_machine_types_name_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_machine_types', function (Blueprint $table) {
            $table->unique('name', 'mm_machine_types_name_unique');
        });
    }
};
