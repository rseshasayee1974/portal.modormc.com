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
        Schema::table('mm_journal_entries', function (Blueprint $table) {
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexes = array_map(fn($index) => $index->getName(), $sm->listTableIndexes('mm_journal_entries'));
            
            if (in_array('uk_voucher', $indexes)) {
                $table->dropUnique('uk_voucher');
            }
            if (!in_array('idx_voucher', $indexes)) {
                $table->index(['plant_id', 'voucher_type', 'voucher_number'], 'idx_voucher');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_journal_entries', function (Blueprint $table) {
            $table->dropIndex('idx_voucher');
            $table->unique(['plant_id', 'voucher_type', 'voucher_number'], 'uk_voucher');
        });
    }
};
