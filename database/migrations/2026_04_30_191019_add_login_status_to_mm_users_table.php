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
        Schema::table('mm_users', function (Blueprint $table) {
            $table->boolean('login_status')->default(false)->after('last_visit_page')
                  ->comment('True = currently logged in, False = logged out');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_users', function (Blueprint $table) {
            $table->dropColumn('login_status');
        });
    }
};
