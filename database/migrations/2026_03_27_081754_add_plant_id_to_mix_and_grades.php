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
        // Redundant - plant_id already exists in create migrations
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Redundant
    }
};
