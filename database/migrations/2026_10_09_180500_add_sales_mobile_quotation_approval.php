<?php
/*
Author: ragul-onemodo
Created: 2026-10-09 18:05:42 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema, DB};

return new class extends Migration {
    public function up(): void
    {
        Schema::table('mm_quotations', function (Blueprint $table) {
            $table->string('mobile_approval_state', 20)->default('draft')->index();
            $table->unsignedBigInteger('mobile_submitted_by')->nullable();
            $table->timestamp('mobile_submitted_at')->nullable();
            $table->unsignedBigInteger('mobile_reviewed_by')->nullable();
            $table->timestamp('mobile_reviewed_at')->nullable();
            $table->text('mobile_approval_comment')->nullable();
        });
        foreach (['CRM_LEAD.VIEW_ALL', 'QUOTATION.VIEW_ALL', 'QUOTATION.APPROVE', 'SALES_ORDER.VIEW_ALL', 'PATRON.VIEW_ALL'] as $name) {
            if (!DB::table('mm_permissions')->where('name', $name)->where('guard_name', 'web')->exists()) {
                DB::table('mm_permissions')->insert(['name' => $name, 'guard_name' => 'web', 'module' => explode('.', $name)[0], 'description' => 'Sales mobile '.strtolower(str_replace(['.', '_'], ' ', $name)), 'is_system' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
            $permission = DB::table('mm_permissions')->where('name', $name)->where('guard_name', 'web')->value('id');
            foreach (DB::table('mm_roles')->whereNull('deleted_at')->whereIn('code', ['SAAS_OWNER', 'PLATFORM_ADMIN', 'SUPER_ADMIN', 'ADMINISTRATOR', 'SALES_MANAGER'])->pluck('id') as $role) {
                DB::table('mm_role_has_permissions')->insertOrIgnore(['role_id' => $role, 'permission_id' => $permission]);
            }
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        \App\Models\EntityUser::clearGlobalRolesCache();
    }
    public function down(): void
    {
        Schema::table('mm_quotations', function (Blueprint $table) {
            $table->dropIndex(['mobile_approval_state']);
            $table->dropColumn(['mobile_approval_state', 'mobile_submitted_by', 'mobile_submitted_at', 'mobile_reviewed_by', 'mobile_reviewed_at', 'mobile_approval_comment']);
        });
        // Permission grants can predate this migration; preserve them on rollback.
    }
};
