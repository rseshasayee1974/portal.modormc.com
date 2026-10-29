<?php

/*
Author: ragul-onemodo
Created: 2026-10-18 21:18:57 Asia/Calcutta (UTC+05:30)
*/

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\Permission;
use App\Services\Finance\AccountingWorkspace;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

final class AccountingWorkspaceSeeder extends Seeder
{
    public function run(): void
    {
        foreach (AccountingWorkspace::ACTIONS as $action) {
            Permission::firstOrCreate(['name' => 'ACCOUNTING.'.$action, 'guard_name' => 'web']);
        }
        foreach (['dashboard' => 'Accounting Dashboard', 'accounts' => 'Chart of Accounts', 'journals' => 'Journal Entries', 'ledger' => 'General Ledger', 'trial' => 'Trial Balance', 'reports' => 'Financial Reports', 'audit' => 'Audit Trail', 'tally' => 'TallyPrime Integration'] as $view => $title) {
            Menu::updateOrCreate(['alias' => 'accounting-'.$view], ['menutype' => 2, 'title' => $title, 'link' => 'finance/accounting?view='.$view, 'icon' => 'DocumentTextIcon', 'published' => 1, 'parent_id' => 9, 'level' => 1, 'ordering' => 16 + array_search($view, ['dashboard', 'accounts', 'journals', 'ledger', 'trial', 'reports', 'audit', 'tally']), 'permission_name' => $view === 'tally' ? 'ACCOUNTING.TALLY' : 'ACCOUNTING.VIEW']);
        }
        Menu::updateOrCreate(['alias' => 'gstr3b-review'], ['menutype' => 2, 'title' => 'GSTR-3B Review', 'link' => 'finance/gst-compliance', 'icon' => 'DocumentTextIcon', 'published' => 1, 'parent_id' => 9, 'level' => 1, 'ordering' => 24, 'permission_name' => 'GST_COMPLIANCE.VIEW']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
