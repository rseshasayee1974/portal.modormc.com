<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('mm_menus')->whereIn('link', ['production/pump-deployments', '/production/pump-deployments'])
            ->update(['title'=>'Pump Schedules', 'link'=>'production/pump-schedules']);
    }

    public function down(): void
    {
        DB::table('mm_menus')->where('link', 'production/pump-schedules')
            ->update(['title'=>'Pump & Boom Schedules', 'link'=>'production/pump-deployments']);
    }
};
