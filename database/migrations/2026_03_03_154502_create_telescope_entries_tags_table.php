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
        Schema::create('mm_telescope_entries_tags', function (Blueprint $table) {
            $table->char('entry_uuid', 36);
            $table->string('tag', 191)->index();

            $table->primary(['entry_uuid', 'tag']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mm_telescope_entries_tags');
    }
};
