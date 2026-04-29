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
        Schema::table('mm_patrons', function (Blueprint $table) {
            $table->json('patron_type')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('mm_patrons', function (Blueprint $table) {
            $table->string('patron_type', 50)->default('Customer')->change();
        });
    }
};
