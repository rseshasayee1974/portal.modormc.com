<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Superseded by 2026_09_11_130000_allow_deleted_payment_voucher_reuse.
        // Keep uniqueness until that migration installs its replacement.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No schema change to reverse. The replacement migration owns the index.
    }
};
