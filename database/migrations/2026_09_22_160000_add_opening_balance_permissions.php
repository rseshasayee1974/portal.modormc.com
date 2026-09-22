<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration {
    private array $actions = [
        'VIEW' => 'View opening balances and access the menu',
        'CREATE' => 'Create opening balances and convert legacy balances',
        'UPDATE' => 'Update existing opening balances',
        'DELETE' => 'Remove opening balances',
        'AUDIT_LOG' => 'View and export opening balance audit logs',
    ];

    public function up(): void
    {
        DB::transaction(function () {
            foreach ($this->actions as $action => $description) {
                DB::table('mm_permissions')->updateOrInsert(
                    ['name' => 'OPENING_BALANCE.'.$action, 'guard_name' => 'web'],
                    ['module' => 'OPENING_BALANCE', 'description' => $description,
                        'is_system' => true, 'created_at' => now(), 'updated_at' => now()],
                );
            }
            $permissionIds = DB::table('mm_permissions')->whereIn('name', $this->names())->where('guard_name', 'web')->pluck('id');
            $adminRoleIds = DB::table('mm_roles')->whereNull('deleted_at')->where('guard_name', 'web')->where(function ($query) {
                $query->whereIn('code', ['SAAS_OWNER', 'PLATFORM_ADMIN', 'SUPER_ADMIN', 'ADMINISTRATOR'])
                    ->orWhereIn('name', ['Saas Owner', 'Platform Admin', 'Super Admin', 'Super Administrator', 'Administrator', 'Admin']);
            })->pluck('id');
            foreach ($adminRoleIds as $roleId) {
                foreach ($permissionIds as $permissionId) {
                    DB::table('mm_role_has_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId]);
                }
            }
            DB::table('mm_menus')->where('alias', 'opening-balances')
                ->update(['permission_name' => 'OPENING_BALANCE.VIEW', 'updated_at' => now()]);
        });
        $this->clearPermissionCache();
    }

    public function down(): void
    {
        DB::transaction(function () {
            DB::table('mm_menus')->where('alias', 'opening-balances')->where('permission_name', 'OPENING_BALANCE.VIEW')
                ->update(['permission_name' => 'JOURNAL_ENTRY.VIEW', 'updated_at' => now()]);
            $ids = DB::table('mm_permissions')->whereIn('name', $this->names())->where('guard_name', 'web')->pluck('id');
            DB::table('mm_role_has_permissions')->whereIn('permission_id', $ids)->delete();
            DB::table('mm_model_has_permissions')->whereIn('permission_id', $ids)->delete();
            DB::table('mm_permissions')->whereIn('id', $ids)->delete();
        });
        $this->clearPermissionCache();
    }

    private function names(): array
    {
        return array_map(fn ($action) => 'OPENING_BALANCE.'.$action, array_keys($this->actions));
    }

    private function clearPermissionCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        \App\Models\EntityUser::clearGlobalRolesCache();
    }
};
