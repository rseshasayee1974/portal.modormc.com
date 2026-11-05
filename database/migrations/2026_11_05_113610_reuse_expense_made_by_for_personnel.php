<?php
/*
Author: ragul-onemodo
Created: 2026-11-05 11:36:10 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->dropMadeByForeignKey();
        // Keep the old login identity only in the audit field. Never interpret a user ID as an employee ID.
        DB::table('mm_expenses')->whereNull('created_by')->update(['created_by' => DB::raw('made_by')]);
        DB::table('mm_expenses')->update(['made_by' => DB::raw('made_by_personnel_id')]);
        Schema::table('mm_expenses', function (Blueprint $table) {
            $table->unsignedBigInteger('made_by')->nullable()->change();
            $table->foreign('made_by')->references('id')->on('mm_personnels')->nullOnDelete();
            $table->dropConstrainedForeignId('made_by_personnel_id');
        });
    }

    public function down(): void
    {
        $this->dropMadeByForeignKey();
        Schema::table('mm_expenses', function (Blueprint $table) {
            $table->foreignId('made_by_personnel_id')->nullable()->constrained('mm_personnels')->nullOnDelete();
        });
        DB::table('mm_expenses')->update(['made_by_personnel_id' => DB::raw('made_by')]);
        DB::table('mm_expenses')->update(['made_by' => null]);
        DB::table('mm_expenses')->whereIn('created_by', DB::table('mm_users')->select('id'))
            ->update(['made_by' => DB::raw('created_by')]);
        Schema::table('mm_expenses', fn (Blueprint $table) => $table->foreign('made_by')->references('id')->on('mm_users')->nullOnDelete());
    }

    private function dropMadeByForeignKey(): void
    {
        foreach (Schema::getForeignKeys('mm_expenses') as $key) {
            if ($key['columns'] === ['made_by']) {
                $constraint = DB::getDriverName() === 'sqlite' ? ['made_by'] : $key['name'];
                Schema::table('mm_expenses', fn (Blueprint $table) => $table->dropForeign($constraint));
            }
        }
    }
};
