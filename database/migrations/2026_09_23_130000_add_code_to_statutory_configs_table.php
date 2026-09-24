<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mm_statutory_configs', function (Blueprint $table) {
            if (!Schema::hasColumn('mm_statutory_configs', 'code')) {
                $table->string('code', 50)->nullable()->after('statute_name')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('mm_statutory_configs', function (Blueprint $table) {
            if (Schema::hasColumn('mm_statutory_configs', 'code')) {
                $table->dropColumn('code');
            }
        });
    }
};
