<?php
/*
Author: Cipher
Created: 2026-11-04 12:25:19 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mm_user_personnel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('mm_users')->cascadeOnDelete();
            $table->foreignId('personnel_id')->unique()->constrained('mm_personnels')->cascadeOnDelete();
            $table->foreignId('plant_id')->constrained('mm_plants')->cascadeOnDelete();
            $table->index(['user_id', 'plant_id']);
            $table->timestamps();
        });
        DB::table('mm_personnels')->whereNotNull('user_id')->whereNull('deleted_at')->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    if (!DB::table('mm_users')->where('id', $row->user_id)->exists()
                        || !DB::table('mm_plants')->where('id', $row->plant_id)->exists()) continue;
                    DB::table('mm_user_personnel')->insert([
                        'user_id' => $row->user_id, 'personnel_id' => $row->id, 'plant_id' => $row->plant_id,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('mm_user_personnel');
    }
};
