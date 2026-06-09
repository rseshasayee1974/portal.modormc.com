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
        // 1. Clean up any existing negative values to ensure migration succeeds cleanly
        DB::table('mm_quantity')->where('quantity', '<', 0)->update(['quantity' => 0]);
        DB::table('mm_quantity')->where('opening_quantity', '<', 0)->update(['opening_quantity' => 0]);

        // 2. Add check constraints (skipped in SQLite)
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE mm_quantity ADD CONSTRAINT chk_quantity_positive CHECK (quantity >= 0)');
            DB::statement('ALTER TABLE mm_quantity ADD CONSTRAINT chk_opening_quantity_positive CHECK (opening_quantity >= 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop check constraints (skipped in SQLite)
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE mm_quantity DROP CONSTRAINT chk_quantity_positive');
            DB::statement('ALTER TABLE mm_quantity DROP CONSTRAINT chk_opening_quantity_positive');
        }
    }
};
