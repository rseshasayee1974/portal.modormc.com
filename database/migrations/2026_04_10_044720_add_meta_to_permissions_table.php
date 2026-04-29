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
        Schema::table('mm_permissions', function (Blueprint $table) {
            $table->string('code')->nullable()->after('id')->index();
            $table->string('module')->nullable()->after('name')->index();
            $table->text('description')->nullable()->after('module');
            $table->boolean('is_system')->default(false)->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mm_permissions', function (Blueprint $table) {
            $table->dropColumn(['code', 'mm_module', 'description', 'is_system']);
        });
    }
};
