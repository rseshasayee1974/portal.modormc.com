<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mm_patrons', function (Blueprint $table) {
            $table->foreignId('debit_ledger_id')->nullable()->constrained('mm_ledgers')->nullOnDelete();
            $table->foreignId('credit_ledger_id')->nullable()->constrained('mm_ledgers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mm_patrons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('debit_ledger_id');
            $table->dropConstrainedForeignId('credit_ledger_id');
        });
    }
};
