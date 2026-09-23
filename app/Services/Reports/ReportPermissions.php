<?php

namespace App\Services\Reports;

use App\Models\EntityUser;
use App\Models\Role;
use App\Models\User;

class ReportPermissions
{
    public const ACTIONS = ['VIEW', 'EXPORT', 'SHARE', 'SCHEDULE'];

    public const REPORTS = [
        'sales_register' => 'Sales Register',
        'detailed_sales_register' => 'Detailed Sales Register',
        'purchase_register' => 'Purchase Register',
        'ledger' => 'General Ledger',
        'patron' => 'Patron Statement',
        'payment' => 'Payment Log',
        'receipt' => 'Receipt Log',
        'customer_outstanding' => 'Customer Outstanding',
        'deleted_report' => 'Deleted Report',
        'bulk_documents' => 'Bulk Invoice / Bill Export',
        'inventory_stock' => 'Stock Status List',
        'inventory_inward' => 'Purchase Inward Receipts',
        'purchase' => 'Purchase and Bills Summary',
        'silo_stock_valuation' => 'Silo Stock Valuation',
        'overall' => 'Overall Daily Report',
        'sales' => 'Sales and Dispatches',
        'product_consolidated' => 'Product Consolidated Report',
        'customer_consolidated' => 'Customer Report',
        'truck_consolidated' => 'Truck Consolidated Report',
        'site_consolidated' => 'Unload Site Consolidated Report',
        'payment_mode_consolidated' => 'Payment Mode Consolidated Report',
        'sales_executive' => 'Sales Executive Report',
        'driver' => 'Driver Trip Report',
        'cancelled_dispatch' => 'Cancelled Dispatch Report',
        'production_batch' => 'Batch Production Sheet',
        'machines_list' => 'Machine Fleet Inventory',
        'machine_tracker' => 'Machine Tracker Log Sheet',
        'machine_summary' => 'Machine Summary Report',
        'vehicle_pl' => 'Vehicle Profit and Loss',
        'payroll_personnel' => 'Personnel Directory',
        'gstr1' => 'GSTR-1 Report',
        'gstr3b' => 'GSTR-3B Summary',
        'tds_certificate' => 'TDS Certificate',
        'esi_pf_challan' => 'ESI/PF Challan',
    ];

    public static function reportId(string $type, array $params = []): string
    {
        $type = strtolower(trim($type));
        if ($type === 'deleted') $type = 'deleted_report';
        // Register services default to detail when their callers omit the layout.
        if ($type === 'sales_register' && strtolower($params['register_view'] ?? 'detail') === 'detail') {
            $type = 'detailed_sales_register';
        }
        return $type;
    }

    public static function name(string $id, string $action): string
    {
        return 'REPORT_'.strtoupper($id).'.'.strtoupper($action);
    }

    /** Resolve the selected entity/plant role; a global role must not override it. */
    private function grants(?User $user, ?int $entityId, ?int $plantId): array
    {
        if (!$user) return [];
        if ($user->isSystemAdmin()) return ['*'];
        $role = null;
        if ($entityId) {
            $assignment = EntityUser::where('user_id', $user->id)->where('entity_id', $entityId)
                ->where(fn ($q) => $q->whereNull('plant_id')->orWhere('plant_id', $plantId))
                ->orderByRaw('CASE WHEN plant_id IS NULL THEN 1 ELSE 0 END')->first();
            if ($assignment?->role_id) $role = Role::with('permissions')->find($assignment->role_id);
        }
        if ($role && (in_array(strtoupper($role->code ?? ''), ['SAAS_OWNER', 'PLATFORM_ADMIN', 'SUPER_ADMIN', 'ADMINISTRATOR'])
            || in_array($role->name, ['Saas Owner', 'Platform Admin', 'Super Admin', 'Super Administrator', 'Administrator']))) {
            return ['*'];
        }
        $permissions = $role ? $role->permissions->merge($user->getDirectPermissions()) : $user->getAllPermissions();
        return $permissions->pluck('name')->map(fn ($name) => strtoupper($name))->unique()->values()->all();
    }

    public function matrix(?User $user = null, ?int $entityId = null, ?int $plantId = null): array
    {
        $user ??= auth()->user();
        $grants = $this->grants($user, $entityId ?? session('active_entity_id'), $plantId ?? session('active_plant_id'));
        $all = in_array('*', $grants, true);
        $matrix = [];
        foreach (self::REPORTS as $id => $label) {
            $view = $all || in_array(self::name($id, 'VIEW'), $grants, true);
            foreach (self::ACTIONS as $action) {
                $matrix[$id][strtolower($action)] = $view && ($all || in_array(self::name($id, $action), $grants, true));
            }
        }
        return $matrix;
    }

    public function allows(string $type, string $action = 'view', array $params = [], ?User $user = null, ?int $entityId = null, ?int $plantId = null): bool
    {
        return $this->matrix($user, $entityId, $plantId)[self::reportId($type, $params)][strtolower($action)] ?? false;
    }

    public function authorize(string $type, string $action = 'view', array $params = []): void
    {
        abort_unless(auth()->check(), 401);
        $id = self::reportId($type, $params);
        abort_unless(isset(self::REPORTS[$id]), 422, 'Unknown report type.');
        abort_unless($this->allows($type, $action, $params), 403, 'You do not have permission to '.$action.' this report.');
    }

    public function rememberExport(string $key, string $type, array $params): void
    {
        \Illuminate\Support\Facades\Cache::put($key.':access', [
            'user_id' => auth()->id(), 'plant_id' => session('active_plant_id'),
            'type' => $type, 'params' => \Illuminate\Support\Arr::only($params, ['register_view']),
        ], now()->addHours(2));
    }
}
