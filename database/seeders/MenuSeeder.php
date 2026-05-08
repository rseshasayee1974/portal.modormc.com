<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Menu;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $menus = [
            // Top Navigation (menutype 1)
            ['id' => 1,  'menutype' => 1, 'title' => 'Dashboard',  'alias' => 'dashboard', 'link' => 'dashboard',           'icon' => 'HomeIcon',                  'published' => 1, 'parent_id' => 0, 'level' => 0, 'ordering' => 1,  'permission_name' => 'DASHBOARD.VIEW'],
            ['id' => 2,  'menutype' => 1, 'title' => 'Master',     'alias' => 'master',    'link' => 'master/taxes',         'icon' => 'Square3Stack3DIcon',         'published' => 1, 'parent_id' => 0, 'level' => 0, 'ordering' => 2,  'permission_name' => 'ADDRESS_TYPE.VIEW'],
            ['id' => 3,  'menutype' => 0, 'title' => 'Order',      'alias' => 'orders',    'link' => 'orders/purchaseorder', 'icon' => 'CreditCardIcon',             'published' => 1, 'parent_id' => 0, 'level' => 0, 'ordering' => 3,  'permission_name' => 'QUOTATION.VIEW'],
            ['id' => 4,  'menutype' => 1, 'title' => 'Machines',      'alias' => 'machines',     'link' => 'fleet/machines',       'icon' => 'TruckIcon',                  'published' => 1, 'parent_id' => 0, 'level' => 0, 'ordering' => 4,  'permission_name' => 'MACHINE.VIEW'],
            ['id' => 5,  'menutype' => 1, 'title' => 'Inventory',  'alias' => 'inventory', 'link' => 'inventory/products',   'icon' => 'ShoppingCartIcon',           'published' => 1, 'parent_id' => 0, 'level' => 0, 'ordering' => 5,  'permission_name' => 'PRODUCT.VIEW'],
            ['id' => 6,  'menutype' => 1, 'title' => 'Batching',   'alias' => 'Batching',  'link' => 'orders/batches',       'icon' => 'ClipboardDocumentListIcon',  'published' => 1, 'parent_id' => 0, 'level' => 0, 'ordering' => 6,  'permission_name' => 'PATRON.VIEW'],
            ['id' => 7,  'menutype' => 1, 'title' => 'Tenant',     'alias' => 'tenant',    'link' => 'tenant/entities',      'icon' => 'BuildingLibraryIcon',        'published' => 1, 'parent_id' => 0, 'level' => 0, 'ordering' => 7,  'permission_name' => 'ENTITY.VIEW'],
            ['id' => 8,  'menutype' => 1, 'title' => 'Membership', 'alias' => 'users',     'link' => 'membership/users',     'icon' => 'IdentificationIcon',         'published' => 1, 'parent_id' => 0, 'level' => 0, 'ordering' => 8,  'permission_name' => 'USER.VIEW'],
            ['id' => 9,  'menutype' => 1, 'title' => 'Finance',    'alias' => 'finance',   'link' => 'finance/ledgers',      'icon' => 'BriefcaseIcon',             'published' => 1, 'parent_id' => 0, 'level' => 0, 'ordering' => 9,  'permission_name' => 'ACCOUNT.VIEW'],
            ['id' => 10, 'menutype' => 1, 'title' => 'Report',     'alias' => 'report',    'link' => 'reports/report',                    'icon' => 'ChartBarIcon',               'published' => 1, 'parent_id' => 0, 'level' => 0, 'ordering' => 10, 'permission_name' => 'REPORT.VIEW'],
            ['id' => 11, 'menutype' => 1, 'title' => 'Setting',    'alias' => 'Setting',   'link' => 'settings/sites',       'icon' => 'Cog6ToothIcon',              'published' => 1, 'parent_id' => 0, 'level' => 0, 'ordering' => 10, 'permission_name' => 'SITES.VIEW'],

            // Sidebar: Membership (parent_id = 8)
            ['id' => 12, 'menutype' => 2, 'title' => 'User Management', 'alias' => 'Users',     'link' => 'membership/users',     'icon' => 'UsersIcon',           'published' => 1, 'parent_id' => 8,  'level' => 1, 'ordering' => 1, 'permission_name' => 'USER.VIEW'],
            ['id' => 20, 'menutype' => 2, 'title' => 'Personnel',       'alias' => 'personnel', 'link' => 'membership/personnel', 'icon' => 'IdentificationIcon',  'published' => 1, 'parent_id' => 6,  'level' => 1, 'ordering' => 5, 'permission_name' => 'PERSONNEL.VIEW'],

            // Sidebar: Master (parent_id = 2)
            ['id' => 13, 'menutype' => 2, 'title' => 'Roles',                 'alias' => 'Roles',                 'link' => 'settings/roles',             'icon' => 'ShieldCheckIcon',          'published' => 1, 'parent_id' => 2,  'level' => 1, 'ordering' => 1,  'permission_name' => 'ROLE.VIEW'],
            ['id' => 14, 'menutype' => 2, 'title' => 'Permission',            'alias' => 'Permissions',           'link' => 'settings/permissions',       'icon' => 'KeyIcon',                  'published' => 1, 'parent_id' => 2,  'level' => 1, 'ordering' => 2,  'permission_name' => 'PERMISSION.VIEW'],
            ['id' => 16, 'menutype' => 2, 'title' => 'Address Type',          'alias' => 'AddressTypes',          'link' => 'master/addresstypes',        'icon' => 'MapIcon',                  'published' => 1, 'parent_id' => 2,  'level' => 1, 'ordering' => 1,  'permission_name' => 'ADDRESS_TYPE.VIEW'],
            ['id' => 17, 'menutype' => 2, 'title' => 'Bank Account Type',     'alias' => 'BankAccountTypes',      'link' => 'master/bankaccounttypes',    'icon' => 'CreditCardIcon',           'published' => 1, 'parent_id' => 2,  'level' => 1, 'ordering' => 2,  'permission_name' => 'BANK_ACCOUNT_TYPE.VIEW'],
            ['id' => 18, 'menutype' => 2, 'title' => 'Contact Type',          'alias' => 'ContactTypes',          'link' => 'master/contacttypes',        'icon' => 'PhoneIcon',                'published' => 1, 'parent_id' => 2,  'level' => 1, 'ordering' => 3,  'permission_name' => 'CONTACT_TYPE.VIEW'],
            ['id' => 19, 'menutype' => 2, 'title' => 'Countries',             'alias' => 'Countries',             'link' => 'master/countries',           'icon' => 'GlobeAltIcon',             'published' => 1, 'parent_id' => 2,  'level' => 1, 'ordering' => 4,  'permission_name' => 'COUNTRY.VIEW'],
            ['id' => 23, 'menutype' => 2, 'title' => 'Currencies',            'alias' => 'Currencies',            'link' => 'master/currencies',          'icon' => 'CurrencyDollarIcon',       'published' => 1, 'parent_id' => 2,  'level' => 1, 'ordering' => 5,  'permission_name' => 'CURRENCY.VIEW'],
            ['id' => 24, 'menutype' => 2, 'title' => 'Entity Type',           'alias' => 'EntityTypes',           'link' => 'master/entitytypes',         'icon' => 'BuildingOfficeIcon',       'published' => 1, 'parent_id' => 2,  'level' => 1, 'ordering' => 6,  'permission_name' => 'ENTITY_TYPE.VIEW'],
            ['id' => 27, 'menutype' => 2, 'title' => 'Invoice Status',        'alias' => 'InvoiceStatuses',       'link' => 'master/invoicestatuses',     'icon' => 'ReceiptPercentIcon',       'published' => 1, 'parent_id' => 2,  'level' => 1, 'ordering' => 7,  'permission_name' => 'INVOICE_STATUS.VIEW'],
            ['id' => 29, 'menutype' => 2, 'title' => 'Plan',                  'alias' => 'Plans',                 'link' => 'master/plans',               'icon' => 'ClipboardDocumentListIcon','published' => 1, 'parent_id' => 2,  'level' => 1, 'ordering' => 8,  'permission_name' => 'PLAN.VIEW'],
            ['id' => 30, 'menutype' => 2, 'title' => 'State Code',            'alias' => 'StateCodes',            'link' => 'master/statecodes',          'icon' => 'MapPinIcon',               'published' => 1, 'parent_id' => 2,  'level' => 1, 'ordering' => 9,  'permission_name' => 'STATE_CODE.VIEW'],
            ['id' => 31, 'menutype' => 2, 'title' => 'Subscription Status',   'alias' => 'SubscriptionStatuses',  'link' => 'master/subscriptionstatuses','icon' => 'ArrowPathIcon',            'published' => 1, 'parent_id' => 2,  'level' => 1, 'ordering' => 10, 'permission_name' => 'SUBSCRIPTION_STATUS.VIEW'],
            ['id' => 41, 'menutype' => 2, 'title' => 'Tax Configuration',     'alias' => 'taxes',                 'link' => 'master/taxes',               'icon' => 'ReceiptPercentIcon',       'published' => 1, 'parent_id' => 2,  'level' => 1, 'ordering' => 11, 'permission_name' => 'TAX.VIEW'],
            ['id' => 44, 'menutype' => 2, 'title' => 'Menu Management',       'alias' => 'menus',                 'link' => 'settings/menus',             'icon' => 'Bars3Icon',                'published' => 1, 'parent_id' => 2,  'level' => 1, 'ordering' => 3,  'permission_name' => 'MENU.VIEW'],
            ['id' => 51, 'menutype' => 2, 'title' => 'Terms & Conditions',    'alias' => 'termsconditions',       'link' => 'settings/termsconditions',   'icon' => 'DocumentTextIcon',         'published' => 1, 'parent_id' => 2,  'level' => 1, 'ordering' => 4,  'permission_name' => 'TERMS_CONDITION.VIEW'],

            // Sidebar: Tenant (parent_id = 7)
            ['id' => 15, 'menutype' => 2, 'title' => 'Entities', 'alias' => 'Entities', 'link' => 'tenant/entities', 'icon' => 'BuildingOfficeIcon',     'published' => 1, 'parent_id' => 7, 'level' => 1, 'ordering' => 1, 'permission_name' => 'ENTITY.VIEW'],
            ['id' => 49, 'menutype' => 2, 'title' => 'Plant',    'alias' => 'plants',   'link' => 'tenant/plants',   'icon' => 'BuildingStorefrontIcon', 'published' => 1, 'parent_id' => 7, 'level' => 1, 'ordering' => 2, 'permission_name' => 'PLANT.VIEW'],

            // Sidebar: Fleet (parent_id = 4)
            ['id' => 33, 'menutype' => 2, 'title' => 'Daily Tracker', 'alias' => 'tracker',      'link' => 'machine/tracker',   'icon' => 'ClockIcon', 'published' => 1, 'parent_id' => 4, 'level' => 1, 'ordering' => 1, 'permission_name' => 'TRACKER.VIEW'],
            ['id' => 43, 'menutype' => 2, 'title' => 'Machine Fleet', 'alias' => 'machines',     'link' => 'fleet/machines',    'icon' => 'TruckIcon', 'published' => 1, 'parent_id' => 4, 'level' => 1, 'ordering' => 1, 'permission_name' => 'MACHINE.VIEW'],
            ['id' => 61, 'menutype' => 2, 'title' => 'Machine Type',  'alias' => 'machinetypes', 'link' => 'fleet/machinetypes','icon' => 'CogIcon',   'published' => 1, 'parent_id' => 4, 'level' => 1, 'ordering' => 2, 'permission_name' => 'MACHINE_TYPE.VIEW'],

            // Sidebar: Finance (parent_id = 9)
            ['id' => 34, 'menutype' => 2, 'title' => 'Chart of Account',    'alias' => 'Accounts',           'link' => 'finance/accounts',   'icon' => 'BriefcaseIcon',      'published' => 1, 'parent_id' => 9, 'level' => 1, 'ordering' => 1, 'permission_name' => 'ACCOUNT.VIEW'],
            ['id' => 35, 'menutype' => 2, 'title' => 'Account Types',       'alias' => 'accounttypes',       'link' => 'finance/accounttypes','icon' => 'ListBulletIcon',     'published' => 1, 'parent_id' => 9, 'level' => 1, 'ordering' => 2, 'permission_name' => 'ACCOUNT_TYPE.VIEW'],
            ['id' => 36, 'menutype' => 2, 'title' => 'Ledger',              'alias' => 'ledgers',            'link' => 'finance/ledgers',    'icon' => 'BookOpenIcon',       'published' => 1, 'parent_id' => 9, 'level' => 1, 'ordering' => 3, 'permission_name' => 'LEDGER.VIEW'],
            ['id' => 57, 'menutype' => 2, 'title' => 'Invoice',             'alias' => 'invoices',           'link' => 'finance/invoices',   'icon' => 'DocumentTextIcon',   'published' => 1, 'parent_id' => 9, 'level' => 1, 'ordering' => 4, 'permission_name' => 'INVOICE.VIEW'],
            ['id' => 40, 'menutype' => 2, 'title' => 'Payment',             'alias' => 'payments',           'link' => 'finance/payments',   'icon' => 'DocumentChartBarIcon','published' => 1, 'parent_id' => 9, 'level' => 1, 'ordering' => 5, 'permission_name' => 'PAYMENT.VIEW'],
            ['id' => 55, 'menutype' => 2, 'title' => 'Expense',             'alias' => 'expenses',           'link' => 'finance/expenses',   'icon' => 'BanknotesIcon',      'published' => 1, 'parent_id' => 9, 'level' => 1, 'ordering' => 6, 'permission_name' => 'EXPENSE.VIEW'],
            ['id' => 38, 'menutype' => 2, 'title' => 'Journal Entry',       'alias' => 'journal-entries',    'link' => 'journal-entries',    'icon' => 'DocumentChartBarIcon','published' => 1, 'parent_id' => 9, 'level' => 1, 'ordering' => 5, 'permission_name' => 'JOURNAL_ENTRY.VIEW'],
            ['id' => 70, 'menutype' => 2, 'title' => 'Accounting Reports',  'alias' => 'accounting_reports', 'link' => 'finance/reports',    'icon' => 'ChartBarIcon',       'published' => 1, 'parent_id' => 9, 'level' => 1, 'ordering' => 8, 'permission_name' => 'LEDGER.VIEW'],
            ['id' => 71, 'menutype' => 2, 'title' => 'ERP Dashboard',       'alias' => 'erp_dashboard',      'link' => 'finance/dashboard',  'icon' => 'ChartPieIcon',       'published' => 1, 'parent_id' => 9, 'level' => 1, 'ordering' => 0, 'permission_name' => 'ACCOUNT.VIEW'],

            // Sidebar: Orders (parent_id = 3)
            ['id' => 53, 'menutype' => 2, 'title' => 'Quotation',       'alias' => 'quotations',    'link' => 'inventory/quotations',   'icon' => 'DocumentChartBarIcon',     'published' => 1, 'parent_id' => 5, 'level' => 1, 'ordering' => 1, 'permission_name' => 'QUOTATION.VIEW'],
            ['id' => 21, 'menutype' => 2, 'title' => 'Purchase Orders', 'alias' => 'purchaseorder', 'link' => 'inventory/purchaseorder','icon' => 'ClipboardDocumentCheckIcon','published' => 1, 'parent_id' => 5, 'level' => 1, 'ordering' => 2, 'permission_name' => 'PURCHASEORDER.VIEW'],
            ['id' => 52, 'menutype' => 2, 'title' => 'Work Order',      'alias' => 'workorders',    'link' => 'inventory/workorders',   'icon' => 'ClipboardDocumentCheckIcon','published' => 1, 'parent_id' => 5, 'level' => 1, 'ordering' => 3, 'permission_name' => 'WORK_ORDER.VIEW'],
            ['id' => 58, 'menutype' => 2, 'title' => 'Party Rate',      'alias' => 'partyrates',    'link' => 'inventory/partyrates',   'icon' => 'CurrencyRupeeIcon',         'published' => 1, 'parent_id' => 5, 'level' => 1, 'ordering' => 3, 'permission_name' => 'PARTY_RATE.VIEW'],
            ['id' => 59, 'menutype' => 2, 'title' => 'Batching',        'alias' => 'Batching',      'link' => 'batching/batches',      'icon' => 'ClipboardDocumentListIcon', 'published' => 1, 'parent_id' => 6, 'level' => 1, 'ordering' => 6, 'permission_name' => 'BATCH.VIEW'],

            // Sidebar: Inventory (parent_id = 5)
            ['id' => 46, 'menutype' => 2, 'title' => 'Product',        'alias' => 'products',        'link' => 'inventory/products',       'icon' => 'ArchiveBoxIcon',  'published' => 1, 'parent_id' => 5, 'level' => 1, 'ordering' => 1, 'permission_name' => 'PRODUCT.VIEW'],
            ['id' => 60, 'menutype' => 2, 'title' => 'Inward',         'alias' => 'inward',          'link' => 'inventory/inwards',        'icon' => 'CogIcon',         'published' => 1, 'parent_id' => 5, 'level' => 1, 'ordering' => 2, 'permission_name' => 'INWARDS.VIEW'],
            ['id' => 42, 'menutype' => 2, 'title' => 'Unit',           'alias' => 'productunits',    'link' => 'inventory/productunits',   'icon' => 'ScaleIcon',       'published' => 1, 'parent_id' => 5, 'level' => 1, 'ordering' => 3, 'permission_name' => 'PRODUCT_UNIT.VIEW'],
            ['id' => 47, 'menutype' => 2, 'title' => 'Mix Design',     'alias' => 'mixdesigns',      'link' => 'inventory/mixdesigns',     'icon' => 'BeakerIcon',      'published' => 1, 'parent_id' => 5, 'level' => 1, 'ordering' => 4, 'permission_name' => 'MIX_DESIGN.VIEW'],
            ['id' => 48, 'menutype' => 2, 'title' => 'Concrete Grade', 'alias' => 'concretegrades',  'link' => 'inventory/concretegrades', 'icon' => 'CONCRETE_GRADE.VIEW', 'published' => 1, 'parent_id' => 5, 'level' => 1, 'ordering' => 5, 'permission_name' => 'CONCRETE_GRADE.VIEW'],
            ['id' => 45, 'menutype' => 2, 'title' => 'Categories',     'alias' => 'productcategories','link' => 'inventory/productcategories','icon' => 'TagIcon',        'published' => 1, 'parent_id' => 5, 'level' => 1, 'ordering' => 2, 'permission_name' => 'PRODUCT_CATEGORY.VIEW'],

            // Sidebar: Settings (parent_id = 11)
            ['id' => 39, 'menutype' => 2, 'title' => 'Patrons',          'alias' => 'PatronsList',    'link' => 'settings/patrons',      'icon' => 'UserGroupIcon',    'published' => 1, 'parent_id' => 11, 'level' => 1, 'ordering' => 2, 'permission_name' => 'PATRON.VIEW'],
            ['id' => 50, 'menutype' => 2, 'title' => 'Site',             'alias' => 'sites',          'link' => 'settings/sites',        'icon' => 'MapPinIcon',       'published' => 1, 'parent_id' => 11, 'level' => 1, 'ordering' => 3, 'permission_name' => 'SITE.VIEW'],
            ['id' => 22, 'menutype' => 2, 'title' => 'Print Templates',  'alias' => 'templates',      'link' => 'settings/templates',    'icon' => 'SwatchIcon',       'published' => 1, 'parent_id' => 11, 'level' => 1, 'ordering' => 5, 'permission_name' => 'MENU.VIEW'],
            ['id' => 100,'menutype' => 2, 'title' => 'Default Accounts', 'alias' => 'defaultaccounts','link' => 'settings/default-accounts','icon' => 'PaintBrushIcon', 'published' => 1, 'parent_id' => 11, 'level' => 1, 'ordering' => 6, 'permission_name' => 'MENU.VIEW'],
        ];

        foreach ($menus as $menu) {
            Menu::updateOrCreate(
                ['id' => $menu['id']],
                $menu
            );
        }
    }
}
