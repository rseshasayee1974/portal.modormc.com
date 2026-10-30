<?php
// Author: Cipher
// Created: 2026-10-29 18:25:59 Asia/Calcutta (UTC+05:30)

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('mm_customer_pos')) {
            throw new RuntimeException('Create the base Customer PO tables before aligning mobile header fields.');
        }

        // Some installations already have these columns from their original schema.
        // Add only missing fields; never change existing types, values or constraints.
        $columns = [
            'due_date' => fn (Blueprint $t) => $t->date('due_date')->nullable(),
            'concrete_pump' => fn (Blueprint $t) => $t->string('concrete_pump', 191)->nullable(),
            'pump_rate' => fn (Blueprint $t) => $t->decimal('pump_rate', 15, 2)->nullable()->default(0),
            'adjustment' => fn (Blueprint $t) => $t->decimal('adjustment', 15, 2)->default(0),
            'is_sales_order' => fn (Blueprint $t) => $t->tinyInteger('is_sales_order')->default(0),
        ];
        foreach ($columns as $name => $addColumn) {
            if (!Schema::hasColumn('mm_customer_pos', $name)) {
                Schema::table('mm_customer_pos', $addColumn);
            }
        }
    }

    public function down(): void
    {
        // Intentionally additive: removing these columns could destroy existing
        // business data or columns that predated this compatibility migration.
    }
};
