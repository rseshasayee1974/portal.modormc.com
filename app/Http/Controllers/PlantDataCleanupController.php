<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\PlantContextService;
use App\Models\Plant;
use Inertia\Inertia;

// Models
use App\Models\Batch;
use App\Models\SalesOrder;
use App\Models\Quotation;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\ConcreteQualityTest;
use App\Models\MixDesign;
use App\Models\Machine;
use App\Models\MachineTracker;
use App\Models\MaintenanceLine;
use App\Models\PettyCash;
use App\Models\Payslip;
use App\Models\TruckEmptyWeight;
use App\Models\Product;
use App\Models\Patron;
use App\Models\Site;

class PlantDataCleanupController extends Controller
{
    /**
     * Whitelist mapping of allowed modules and their corresponding Model classes.
     */
    protected array $moduleMap = [
        'batches'             => ['model' => Batch::class, 'name' => 'Batches', 'key_field' => 'batch_no'],
        'sales_orders'        => ['model' => SalesOrder::class, 'name' => 'Sales Orders', 'key_field' => 'order_no'],
        'quotations'          => ['model' => Quotation::class, 'name' => 'Quotations', 'key_field' => 'quotation_no'],
        'invoices'            => ['model' => Invoice::class, 'name' => 'Invoices', 'key_field' => 'invoice_no'],
        'purchase_orders'     => ['model' => PurchaseOrder::class, 'name' => 'Purchase Orders', 'key_field' => 'po_no'],
        'quality_tests'       => ['model' => ConcreteQualityTest::class, 'name' => 'Quality Tests', 'key_field' => 'id'],
        'mix_designs'         => ['model' => MixDesign::class, 'name' => 'Mix Designs', 'key_field' => 'name'],
        'machines'            => ['model' => Machine::class, 'name' => 'Machines', 'key_field' => 'name'],
        'machine_trackers'    => ['model' => MachineTracker::class, 'name' => 'Machine Trackers', 'key_field' => 'name'],
        'maintenance_lines'   => ['model' => MaintenanceLine::class, 'name' => 'Maintenance Lines', 'key_field' => 'id'],
        'petty_cash'          => ['model' => PettyCash::class, 'name' => 'Petty Cash', 'key_field' => 'voucher_no'],
        'payslips'            => ['model' => Payslip::class, 'name' => 'Payslips', 'key_field' => 'payslip_no'],
        'truck_empty_weights' => ['model' => TruckEmptyWeight::class, 'name' => 'Truck Empty Weights', 'key_field' => 'id'],
        'products'            => ['model' => Product::class, 'name' => 'Products', 'key_field' => 'name'],
        'patrons'             => ['model' => Patron::class, 'name' => 'Patrons / Customers', 'key_field' => 'legal_name'],
        'sites'               => ['model' => Site::class, 'name' => 'Sites', 'key_field' => 'name'],
    ];

    /**
     * Resolve active plant ID strictly.
     */
    protected function getActivePlantId(): int
    {
        $plantId = app(PlantContextService::class)->plantId() ?? session('active_plant_id');

        if (!$plantId) {
            abort(403, 'No active plant selected in session.');
        }

        return (int) $plantId;
    }

    /**
     * Index page: Displays module summary stats and data table for active plant.
     */
    public function index(Request $request)
    {
        $activePlantId = $this->getActivePlantId();
        $plant = Plant::with('entity')->find($activePlantId);

        $stats = [];
        foreach ($this->moduleMap as $key => $config) {
            $modelClass = $config['model'];
            $hasSoftDeletes = in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($modelClass));

            $baseQuery = $modelClass::withoutGlobalScopes()->where('plant_id', $activePlantId);

            $stats[$key] = [
                'key'            => $key,
                'name'           => $config['name'],
                'active_count'   => (clone $baseQuery)->whereNull('deleted_at')->count(),
                'trashed_count'  => $hasSoftDeletes ? (clone $baseQuery)->whereNotNull('deleted_at')->count() : 0,
                'supports_trash' => $hasSoftDeletes,
            ];
        }

        // Active module selection
        $selectedModule = $request->query('module', 'batches');
        if (!array_key_exists($selectedModule, $this->moduleMap)) {
            $selectedModule = 'batches';
        }

        $viewTrashed = $request->boolean('trashed', false);
        $search = $request->query('search', '');

        $records = $this->fetchModuleRecords($selectedModule, $activePlantId, $viewTrashed, $search);

        return Inertia::render('Admin/PlantDataCleanup/Index', [
            'plant'          => $plant,
            'activePlantId'  => $activePlantId,
            'modules'        => $stats,
            'selectedModule' => $selectedModule,
            'viewTrashed'    => $viewTrashed,
            'records'        => $records,
            'filters'        => [
                'module'  => $selectedModule,
                'trashed' => $viewTrashed,
                'search'  => $search,
            ],
        ]);
    }

    /**
     * Fetch paginated records for a module filtered by active plant_id.
     */
    protected function fetchModuleRecords(string $moduleKey, int $plantId, bool $trashed, string $search = '')
    {
        $config = $this->moduleMap[$moduleKey];
        $modelClass = $config['model'];
        $keyField = $config['key_field'];

        $query = $modelClass::withoutGlobalScopes()->where('plant_id', $plantId);

        if ($trashed) {
            $query->onlyTrashed();
        } else {
            $query->whereNull('deleted_at');
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($keyField, $search) {
                $q->where('id', 'like', "%{$search}%");
                if ($keyField !== 'id') {
                    $q->orWhere($keyField, 'like', "%{$search}%");
                }
            });
        }

        return $query->latest('id')->paginate(15)->through(function ($item) use ($keyField) {
            return [
                'id'          => $item->id,
                'reference'   => $keyField !== 'id' && isset($item->{$keyField}) ? (string) $item->{$keyField} : "ID #{$item->id}",
                'status'      => $item->status ?? $item->operational_status ?? ($item->is_active ?? 'N/A'),
                'created_at'  => $item->created_at ? $item->created_at->format('Y-m-d H:i') : 'N/A',
                'deleted_at'  => $item->deleted_at ? $item->deleted_at->format('Y-m-d H:i') : null,
            ];
        });
    }

    /**
     * Bulk Delete (Soft Delete or Force Delete) strictly scoped to active_plant_id.
     */
    public function bulkDelete(Request $request)
    {
        $activePlantId = $this->getActivePlantId();

        $validated = $request->validate([
            'module'       => 'required|string|in:' . implode(',', array_keys($this->moduleMap)),
            'ids'          => 'nullable|array',
            'ids.*'        => 'integer',
            'delete_all'   => 'nullable|boolean',
            'force_delete' => 'nullable|boolean',
        ]);

        $moduleKey   = $validated['module'];
        $modelClass  = $this->moduleMap[$moduleKey]['model'];
        $moduleName  = $this->moduleMap[$moduleKey]['name'];
        $isForce     = $validated['force_delete'] ?? false;
        $deleteAll   = $validated['delete_all'] ?? false;
        $ids         = $validated['ids'] ?? [];

        if (!$deleteAll && empty($ids)) {
            return back()->with('error', 'Please select records to delete or choose Delete All.');
        }

        DB::beginTransaction();
        try {
            // STRICT QUERY: Always force where('plant_id', $activePlantId)
            $query = $modelClass::withoutGlobalScopes()
                ->where('plant_id', $activePlantId);

            if (!$deleteAll) {
                $query->whereIn('id', $ids);
            }

            if ($isForce) {
                $deletedCount = $query->forceDelete();
                $actionText = 'permanently deleted';
            } else {
                $deletedCount = $query->delete();
                $actionText = 'moved to trash';
            }

            DB::commit();

            Log::info("Bulk delete: {$deletedCount} records {$actionText} in {$moduleName} (plant_id: {$activePlantId}) by User ID: " . auth()->id());

            return back()->with('success', "Successfully {$actionText} {$deletedCount} records in {$moduleName}.");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Bulk delete failed for {$moduleName} (plant_id: {$activePlantId}): " . $e->getMessage());

            return back()->with('error', 'Delete operation failed: ' . $e->getMessage());
        }
    }

    /**
     * Bulk Restore strictly scoped to active_plant_id.
     */
    public function bulkRestore(Request $request)
    {
        $activePlantId = $this->getActivePlantId();

        $validated = $request->validate([
            'module'      => 'required|string|in:' . implode(',', array_keys($this->moduleMap)),
            'ids'         => 'nullable|array',
            'ids.*'       => 'integer',
            'restore_all' => 'nullable|boolean',
        ]);

        $moduleKey   = $validated['module'];
        $modelClass  = $this->moduleMap[$moduleKey]['model'];
        $moduleName  = $this->moduleMap[$moduleKey]['name'];
        $restoreAll  = $validated['restore_all'] ?? false;
        $ids         = $validated['ids'] ?? [];

        $hasSoftDeletes = in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($modelClass));
        if (!$hasSoftDeletes) {
            return back()->with('error', "{$moduleName} does not support trash restore.");
        }

        DB::beginTransaction();
        try {
            // STRICT QUERY: Always force onlyTrashed and where('plant_id', $activePlantId)
            $query = $modelClass::onlyTrashed()
                ->withoutGlobalScopes()
                ->where('plant_id', $activePlantId);

            if (!$restoreAll) {
                $query->whereIn('id', $ids);
            }

            $restoredCount = $query->restore();

            DB::commit();

            Log::info("Bulk restore: {$restoredCount} records restored in {$moduleName} (plant_id: {$activePlantId}) by User ID: " . auth()->id());

            return back()->with('success', "Successfully restored {$restoredCount} records in {$moduleName}.");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Bulk restore failed for {$moduleName} (plant_id: {$activePlantId}): " . $e->getMessage());

            return back()->with('error', 'Restore failed: ' . $e->getMessage());
        }
    }
}
