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
        if (!Schema::hasColumn('mm_machines', 'entity_id')) {
            Schema::table('mm_machines', function (Blueprint $table) {
                $table->foreignId('entity_id')->nullable()->after('plant_id')->constrained('mm_entities')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('mm_machines', 'entity_id')) {
            Schema::table('mm_machines', function (Blueprint $table) {
                $table->dropForeign(['entity_id']);
                $table->dropColumn('entity_id');
            });
        }
    }
};
