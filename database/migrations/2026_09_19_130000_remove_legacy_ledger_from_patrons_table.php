<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Preserve legacy mappings without overwriting explicitly chosen ledgers.
        DB::table('mm_patrons')->whereNotNull('ledger_id')->update([
            'debit_ledger_id' => DB::raw('COALESCE(debit_ledger_id, ledger_id)'),
            'credit_ledger_id' => DB::raw('COALESCE(credit_ledger_id, ledger_id)'),
        ]);

        // Some installations have only an index on the legacy column.
        foreach (Schema::getForeignKeys('mm_patrons') as $foreignKey) {
            if ($foreignKey['columns'] === ['ledger_id']) {
                $key = DB::getDriverName() === 'sqlite' ? ['ledger_id'] : $foreignKey['name'];
                Schema::table('mm_patrons', fn (Blueprint $table) => $table->dropForeign($key));
            }
        }

        Schema::table('mm_patrons', fn (Blueprint $table) => $table->dropColumn('ledger_id'));
    }

    public function down(): void
    {
        Schema::table('mm_patrons', function (Blueprint $table) {
            $table->foreignId('ledger_id')->nullable()->constrained('mm_ledgers')->nullOnDelete();
        });
        DB::table('mm_patrons')->update([
            'ledger_id' => DB::raw('COALESCE(debit_ledger_id, credit_ledger_id)'),
        ]);
    }
};
