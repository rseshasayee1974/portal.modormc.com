<?php

/*
Author: Cipher
Created: 2026-10-17 14:56:06 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getColumnType('mm_sites', 'patron_id') === 'json') {
            return;
        }
        foreach (Schema::getForeignKeys('mm_sites') as $foreign) {
            if ($foreign['columns'] === ['patron_id']) {
                $constraint = DB::getDriverName() === 'sqlite' ? ['patron_id'] : $foreign['name'];
                Schema::table('mm_sites', fn (Blueprint $table) => $table->dropForeign($constraint));
            }
        }
        Schema::table('mm_sites', fn (Blueprint $table) => $table->json('patron_id')->nullable()->change());
        DB::table('mm_sites')->whereNotNull('patron_id')->orderBy('id')->chunkById(200, function ($sites) {
            foreach ($sites as $site) {
                $ids = json_decode((string) $site->patron_id, true);
                if (! is_array($ids)) {
                    DB::table('mm_sites')->where('id', $site->id)->update(['patron_id' => json_encode([(int) $site->patron_id])]);
                }
            }
        });
    }

    public function down(): void
    {
        // A site can now belong to several customers; preserve those links on rollback.
    }
};
