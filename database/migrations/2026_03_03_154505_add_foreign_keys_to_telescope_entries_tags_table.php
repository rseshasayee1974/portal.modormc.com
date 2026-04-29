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
        Schema::table('mm_telescope_entries_tags', function (Blueprint $table) {
            $table->foreign(['entry_uuid'])->references(['uuid'])->on('mm_telescope_entries')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_telescope_entries_tags', function (Blueprint $table) {
            $table->dropForeign('mm_telescope_entries_tags_entry_uuid_foreign');
        });
    }
};
