<?php
/*
Author: ragul-onemodo
Created: 2026-10-30 15:40:00 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Login and other account changes can occur before a plant is selected.
        Schema::table('mm_inventory_audit_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('plant_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // The original plant_id migration was already nullable. Keep that contract
        // and retain account audit records created without a plant context.
    }
};
