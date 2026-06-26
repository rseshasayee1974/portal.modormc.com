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

        $payload = collect($payload)->only($tableColumns)->toArray();

        DB::transaction(function () use ($payload) {
            SalesOrder::create($payload);
        });

        return redirect()->back()->with('success', 'Sales Order created successfully.');
    }

    public function update(UpdateSalesOrderRequest $request, SalesOrder $salesorder)
    {
        $this->authorizeModule('edit');
        $this->ensurePlantScope($salesorder);
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

        if ($salesorder->batches()->exists() || $salesorder->dispatches()->exists()) {
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
