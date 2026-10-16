<?php
/*
Author: Cipher
Created: 2026-10-16 13:34:03 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mm_crm_leads', function (Blueprint $table) {
            $table->foreignId('mix_design_id')->nullable()->constrained('mm_mix_designs')->nullOnDelete();
        });

        DB::table('mm_crm_leads')->whereNotNull('requirement')->orderBy('id')->chunkById(200, function ($leads) {
            foreach ($leads as $lead) {
                $matches = DB::table('mm_mix_designs')->where('plant_id', $lead->plant_id)
                    ->whereNull('deleted_at')->where('design_name', $lead->requirement)->pluck('id');
                if ($matches->count() === 1) {
                    DB::table('mm_crm_leads')->where('id', $lead->id)->update(['mix_design_id' => $matches->first()]);
                }
            }
        });
    }

    public function down(): void
    {
        DB::table('mm_crm_leads')->whereNotNull('mix_design_id')->whereNull('requirement')->orderBy('id')->chunkById(200, function ($leads) {
            foreach ($leads as $lead) {
                $name = DB::table('mm_mix_designs')->where('id', $lead->mix_design_id)->value('design_name');
                DB::table('mm_crm_leads')->where('id', $lead->id)->update(['requirement' => $name]);
            }
        });
        Schema::table('mm_crm_leads', fn (Blueprint $table) => $table->dropConstrainedForeignId('mix_design_id'));
    }
};
