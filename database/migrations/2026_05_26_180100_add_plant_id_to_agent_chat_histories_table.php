<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_chat_histories', function (Blueprint $table) {
            $table->unsignedBigInteger('plant_id')->nullable()->after('user_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('agent_chat_histories', function (Blueprint $table) {
            $table->dropColumn('plant_id');
        });
    }
};
