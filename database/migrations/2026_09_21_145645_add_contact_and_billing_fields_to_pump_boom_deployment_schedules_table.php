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
        Schema::table('mm_pump_boom_deployment_schedule', function (Blueprint $table) {
            $table->string('site_contact_number', 100)->nullable()->after('site_name');
            $table->string('driver_contact_number', 100)->nullable()->after('operator_name');
            $table->string('billing_name', 200)->nullable()->after('driver_contact_number');
        });
    }

    public function down(): void
    {
        Schema::table('mm_pump_boom_deployment_schedule', function (Blueprint $table) {
            $table->dropColumn([
                'site_contact_number',
                'driver_contact_number',
                'billing_name',
            ]);
        });
    }
};
