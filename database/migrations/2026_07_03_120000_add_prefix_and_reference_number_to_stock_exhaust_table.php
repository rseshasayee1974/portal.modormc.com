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
        Schema::table('mm_stock_exhaust', function (Blueprint $table) {
            $table->string('prefix')->nullable()->after('plant_id');
            $table->string('reference_number')->nullable()->after('prefix');
        });

        // Backfill existing stock exhausts
        $records = DB::table('mm_stock_exhaust')->orderBy('id', 'asc')->get();
        foreach ($records as $record) {
            $date = $record->issued_date ?? $record->created ?? now();
            $now = \Carbon\Carbon::parse($date);
            $startYear = $now->month >= 4 ? $now->year : $now->year - 1;
            $fyString = substr($startYear, -2) . substr($startYear + 1, -2);
            $prefix = "SE-{$fyString}-";
            
            // Format reference sequentially based on ID
            $referenceNumber = sprintf('%s%04d', $prefix, $record->id);
            DB::table('mm_stock_exhaust')
                ->where('id', $record->id)
                ->update([
                    'prefix' => $prefix,
                    'reference_number' => $referenceNumber,
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_stock_exhaust', function (Blueprint $table) {
            $table->dropColumn(['prefix', 'reference_number']);
        });
    }
};
