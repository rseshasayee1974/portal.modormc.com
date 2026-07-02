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
        Schema::table('mm_customer_pos', function (Blueprint $table) {
            $table->string('prefix')->nullable()->after('plant_id');
            $table->string('reference')->nullable()->after('prefix');
        });

        // Backfill existing customer POs
        $customerPOs = DB::table('mm_customer_pos')->orderBy('id', 'asc')->get();
        foreach ($customerPOs as $po) {
            $now = \Carbon\Carbon::parse($po->order_date);
            $startYear = $now->month >= 4 ? $now->year : $now->year - 1;
            $fyString = substr($startYear, -2) . substr($startYear + 1, -2);
            $prefix = "CPO-{$fyString}-";
            
            // Format reference sequentially based on ID
            $reference = sprintf('%s%04d', $prefix, $po->id);
            DB::table('mm_customer_pos')
                ->where('id', $po->id)
                ->update([
                    'prefix' => $prefix,
                    'reference' => $reference,
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_customer_pos', function (Blueprint $table) {
            $table->dropColumn(['prefix', 'reference']);
        });
    }
};
