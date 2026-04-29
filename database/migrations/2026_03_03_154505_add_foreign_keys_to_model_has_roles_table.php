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
        Schema::table('mm_model_has_roles', function (Blueprint $table) {
            $table->foreign(['role_id'])->references(['id'])->on('mm_roles')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_model_has_roles', function (Blueprint $table) {
            $table->dropForeign('mm_model_has_roles_role_id_foreign');
        });
    }
};
