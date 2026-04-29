<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Requests\StoreBatchRequest;
use App\Http\Requests\UpdateBatchRequest;
use App\Models\Batch;
use App\Models\BatchMaterial;
use App\Models\Product;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class BatchController extends Controller
{
    use AuthorizesModule;

    protected string $module = 'work_orders';

    public function index()
    {
        $this->authorizeModule('menu');
        $activePlantId = session('active_plant_id');

        $batches = Batch::with([
            'workOrder', 
            'truck', 
            'materials.product:id,title', 
            'materials.uom:id,unit_name,unit_code'
        ])
        ->whereHas('workOrder', fn ($q) => $q->where('plant_id', $activePlantId))
        ->latest()
        ->get();

        $workOrders = WorkOrder::query()
            ->with(['mixDesign.items.product', 'mixDesign.items.uom', 'mixDesign.concrete_grade', 'customer', 'site'])
            ->withCount('batches')
            ->where('plant_id', $activePlantId)
            ->whereIn('status', [WorkOrder::STATUS_IN_PROGRESS])
            ->orderBy('order_no')
            ->get();

        $now = now();
        $startYear = $now->month >= 4 ? $now->year : $now->year - 1;
        $fyStart = \Carbon\Carbon::create($startYear, 4, 1, 0, 0, 0);

        $nextBatchNo = Batch::query()
            ->whereHas('workOrder', fn ($q) => $q->where('plant_id', $activePlantId))
            ->where('created_at', '>=', $fyStart)
            ->max('batch_no') + 1;

        return Inertia::render('Batches/Index', [
            'batches' => $batches,
            'workOrders' => $workOrders,
            'trucks' => MachinesDropdown($activePlantId),
            'transporters' => PatronsDropdown($activePlantId), // assuming PatronsDropdown handles this
            'loading_sites' => SitesDropdown($activePlantId,'loading'),
            'unloading_sites' => SitesDropdown($activePlantId,'unloading'),
            'personnel' => PersonnelDropdown($activePlantId),
            'taxes' => TaxesDropdown($activePlantId,'sales'),
            'products' => ProductsDropdown($activePlantId),
            'uoms' => Productunit(),
            'statuses' => Batch::statusOptions(),
            'nextBatchNo' => $nextBatchNo ?: 1,
        ]);
    }

    public function store(StoreBatchRequest $request)
    {
        $this->authorizeModule('create');
        $payload = $request->validated();
        
        $workOrder = WorkOrder::query()->findOrFail($payload['work_order_id']);
        $this->ensurePlantScope($workOrder);

        DB::transaction(function () use ($payload, $workOrder) {
            $payload['batch_no'] = $payload['batch_no'] ?? ($workOrder->batches()->max('batch_no') + 1);
            $payload['status'] = $payload['status'] ?? Batch::STATUS_PLANNED;

            $materials = $payload['materials'] ?? [];
            unset($payload['materials']);

            $batch = Batch::create($payload);
            $this->syncMaterials($batch, $materials);
            $this->refreshWorkOrderProduction($workOrder);
        });

        return redirect()->back()->with('success', 'Batch created successfully.');
    }

    public function update(UpdateBatchRequest $request, Batch $batch)
    {
        $this->authorizeModule('edit');
        $batch->load('workOrder');
        $this->ensurePlantScope($batch->workOrder);

        $payload = $request->validated();

        DB::transaction(function () use ($batch, $payload) {
            $materials = $payload['materials'] ?? [];
            unset($payload['materials']);

            $batch->fill($payload);
            $batch->updated_by = auth()->id();
            $batch->updated_at = now();
            $batch->save();
            
            $this->syncMaterials($batch, $materials);
            $this->refreshWorkOrderProduction($batch->workOrder);
        });

        return redirect()->back()->with('success', 'Batch updated successfully.');
    }

    public function destroy(Batch $batch)
    {
        $this->authorizeModule('delete');
        $batch->load('workOrder');
        $this->ensurePlantScope($batch->workOrder);

        DB::transaction(function () use ($batch) {
            $batch->materials()->delete();
            $batch->delete();
            $this->refreshWorkOrderProduction($batch->workOrder);
        });

        return redirect()->back()->with('success', 'Batch deleted successfully.');
    }

    private function syncMaterials(Batch $batch, array $materials): void
    {
        $existingIds = collect($materials)->pluck('id')->filter()->values()->all();
        $batch->materials()->whereNotIn('id', $existingIds)->delete();

        foreach ($materials as $item) {
            $materialName = $item['material_name'] ?? Product::query()->whereKey($item['product_id'])->value('title') ?? 'Material';

            $row = [
                'product_id' => $item['product_id'],
                'material_name' => $materialName,
                'target_qty' => $item['target_qty'],
                'actual_qty' => $item['actual_qty'],
                'deviation_quantity' => $item['deviation_quantity'] ?? 0,
                'uom_id' => $item['uom_id'],
            ];

            if (!empty($item['id'])) {
                $batchMat = BatchMaterial::query()
                    ->where('id', $item['id'])
                    ->where('batch_id', $batch->id)
                    ->first();
                    
                if ($batchMat) {
                    $batchMat->update($row);
                }
            } else {
                $batch->materials()->create($row);
            }
        }
    }

    private function refreshWorkOrderProduction(WorkOrder $workOrder): void
    {
        $producedQty = (float) $workOrder->batches()->sum('batch_size');
        $status = $workOrder->status;

        if ($producedQty <= 0 && $workOrder->status !== WorkOrder::STATUS_CANCELLED) {
            $status = WorkOrder::STATUS_SCHEDULED;
        } elseif ($producedQty > 0 && $producedQty < (float) $workOrder->total_qty && $workOrder->status !== WorkOrder::STATUS_CANCELLED) {
            $status = WorkOrder::STATUS_IN_PROGRESS;
        } elseif ($producedQty >= (float) $workOrder->total_qty && $workOrder->status !== WorkOrder::STATUS_CANCELLED) {
            $status = WorkOrder::STATUS_COMPLETED;
        }

        $workOrder->update([
            'produced_qty' => $producedQty,
            'status' => $status,
        ]);
    }

    private function ensurePlantScope(WorkOrder $workOrder): void
    {
        if ((int) $workOrder->plant_id !== (int) session('active_plant_id')) {
            abort(403, 'You can only manage batches from the active plant.');
        }
    }
}
