<?php

/*
Author: ragul-onemodo
Created: 2026-10-17 14:22:29 Asia/Calcutta (UTC+05:30)
*/

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\Permission;
use App\Services\Gst\GstAccess;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

final class GstComplianceSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AccountingWorkspaceSeeder::class);
        foreach (GstAccess::ACTIONS as $action) {
            Permission::firstOrCreate(['name' => 'GST_COMPLIANCE.'.$action, 'guard_name' => 'web']);
        }
        Menu::updateOrCreate(['alias' => 'gst-compliance'], [
            'menutype' => 2, 'title' => 'GST Reconciliation', 'link' => 'finance/gst-compliance', 'icon' => 'DocumentTextIcon',
            'published' => 1, 'parent_id' => 9, 'level' => 1, 'ordering' => 15, 'permission_name' => 'GST_COMPLIANCE.VIEW',
        ]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
