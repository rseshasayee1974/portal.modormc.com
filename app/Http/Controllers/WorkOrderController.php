<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Requests\StoreWorkOrderRequest;
use App\Http\Requests\UpdateWorkOrderRequest;
use App\Models\WorkOrder;
use App\Services\WorkOrders\WorkOrderIndexDataFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class WorkOrderController extends Controller
{
    use AuthorizesModule;

    protected string $module = 'work_orders';

    public function __construct(private readonly WorkOrderIndexDataFactory $indexDataFactory)
    {
    }

    public function index()
    {
        $this->authorizeModule('menu');
        $activePlantId = session('active_plant_id');
// dd($this->indexDataFactory->build($activePlantId !== null ? (int) $activePlantId : null));
        return Inertia::render(
            'WorkOrders/Index',
            $this->indexDataFactory->build($activePlantId !== null ? (int) $activePlantId : null)
        );
    }

    public function store(StoreWorkOrderRequest $request)
    {
        $this->authorizeModule('create');
        $payload = $request->validated();

        if (empty($payload['order_no'])) {
            $payload['order_no'] = WorkOrder::generateOrderNo($payload['prefix'] ?? 'WO');
        }

        $tableColumns = Schema::getColumnListing('mm_work_orders');
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
            WorkOrder::create($payload);
        });

        return redirect()->back()->with('success', 'Work order created successfully.');
    }

    public function update(UpdateWorkOrderRequest $request, WorkOrder $workorder)
    {
        $this->authorizeModule('edit');
        $this->ensurePlantScope($workorder);
        $payload = $request->validated();
        $tableColumns = Schema::getColumnListing('mm_work_orders');
        $hasPlantIdColumn = in_array('plant_id', $tableColumns, true);
        $hasTotalQtyColumn = in_array('total_qty', $tableColumns, true);
        $hasLegacyQtyColumn = in_array('quantity', $tableColumns, true);

        if ($hasPlantIdColumn) {
            $payload['plant_id'] = $payload['plant_id'] ?? $workorder->plant_id;
        }

        if ($hasLegacyQtyColumn && !$hasTotalQtyColumn && isset($payload['total_qty'])) {
            $payload['quantity'] = $payload['total_qty'];
        }

        $payload = collect($payload)->only($tableColumns)->toArray();

        DB::transaction(function () use ($payload, $workorder) {
            $workorder->update($payload);
        });

        return redirect()->back()->with('success', 'Work order updated successfully.');
    }

    public function destroy(WorkOrder $workorder)
    {
        $this->authorizeModule('delete');
        $this->ensurePlantScope($workorder);
        $workorder->delete();

        return redirect()->back()->with('success', 'Work order deleted successfully.');
    }

    private function ensurePlantScope(WorkOrder $workOrder): void
    {
        if (Schema::hasColumn('mm_work_orders', 'plant_id') && (int) $workOrder->plant_id !== (int) session('active_plant_id')) {
            abort(403, 'You can only manage work orders from the active plant.');
        }
    }
}
