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
        Schema::table('mm_plants', function (Blueprint $table) {
            $table->string('scheduler_api_url')->nullable()->after('is_initialized');
            $table->text('scheduler_api_token')->nullable()->after('scheduler_api_url');
            $table->string('scheduler_oauth_url')->nullable()->after('scheduler_api_token');
            $table->string('scheduler_client_id')->nullable()->after('scheduler_oauth_url');
            $table->text('scheduler_client_secret')->nullable()->after('scheduler_client_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_plants', function (Blueprint $table) {
            $table->dropColumn([
                'scheduler_api_url',
                'scheduler_api_token',
                'scheduler_oauth_url',
                'scheduler_client_id',
                'scheduler_client_secret'
            ]);
        });
    }
};
