<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Requests\StoreBatchRequest;
use App\Http\Requests\UpdateBatchRequest;
use App\Models\Batch;
use App\Models\BatchMaterial;
use App\Models\MmImage;
use App\Models\Product;
use App\Models\Quantity;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;

class BatchController extends Controller
{
    use AuthorizesModule;

    protected string $module = 'work_orders';

    public function index()
    {
        $this->authorizeModule('menu');
        $activePlantId = session('active_plant_id');

        $batches = Batch::with([
            'workOrder.customer',
            'workOrder.mixDesign',
            'workOrder.site',
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
            'customers' => PatronsDropdown($activePlantId, 'Customer'),
            'transporters' => PatronsDropdown($activePlantId), // assuming PatronsDropdown handles this
            'loading_sites' => SitesDropdown($activePlantId,'loading'),
            'unloading_sites' => SitesDropdown($activePlantId,'unloading'),
            'personnel' => PersonnelDropdown($activePlantId),
            'taxes' => TaxesDropdown($activePlantId,'sales'),
            'products' => ProductsDropdown($activePlantId),
            'uoms' => Productunit(),
            'statuses' => Batch::statusOptions(),
            'nextBatchNo' => $nextBatchNo ?: 1,
            'batchingSettings' => \App\Models\CustomSetting::getForModule($activePlantId, 'batching'),
        ]);
    }

    public function store(StoreBatchRequest $request)
    {
        $this->authorizeModule('create');
        $payload = $request->validated();
        
        $workOrder = WorkOrder::query()->findOrFail($payload['work_order_id']);
        $this->ensurePlantScope($workOrder);

        $emptyPhoto = $payload['empty_weight_photo'] ?? null;
        $loadedPhoto = $payload['loaded_weight_photo'] ?? null;
        unset($payload['empty_weight_photo'], $payload['loaded_weight_photo']);

        DB::transaction(function () use ($payload, $workOrder, $emptyPhoto, $loadedPhoto) {
            $payload['batch_no'] = $payload['batch_no'] ?? ($workOrder->batches()->max('batch_no') + 1);
            $payload['status'] = $payload['status'] ?? Batch::STATUS_PLANNED;

            $materials = $payload['materials'] ?? [];
            unset($payload['materials']);

            $batch = Batch::create($payload);
            $this->syncMaterials($batch, $materials);
            
            // Deduct stock for materials used in this batch ONLY if dispatched/completed
            if (in_array($batch->status, [Batch::STATUS_DISPATCHED, Batch::STATUS_COMPLETED])) {
                $this->adjustStock($batch, $materials);
            }
            
            $this->refreshWorkOrderProduction($workOrder);

            if ($emptyPhoto) $this->storeBatchImage($batch, $emptyPhoto, 'empty');
            if ($loadedPhoto) $this->storeBatchImage($batch, $loadedPhoto, 'loaded');
        });

        return redirect()->back()->with('success', 'Batch created successfully.');
    }

    public function update(UpdateBatchRequest $request, Batch $batch)
    {
        $this->authorizeModule('edit');
        $batch->load('workOrder');
        $this->ensurePlantScope($batch->workOrder);

        $payload = $request->validated();

        $emptyPhoto = $payload['empty_weight_photo'] ?? null;
        $loadedPhoto = $payload['loaded_weight_photo'] ?? null;
        unset($payload['empty_weight_photo'], $payload['loaded_weight_photo']);

        $oldMaterials = $batch->materials()->get()->toArray();
        $oldStatus = $batch->status;

        DB::transaction(function () use ($batch, $payload, $emptyPhoto, $loadedPhoto, $oldMaterials, $oldStatus) {
            $materials = $payload['materials'] ?? [];
            unset($payload['materials']);

            // 1. Revert old consumption only if it was previously deducted
            if (in_array($oldStatus, [Batch::STATUS_DISPATCHED, Batch::STATUS_COMPLETED])) {
                $this->adjustStock($batch, $oldMaterials, true);
            }

            $batch->fill($payload);
            
            // Auto-update status to dispatched if any material has actual quantity > 0
            $hasActual = collect($materials)->contains(fn($m) => (float)($m['actual_qty'] ?? 0) > 0);
            if ($hasActual && $batch->status == Batch::STATUS_PLANNED) {
                $batch->status = Batch::STATUS_DISPATCHED;
            }

            $batch->updated_by = auth()->id();
            $batch->updated_at = now();
            $batch->save();
            
            $this->syncMaterials($batch, $materials);
            
            // 2. Apply new consumption only if now dispatched/completed
            if (in_array($batch->status, [Batch::STATUS_DISPATCHED, Batch::STATUS_COMPLETED])) {
                $this->adjustStock($batch, $materials);
            }
            
            $this->refreshWorkOrderProduction($batch->workOrder);

            if ($emptyPhoto) $this->storeBatchImage($batch, $emptyPhoto, 'empty');
            if ($loadedPhoto) $this->storeBatchImage($batch, $loadedPhoto, 'loaded');
        });

        return redirect()->back()->with('success', 'Batch updated successfully.');
    }

    public function destroy(Batch $batch)
    {
        $this->authorizeModule('delete');
        $batch->load('workOrder');
        $this->ensurePlantScope($batch->workOrder);

        DB::transaction(function () use ($batch) {
            $materials = $batch->materials()->get()->toArray();
            
            // Revert stock only if it was previously deducted
            if (in_array($batch->status, [Batch::STATUS_DISPATCHED, Batch::STATUS_COMPLETED])) {
                $this->adjustStock($batch, $materials, true);
            }

            $batch->materials()->delete();
            $batch->delete();
            $this->refreshWorkOrderProduction($batch->workOrder);
        });

        return redirect()->back()->with('success', 'Batch deleted successfully.');
    }

    public function report(Batch $batch)
    {
       
        $batch = $this->resolveBatchSheetBatch($batch);
        
        $sheet = $this->prepareBatchSheetData($batch);

        return view('pdfs.batches.batch_sheet', [
            'batch' => $batch,
            'sheet' => $sheet,
            'isPreview' => true,
        ]);
    }

    public function downloadPdf(Batch $batch)
    {
        $batch = $this->resolveBatchSheetBatch($batch);
        $sheet = $this->prepareBatchSheetData($batch);

        $pdf = Pdf::loadView('pdfs.batches.batch_sheet', [
            'batch' => $batch,
            'sheet' => $sheet,
            'isPreview' => false,
        ])->setPaper('a4', 'landscape');

        $filename = sprintf(
            'batch-sheet-%s-%s.pdf',
            $batch->workOrder?->order_no ?? 'order',
            $batch->batch_no ?? $batch->id
        );

        return $pdf->download($filename);
    }

    private function resolveBatchSheetBatch(Batch $batch): Batch
    {
        $batch->load([
            'workOrder.customer',
            'workOrder.site',
            'workOrder.plant.entity',
            'workOrder.mixDesign.concrete_grade',
            'truck',
            'driver',
            'site',
            'materials.product.category',
            'materials.uom',
        ]);

        $this->ensurePlantScope($batch->workOrder);

        return $batch;
    }

    private function prepareBatchSheetData(Batch $batch): array
    {
        $materials = $batch->materials->map(function ($material) {
            $name = (string) ($material->material_name ?: ($material->product->title ?? 'Material'));
            $categoryName = (string) ($material->product->category->name ?? '');
            
            return [
                'key' => $material->id,
                'name' => $name,
                'category_name' => $categoryName,
                'short' => strtoupper(substr(preg_replace('/\s+/', '', $name), 0, 6)),
                'target' => (float) $material->target_qty,
                'actual' => (float) $material->actual_qty,
                'diff_percent' => (float) ($material->deviation_quantity ?? 0),
            ];
        })->values();

        $groups = [
            ['name' => 'Aggregate', 'keywords' => ['SAND', 'AGG', '10MM', '12MM', '20MM', 'DUST', 'GGBS']],
            ['name' => 'Cement', 'keywords' => ['CEM', 'CEMENT', 'FLY', 'OPC', 'PPC']],
            ['name' => 'Water / Ice', 'keywords' => ['WTR', 'WATER', 'ICE']],
            ['name' => 'Admixture', 'keywords' => ['ADM', 'ADMI', 'CHEM', 'RET']],
            ['name' => 'Silica', 'keywords' => ['SIL', 'SILICA', 'FUME']],
        ];

        $grouped = [];
        foreach ($groups as $group) {
            $grouped[$group['name']] = [];
        }

        foreach ($materials as $material) {
            $upperName = strtoupper($material['name']);
            $upperCategory = strtoupper($material['category_name']);
            $matched = false;

            foreach ($groups as $group) {
                foreach ($group['keywords'] as $keyword) {
                    if (str_contains($upperName, $keyword) || str_contains($upperCategory, $keyword)) {
                        $grouped[$group['name']][] = $material;
                        $matched = true;
                        break 2;
                    }
                }
            }

            if (!$matched) {
                $grouped['Aggregate'][] = $material;
            }
        }

        $groupOrder = array_map(fn ($group) => $group['name'], $groups);
        $tableMaterials = [];
        foreach ($groupOrder as $groupName) {
            if (empty($grouped[$groupName])) {
                // Add a placeholder for empty groups to maintain table structure
                $placeholder = [
                    'key' => 'placeholder-' . $groupName,
                    'name' => '-',
                    'category_name' => $groupName,
                    'short' => '-',
                    'target' => 0.0,
                    'actual' => 0.0,
                    'diff_percent' => 0.0,
                    'is_placeholder' => true,
                ];
                $grouped[$groupName][] = $placeholder;
                $tableMaterials[] = $placeholder;
            } else {
                foreach ($grouped[$groupName] as $entry) {
                    $tableMaterials[] = $entry;
                }
            }
        }

        $totalSetWeight = collect($tableMaterials)->sum('target');
        $totalActualWeight = collect($tableMaterials)->sum('actual');
        $totalDifferencePercent = $totalSetWeight > 0
            ? (($totalActualWeight - $totalSetWeight) / $totalSetWeight) * 100
            : 0;

        return [
            'group_order' => $groupOrder,
            'grouped' => $grouped,
            'table_materials' => $tableMaterials,
            'total_set_weight' => round($totalSetWeight, 2),
            'total_actual_weight' => round($totalActualWeight, 2),
            'total_difference_percent' => round($totalDifferencePercent, 2),
        ];
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

    private function adjustStock(Batch $batch, array $materials, bool $isReverting = false): void
    {
        $userId = auth()->id();
        // Use load_time for the consumption date, fallback to now
        $date = $batch->load_time ? $batch->load_time->toDateString() : now()->toDateString();
        $plantId = $batch->workOrder->plant_id ?? session('active_plant_id');

        foreach ($materials as $item) {
            if (empty($item['product_id']) || (float)($item['actual_qty'] ?? 0) <= 0) continue;

            $quantityRecord = Quantity::firstOrNew([
                'plant_id' => $plantId,
                'product_id' => $item['product_id'],
                'uom_id' => $item['uom_id'],
                'date' => $date,
                'is_warehouse' => true,
            ]);

            if (!$quantityRecord->exists) {
                $quantityRecord->opening_quantity = 0;
                $quantityRecord->created_by = $userId;
                $quantityRecord->status = 1;
            }

            $adjustment = (float)$item['actual_qty'];
            
            if ($isReverting) {
                // Add back to stock
                $quantityRecord->quantity = (float)$quantityRecord->quantity + $adjustment;
            } else {
                // Subtract from stock
                $quantityRecord->quantity = (float)$quantityRecord->quantity - $adjustment;
            }

            $quantityRecord->updated_by = $userId;
            $quantityRecord->save();
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

    private function storeBatchImage(Batch $batch, ?string $base64Data, string $type): void
    {
        if (!$base64Data || !str_contains($base64Data, 'base64')) return;

        try {
            // Extract base64 content
            if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $typeMatch)) {
                $data = substr($base64Data, strpos($base64Data, ',') + 1);
                $extension = strtolower($typeMatch[1]);
                $data = base64_decode($data);
            } else {
                return;
            }

            $fileName = "batch_{$batch->id}_{$type}_" . time() . ".{$extension}";
            $path = "images/batching/{$fileName}";
            
            Storage::disk('public')->put($path, $data);

            MmImage::updateOrCreate(
                ['category' => 'Batching', 'ref_no' => (string)$batch->id, 'image_name' => "{$type}_weight_snap"],
                [
                    'alt_txt' => ucfirst($type) . ' Weight Photo',
                    'image_path' => $path,
                    'plant_id' => session('active_plant_id'),
                ]
            );
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to store batch image: " . $e->getMessage());
        }
    }

    private function ensurePlantScope(WorkOrder $workOrder): void
    {
        if ((int) $workOrder->plant_id !== (int) session('active_plant_id')) {
            abort(403, 'You can only manage batches from the active plant.');
        }
    }
}
