<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Requests\StoreSalesOrderRequest;
use App\Http\Requests\UpdateSalesOrderRequest;
use App\Models\SalesOrder;
use App\Services\SalesOrders\SalesOrderIndexDataFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class SalesOrderController extends Controller
{
    use AuthorizesModule;

    protected string $module = 'sales_orders';

    public function __construct(private readonly SalesOrderIndexDataFactory $indexDataFactory)
    {
    }

    public function index()
    {
        $this->authorizeModule('menu');
        $activePlantId = session('active_plant_id');

        return Inertia::render(
            'SalesOrders/Index',
            
            $this->indexDataFactory->build($activePlantId !== null ? (int) $activePlantId : null)
        );
    }

    public function store(StoreSalesOrderRequest $request)
    {
        $this->authorizeModule('create');
        $payload = $request->validated();

        if (!empty($payload['customer_po_id'])) {
            $po = \App\Models\CustomerPO::find($payload['customer_po_id']);
            if ($po) {
                $payload['sales_executive_id'] = $payload['sales_executive_id'] ?? $po->sales_executive_id;
                $payload['customer_id'] = $payload['customer_id'] ?? $po->patron_id;
                $payload['site_id'] = $payload['site_id'] ?? $po->site_id;
            }
        }

        if (empty($payload['order_no'])) {
            $details = SalesOrder::generateOrderNo(session('active_plant_id'), $payload['prefix'] ?? 'SO');
            $payload['prefix'] = $details['prefix'];
            $payload['order_no'] = $details['next_number'];
        }

        $tableColumns = Schema::getColumnListing('mm_sales_orders');
        $hasPlantIdColumn = in_array('plant_id', $tableColumns, true);
        $hasTotalQtyColumn = in_array('total_qty', $tableColumns, true);
        $hasLegacyQtyColumn = in_array('quantity', $tableColumns, true);

        if ($hasPlantIdColumn && empty($payload['plant_id'])) {
            $payload['plant_id'] = session('active_plant_id');
        }

        if ($hasLegacyQtyColumn && !$hasTotalQtyColumn && isset($payload['total_qty'])) {
            $payload['quantity'] = $payload['total_qty'];
        }

        $payload = array_intersect_key($payload, array_flip($tableColumns));

        DB::transaction(function () use ($payload) {
            SalesOrder::create($payload);
        });

        return redirect()->back()->with('success', 'Sales Order created successfully.');
    }

    public function show(SalesOrder $salesorder)
    {
        $this->authorizeModule('menu');
        $this->ensurePlantScope($salesorder);
        
        $salesorder->load([
            'customer', 
            'site', 
            'mixDesign.items.product', 
            'mixDesign.items.uom', 
            'mixDesign.concrete_grade',
            'customerPO.patron',
            'customerPO',
            'customerPO.site',
            'customerPO.quotation.items.mixDesign'
        ]);

        if ($salesorder->customer_po_id && $salesorder->customerPO) {
            $po = $salesorder->customerPO;
            
            $salesorder->customer_id = $salesorder->customer_id ?? $po->patron_id;
            $salesorder->site_id = $salesorder->site_id ?? $po->site_id;
            $salesorder->concrete_pump = $salesorder->concrete_pump ?? $po->concrete_pump;
            $salesorder->sales_executive_id = $salesorder->sales_executive_id ?? $po->sales_executive_id;
            
            if ($po->quotation && $po->quotation->items && $po->quotation->items->isNotEmpty()) {
                $firstItem = $po->quotation->items->first();
                $salesorder->mix_design_id = $salesorder->mix_design_id ?? $firstItem->mix_design_id;
                $salesorder->total_qty = $salesorder->total_qty ?? $firstItem->quantity;
            }
        }

        return response()->json($salesorder);
    }

    public function update(UpdateSalesOrderRequest $request, SalesOrder $salesorder)
    {
        $this->authorizeModule('edit');
        $this->ensurePlantScope($salesorder);
        
        $user = auth()->user();
        $isAdmin = $user && method_exists($user, 'hasRole') && (
            $user->hasRole('Saas Owner') || 
            $user->hasRole('Platform Admin') || 
            $user->hasRole('Super Admin') || 
            $user->hasRole('Admin') || 
            $user->hasRole('Super Administrator') ||
            $user->hasRole('Administrator')
        );

        if (!$isAdmin) {
            if (
                ($request->has('mix_design_id') && (int)$request->mix_design_id !== (int)$salesorder->mix_design_id) ||
                ($request->has('total_qty') && (float)$request->total_qty !== (float)$salesorder->total_qty) ||
                ($request->has('concrete_pump') && ($request->filled('concrete_pump') ? (int)$request->concrete_pump : null) !== ($salesorder->concrete_pump !== null ? (int)$salesorder->concrete_pump : null)) ||
                ($request->has('pump_rate') && (float)$request->pump_rate !== (float)$salesorder->pump_rate)
            ) {
                return redirect()->back()->withErrors(['error' => 'Only administrators are authorized to modify Mix Design, Total Quantity, Concrete Pump Type, or Pump Rate.']);
            }
        }

        $payload = $request->validated();
        
        $tableColumns = Schema::getColumnListing('mm_sales_orders');
        $hasPlantIdColumn = in_array('plant_id', $tableColumns, true);
        $hasTotalQtyColumn = in_array('total_qty', $tableColumns, true);
        $hasLegacyQtyColumn = in_array('quantity', $tableColumns, true);

        if ($hasPlantIdColumn) {
            $payload['plant_id'] = $payload['plant_id'] ?? $salesorder->plant_id;
        }

        if ($hasLegacyQtyColumn && !$hasTotalQtyColumn && isset($payload['total_qty'])) {
            $payload['quantity'] = $payload['total_qty'];
        }

        // if ($hasActiveData) {
        //     unset($payload['customer_id'], $payload['site_id'], $payload['mix_design_id']);
        // }

        $payload = collect($payload)->only($tableColumns)->toArray();

        DB::transaction(function () use ($payload, $salesorder) {
            $salesorder->update($payload);
            $salesorder->refreshProduction();
        });

        return redirect()->back()->with('success', 'Sales Order updated successfully.');
    }

    public function destroy(SalesOrder $salesorder)
    {
        $this->authorizeModule('delete');
        $this->ensurePlantScope($salesorder);

        $user = request()->user();
        $isSuperAdmin = $user && method_exists($user, 'hasRole') && $user->hasRole(['Saas Owner', 'Platform Admin', 'Super Admin']);

        if (!$isSuperAdmin && ($salesorder->batches()->exists() || $salesorder->dispatches()->exists())) {
            return redirect()->back()->with('error', 'Cannot delete this sales order because it has associated batches or dispatches.');
        }

        $salesorder->delete();

        return redirect()->back()->with('success', 'Sales Order deleted successfully.');
    }

    private function ensurePlantScope(SalesOrder $salesOrder): void
    {
        if (Schema::hasColumn('mm_sales_orders', 'plant_id') && (int) $salesOrder->plant_id !== (int) session('active_plant_id')) {
            abort(403, 'You can only manage sales orders from the active plant.');
        }
    }
}
