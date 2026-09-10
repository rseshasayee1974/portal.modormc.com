<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('mm_voucher_types')) {
            // Drop any active foreign keys on entity_id or plant_id
            $fks = DB::select("
                SELECT CONSTRAINT_NAME 
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = DATABASE() 
                  AND TABLE_NAME = 'mm_voucher_types' 
                  AND COLUMN_NAME IN ('entity_id', 'plant_id') 
                  AND REFERENCED_TABLE_NAME IS NOT NULL
            ");

            foreach ($fks as $fk) {
                try {
                    DB::statement("ALTER TABLE mm_voucher_types DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
                } catch (\Throwable $e) {}
            }

            Schema::table('mm_voucher_types', function (Blueprint $table) {
                if (Schema::hasColumn('mm_voucher_types', 'entity_id')) {
                    $table->dropColumn('entity_id');
                }
                if (Schema::hasColumn('mm_voucher_types', 'plant_id')) {
                    $table->dropColumn('plant_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('mm_voucher_types')) {
            Schema::table('mm_voucher_types', function (Blueprint $table) {
                if (!Schema::hasColumn('mm_voucher_types', 'entity_id')) {
                    $table->unsignedBigInteger('entity_id')->nullable()->after('id');
                }
            });
        }
    }
};
