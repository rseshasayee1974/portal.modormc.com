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
        Schema::table('mm_machine_documents', function (Blueprint $table) {
            $table->unique(['machine_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_machine_documents', function (Blueprint $table) {
            $table->dropUnique(['machine_id', 'type']);
        });
    }
};
