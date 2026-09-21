<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('mm_inventory_audit_logs', 'action_type')) {
            Schema::table('mm_inventory_audit_logs', function (Blueprint $table) {
                // Historical entries retain their original fields; new writes use this action.
                $table->string('action_type', 20)->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        Schema::table('mm_inventory_audit_logs', fn (Blueprint $table) => $table->dropColumn('action_type'));
    }
};
