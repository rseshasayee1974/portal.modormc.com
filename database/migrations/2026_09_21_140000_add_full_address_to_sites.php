<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['site_address_2'=>500, 'city'=>150, 'district'=>150, 'state'=>150, 'country'=>100] as $name=>$length) {
            if (!Schema::hasColumn('mm_sites', $name)) {
                Schema::table('mm_sites', fn (Blueprint $table) => $table->string($name, $length)->nullable());
            }
        }
    }

    public function down(): void
    {
        Schema::table('mm_sites', fn (Blueprint $table) => $table->dropColumn(['site_address_2', 'city', 'district', 'state', 'country']));
    }
};
