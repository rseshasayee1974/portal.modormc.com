<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $menus = [
            // AppLayout Top Navigation (menutype 1)
            ['id' => 1, 'menutype' => 1, 'title' => 'Dashboard', 'alias' => 'dashboard', 'link' => 'dashboard', 'published' => true, 'parent_id' => 0, 'level' => 0, 'ordering' => 1, 'icon' => 'HomeIcon', 'permission_name' => 'DASHBOARD.VIEW'],
            ['id' => 2, 'menutype' => 1, 'title' => 'Master', 'alias' => 'master', 'link' => '#', 'published' => true, 'parent_id' => 0, 'level' => 0, 'ordering' => 2, 'icon' => 'Square3Stack3DIcon', 'permission_name' => 'ADDRESS_TYPE.VIEW'],
            ['id' => 3, 'menutype' => 1, 'title' => 'Orders', 'alias' => 'orders', 'link' => '#', 'published' => true, 'parent_id' => 0, 'level' => 0, 'ordering' => 3, 'icon' => 'CreditCardIcon', 'permission_name' => 'QUOTATION.VIEW'],
            ['id' => 4, 'menutype' => 1, 'title' => 'Fleet', 'alias' => 'fleet', 'link' => '#', 'published' => true, 'parent_id' => 0, 'level' => 0, 'ordering' => 4, 'icon' => 'Cog6ToothIcon', 'permission_name' => 'MACHINE.VIEW'],
            ['id' => 5, 'menutype' => 1, 'title' => 'Inventory', 'alias' => 'inventory', 'link' => '#', 'published' => true, 'parent_id' => 0, 'level' => 0, 'ordering' => 5, 'icon' => 'ArchiveBoxIcon', 'permission_name' => 'PRODUCT.VIEW'],
            ['id' => 6, 'menutype' => 1, 'title' => 'Members', 'alias' => 'membership', 'link' => '#', 'published' => true, 'parent_id' => 0, 'level' => 0, 'ordering' => 6, 'icon' => 'ClipboardDocumentListIcon', 'permission_name' => 'PATRON.VIEW'],
            ['id' => 7, 'menutype' => 1, 'title' => 'Tenant', 'alias' => 'tenant', 'link' => '#', 'published' => true, 'parent_id' => 0, 'level' => 0, 'ordering' => 7, 'icon' => 'BuildingOfficeIcon', 'permission_name' => 'ENTITY.VIEW'],
            ['id' => 9, 'menutype' => 1, 'title' => 'Finance', 'alias' => 'finance', 'link' => '#', 'published' => true, 'parent_id' => 0, 'level' => 0, 'ordering' => 9, 'icon' => 'BriefcaseIcon', 'permission_name' => 'ACCOUNT.VIEW'],
            ['id' => 40, 'menutype' => 1, 'title' => 'Settings', 'alias' => 'settings', 'link' => '#', 'published' => true, 'parent_id' => 0, 'level' => 0, 'ordering' => 11, 'icon' => 'CogIcon', 'permission_name' => 'MENU.VIEW'],

            // Sidebar Navigation for 'Membership' (parent_id = 6)
            ['id' => 11, 'menutype' => 2, 'title' => 'User Management', 'alias' => 'Users', 'link' => 'membership/users', 'published' => true, 'parent_id' => 6, 'level' => 1, 'ordering' => 1, 'icon' => 'UsersIcon', 'permission_name' => 'USER.VIEW'],
            ['id' => 39, 'menutype' => 2, 'title' => 'Patrons', 'alias' => 'PatronsList', 'link' => 'membership/patrons', 'published' => true, 'parent_id' => 6, 'level' => 1, 'ordering' => 2, 'icon' => 'UserGroupIcon', 'permission_name' => 'PATRON.VIEW'],
            ['id' => 63, 'menutype' => 2, 'title' => 'Personnel', 'alias' => 'personnel', 'link' => 'membership/personnel', 'published' => true, 'parent_id' => 6, 'level' => 1, 'ordering' => 3, 'icon' => 'IdentificationIcon', 'permission_name' => 'PERSONNEL.VIEW'],

            // Sidebar Navigation for 'Tenant' (parent_id = 7)
            ['id' => 15, 'menutype' => 2, 'title' => 'Entities', 'alias' => 'Entities', 'link' => 'tenant/entities', 'published' => true, 'parent_id' => 7, 'level' => 1, 'ordering' => 1, 'icon' => 'BuildingOfficeIcon', 'permission_name' => 'ENTITY.VIEW'],
            ['id' => 49, 'menutype' => 2, 'title' => 'Plants', 'alias' => 'plants', 'link' => 'tenant/plants', 'published' => true, 'parent_id' => 7, 'level' => 1, 'ordering' => 2, 'icon' => 'BuildingStorefrontIcon', 'permission_name' => 'PLANT.VIEW'],
            ['id' => 50, 'menutype' => 2, 'title' => 'Sites', 'alias' => 'sites', 'link' => 'tenant/sites', 'published' => true, 'parent_id' => 7, 'level' => 1, 'ordering' => 3, 'icon' => 'MapPinIcon', 'permission_name' => 'SITE.VIEW'],

            // Sidebar Navigation for 'Master' (parent_id = 2)
            ['id' => 19, 'menutype' => 2, 'title' => 'Address Types', 'alias' => 'AddressTypes', 'link' => 'master/addresstypes', 'published' => true, 'parent_id' => 2, 'level' => 1, 'ordering' => 1, 'icon' => 'MapIcon', 'permission_name' => 'ADDRESS_TYPE.VIEW'],
            ['id' => 20, 'menutype' => 2, 'title' => 'Bank Account Types', 'alias' => 'BankAccountTypes', 'link' => 'master/bankaccounttypes', 'published' => true, 'parent_id' => 2, 'level' => 1, 'ordering' => 2, 'icon' => 'CreditCardIcon', 'permission_name' => 'BANK_ACCOUNT_TYPE.VIEW'],
            ['id' => 21, 'menutype' => 2, 'title' => 'Contact Types', 'alias' => 'ContactTypes', 'link' => 'master/contacttypes', 'published' => true, 'parent_id' => 2, 'level' => 1, 'ordering' => 3, 'icon' => 'PhoneIcon', 'permission_name' => 'CONTACT_TYPE.VIEW'],
            ['id' => 22, 'menutype' => 2, 'title' => 'Countries', 'alias' => 'Countries', 'link' => 'master/countries', 'published' => true, 'parent_id' => 2, 'level' => 1, 'ordering' => 4, 'icon' => 'GlobeAltIcon', 'permission_name' => 'COUNTRY.VIEW'],
            ['id' => 23, 'menutype' => 2, 'title' => 'Currencies', 'alias' => 'Currencies', 'link' => 'master/currencies', 'published' => true, 'parent_id' => 2, 'level' => 1, 'ordering' => 5, 'icon' => 'CurrencyDollarIcon', 'permission_name' => 'CURRENCY.VIEW'],
            ['id' => 24, 'menutype' => 2, 'title' => 'Entity Types', 'alias' => 'EntityTypes', 'link' => 'master/entitytypes', 'published' => true, 'parent_id' => 2, 'level' => 1, 'ordering' => 6, 'icon' => 'BuildingOfficeIcon', 'permission_name' => 'ENTITY_TYPE.VIEW'],
            ['id' => 27, 'menutype' => 2, 'title' => 'Invoice Statuses', 'alias' => 'InvoiceStatuses', 'link' => 'master/invoicestatuses', 'published' => true, 'parent_id' => 2, 'level' => 1, 'ordering' => 7, 'icon' => 'ReceiptPercentIcon', 'permission_name' => 'INVOICE_STATUS.VIEW'],
            ['id' => 29, 'menutype' => 2, 'title' => 'Plans', 'alias' => 'Plans', 'link' => 'master/plans', 'published' => true, 'parent_id' => 2, 'level' => 1, 'ordering' => 8, 'icon' => 'ClipboardDocumentListIcon', 'permission_name' => 'PLAN.VIEW'],
            ['id' => 30, 'menutype' => 2, 'title' => 'State Codes', 'alias' => 'StateCodes', 'link' => 'master/statecodes', 'published' => true, 'parent_id' => 2, 'level' => 1, 'ordering' => 9, 'icon' => 'MapPinIcon', 'permission_name' => 'STATE_CODE.VIEW'],
            ['id' => 31, 'menutype' => 2, 'title' => 'Subscription Statuses', 'alias' => 'SubscriptionStatuses', 'link' => 'master/subscriptionstatuses', 'published' => true, 'parent_id' => 2, 'level' => 1, 'ordering' => 10, 'icon' => 'ArrowPathIcon', 'permission_name' => 'SUBSCRIPTION_STATUS.VIEW'],
            ['id' => 41, 'menutype' => 2, 'title' => 'Tax Configuration', 'alias' => 'taxes', 'link' => 'master/taxes', 'published' => true, 'parent_id' => 2, 'level' => 1, 'ordering' => 11, 'icon' => 'ReceiptPercentIcon', 'permission_name' => 'TAX.VIEW'],

            // Sidebar Navigation for 'Finance' (parent_id = 9)
            ['id' => 34, 'menutype' => 2, 'title' => 'Chart of Accounts', 'alias' => 'Accounts', 'link' => 'finance/accounts', 'published' => true, 'parent_id' => 9, 'level' => 1, 'ordering' => 1, 'icon' => 'BriefcaseIcon', 'permission_name' => 'ACCOUNT.VIEW'],
            ['id' => 35, 'menutype' => 2, 'title' => 'Account Types', 'alias' => 'accounttypes', 'link' => 'finance/accounttypes', 'published' => true, 'parent_id' => 9, 'level' => 1, 'ordering' => 2, 'icon' => 'ListBulletIcon', 'permission_name' => 'ACCOUNT_TYPE.VIEW'],
            ['id' => 36, 'menutype' => 2, 'title' => 'Ledgers', 'alias' => 'ledgers', 'link' => 'finance/ledgers', 'published' => true, 'parent_id' => 9, 'level' => 1, 'ordering' => 3, 'icon' => 'BookOpenIcon', 'permission_name' => 'LEDGER.VIEW'],
            ['id' => 57, 'menutype' => 2, 'title' => 'Invoices', 'alias' => 'invoices', 'link' => 'finance/invoices', 'published' => true, 'parent_id' => 9, 'level' => 1, 'ordering' => 4, 'icon' => 'DocumentTextIcon', 'permission_name' => 'INVOICE.VIEW'],
            ['id' => 62, 'menutype' => 2, 'title' => 'Payments', 'alias' => 'payments', 'link' => 'finance/payments', 'published' => true, 'parent_id' => 9, 'level' => 1, 'ordering' => 5, 'icon' => 'DocumentChartBarIcon', 'permission_name' => 'PAYMENT.VIEW'],
            ['id' => 55, 'menutype' => 2, 'title' => 'Expenses', 'alias' => 'expenses', 'link' => 'finance/expenses', 'published' => true, 'parent_id' => 9, 'level' => 1, 'ordering' => 6, 'icon' => 'BanknotesIcon', 'permission_name' => 'EXPENSE.VIEW'],
            ['id' => 56, 'menutype' => 2, 'title' => 'Petty Cash', 'alias' => 'pettycash', 'link' => 'finance/pettycash', 'published' => true, 'parent_id' => 9, 'level' => 1, 'ordering' => 7, 'icon' => 'CurrencyRupeeIcon', 'permission_name' => 'PETTY_CASH.VIEW'],

            // Sidebar Navigation for 'Orders' (parent_id = 3)
            ['id' => 53, 'menutype' => 2, 'title' => 'Quotations', 'alias' => 'quotations', 'link' => 'orders/quotations', 'published' => true, 'parent_id' => 3, 'level' => 1, 'ordering' => 1, 'icon' => 'DocumentChartBarIcon', 'permission_name' => 'QUOTATION.VIEW'],
            ['id' => 64, 'menutype' => 2, 'title' => 'Purchase Orders', 'alias' => 'purchaseorder', 'link' => 'orders/purchaseorder', 'published' => true, 'parent_id' => 3, 'level' => 1, 'ordering' => 2, 'icon' => 'ClipboardDocumentCheckIcon', 'permission_name' => 'PURCHASEORDER.VIEW'],
            ['id' => 52, 'menutype' => 2, 'title' => 'Work Orders', 'alias' => 'workorders', 'link' => 'orders/workorders', 'published' => true, 'parent_id' => 3, 'level' => 1, 'ordering' => 3, 'icon' => 'ClipboardDocumentCheckIcon', 'permission_name' => 'WORK_ORDER.VIEW'],

            // Sidebar Navigation for 'Fleet' (parent_id = 4)
            ['id' => 43, 'menutype' => 2, 'title' => 'Machine Fleet', 'alias' => 'machines', 'link' => 'fleet/machines', 'published' => true, 'parent_id' => 4, 'level' => 1, 'ordering' => 1, 'icon' => 'TruckIcon', 'permission_name' => 'MACHINE.VIEW'],
            ['id' => 61, 'menutype' => 2, 'title' => 'Machine Types', 'alias' => 'machinetypes', 'link' => 'fleet/machinetypes', 'published' => true, 'parent_id' => 4, 'level' => 1, 'ordering' => 2, 'icon' => 'CogIcon', 'permission_name' => 'MACHINE_TYPE.VIEW'],
            ['id' => 59, 'menutype' => 2, 'title' => 'Trip Management', 'alias' => 'trips', 'link' => 'ops/trips', 'published' => true, 'parent_id' => 4, 'level' => 1, 'ordering' => 3, 'icon' => 'TruckIcon', 'permission_name' => 'TRIP.VIEW'],
            ['id' => 60, 'menutype' => 2, 'title' => 'Trip Dashboard', 'alias' => 'tripsdashboard', 'link' => 'ops/trips/dashboard', 'published' => true, 'parent_id' => 4, 'level' => 1, 'ordering' => 4, 'icon' => 'ChartBarIcon', 'permission_name' => 'TRIP.VIEW'],

            // Sidebar Navigation for 'Inventory' (parent_id = 5)
            ['id' => 46, 'menutype' => 2, 'title' => 'Products', 'alias' => 'products', 'link' => 'inventory/products', 'published' => true, 'parent_id' => 5, 'level' => 1, 'ordering' => 1, 'icon' => 'ArchiveBoxIcon', 'permission_name' => 'PRODUCT.VIEW'],
            ['id' => 45, 'menutype' => 2, 'title' => 'Categories', 'alias' => 'productcategories', 'link' => 'inventory/productcategories', 'published' => true, 'parent_id' => 5, 'level' => 1, 'ordering' => 2, 'icon' => 'TagIcon', 'permission_name' => 'PRODUCT_CATEGORY.VIEW'],
            ['id' => 42, 'menutype' => 2, 'title' => 'Units', 'alias' => 'productunits', 'link' => 'inventory/productunits', 'published' => true, 'parent_id' => 5, 'level' => 1, 'ordering' => 3, 'icon' => 'ScaleIcon', 'permission_name' => 'PRODUCT_UNIT.VIEW'],
            ['id' => 47, 'menutype' => 2, 'title' => 'Mix Designs', 'alias' => 'mixdesigns', 'link' => 'inventory/mixdesigns', 'published' => true, 'parent_id' => 5, 'level' => 1, 'ordering' => 4, 'icon' => 'BeakerIcon', 'permission_name' => 'MIX_DESIGN.VIEW'],
            ['id' => 48, 'menutype' => 2, 'title' => 'Concrete Grades', 'alias' => 'concretegrades', 'link' => 'inventory/concretegrades', 'published' => true, 'parent_id' => 5, 'level' => 1, 'ordering' => 5, 'icon' => 'CONCRETE_GRADE.VIEW'],

            // Sidebar Navigation for 'Settings' (parent_id = 40)
            ['id' => 12, 'menutype' => 2, 'title' => 'Roles', 'alias' => 'Roles', 'link' => 'settings/roles', 'published' => true, 'parent_id' => 40, 'level' => 1, 'ordering' => 1, 'icon' => 'ShieldCheckIcon', 'permission_name' => 'ROLE.VIEW'],
            ['id' => 13, 'menutype' => 2, 'title' => 'Permissions', 'alias' => 'Permissions', 'link' => 'settings/permissions', 'published' => true, 'parent_id' => 40, 'level' => 1, 'ordering' => 2, 'icon' => 'KeyIcon', 'permission_name' => 'PERMISSION.VIEW'],
            ['id' => 44, 'menutype' => 2, 'title' => 'Menu Management', 'alias' => 'menus', 'link' => 'settings/menus', 'published' => true, 'parent_id' => 40, 'level' => 1, 'ordering' => 3, 'icon' => 'Bars3Icon', 'permission_name' => 'MENU.VIEW'],
            ['id' => 51, 'menutype' => 2, 'title' => 'Terms & Conditions', 'alias' => 'termsconditions', 'link' => 'settings/termsconditions', 'published' => true, 'parent_id' => 40, 'level' => 1, 'ordering' => 4, 'icon' => 'DocumentTextIcon', 'permission_name' => 'TERMS_CONDITION.VIEW'],
            ['id' => 100, 'menutype' => 2, 'title' => 'Template Engine', 'alias' => 'templates', 'link' => 'settings/templates', 'published' => true, 'parent_id' => 40, 'level' => 1, 'ordering' => 5, 'icon' => 'PaintBrushIcon', 'permission_name' => 'MENU.VIEW'],
        ];

        foreach ($menus as $menu) {
            \App\Models\Menu::updateOrCreate(
                ['id' => $menu['id']],
                $menu
            );
        }
    }
}
