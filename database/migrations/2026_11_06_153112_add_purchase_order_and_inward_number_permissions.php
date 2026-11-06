<?php
/*
Author: ragul-onemodo
Created: 2026-11-06 15:31:12 Asia/Calcutta (UTC+05:30)
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    private function permissions(): array
    {
        return [
            'PURCHASE_ORDER.EDIT_REFERENCE_NUMBER' => 'Edit Purchase Order Reference Number',
            'INWARD.EDIT_REFERENCE_NUMBER' => 'Edit Inward Reference Number',
        ];
    }

    public function up(): void
    {
        DB::transaction(function () {
            foreach ($this->permissions() as $name => $description) {
                if (DB::table('mm_permissions')->where('name', $name)->where('guard_name', 'web')->exists()) {
                    continue;
                }

                DB::table('mm_permissions')->insert([
                    'name' => $name,
                    'guard_name' => 'web',
                    'module' => explode('.', $name)[0],
                    'description' => $description,
                    'is_system' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        $this->clearPermissionCache();
    }

    public function down(): void
    {
        DB::transaction(function () {
            $ids = DB::table('mm_permissions')->where('guard_name', 'web')
                ->whereIn('name', array_keys($this->permissions()))->pluck('id');
            foreach (['mm_role_has_permissions', 'mm_model_has_permissions'] as $table) {
                DB::table($table)->whereIn('permission_id', $ids)->delete();
            }
            DB::table('mm_permissions')->whereIn('id', $ids)->delete();
        });

        $this->clearPermissionCache();
    }

    private function clearPermissionCache(): void
    {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        \App\Models\EntityUser::clearGlobalRolesCache();
    }
};
