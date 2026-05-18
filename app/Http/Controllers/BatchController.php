<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Requests\StoreBatchRequest;
use App\Http\Requests\UpdateBatchRequest;
use App\Models\Batch;
use App\Models\BatchMaterial;
use App\Models\Image;
use App\Models\Product;
use App\Models\Quantity;
use App\Models\WorkOrder;
use App\Models\Dispatch;
use App\Models\DispatchStatus;
use App\Models\Plant;
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
            'dispatches.truck',
            'dispatches.salesExecutive',
            'dispatches.creator:id,email',
            'dispatches.modifier:id,email',
            'dispatches.status.invoice.createdBy:id,email',
            'materials.product',
            'materials.uom'
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
            'trucks' => MachinesDropdown(),
            'customers' => PatronsDropdown('Customer'),
            'transporters' => PatronsDropdown(), // assuming PatronsDropdown handles this
            'loading_sites' => SitesDropdown('loading'),
            'unloading_sites' => SitesDropdown('unloading'),
            'personnel' => PersonnelDropdown(),
            'taxes' => TaxesDropdown('sales'),
            'products' => ProductsDropdown(),
            'uoms' => Productunit(),
            'statuses' => Batch::statusOptions(),
            'payment_methods' => PaymentMethodsDropdown(),
            
            'sales_ledgers' => toSelectOptions(LedgersDropdown('REVENUE'),'title','id'),
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

        $materialsData = $payload['materials'] ?? [];

        $batch = DB::transaction(function () use ($payload, $workOrder, $emptyPhoto, $loadedPhoto, $materialsData) {
            $payload['batch_no'] = $payload['batch_no'] ?? ($workOrder->batches()->max('batch_no') + 1);
            $payload['status'] = $payload['status'] ?? Batch::STATUS_PLANNED;

            $materials = $materialsData;
            unset($payload['materials']);

            // Auto-assign shift if not provided
            if (empty($payload['shift'])) {
                $plant = Plant::find(session('active_plant_id', $workOrder->plant_id));
                if ($plant) {
                    $shiftInfo = $plant->getCurrentShiftInfo($payload['start_time'] ?? null);
                    $payload['shift'] = $shiftInfo['shift'];
                }
            }

            $batch = Batch::create($payload);
            $this->syncMaterials($batch, $materials);
            
            // Deduct stock for materials used in this batch ONLY if dispatched/completed
            if (in_array($batch->status, [Batch::STATUS_DISPATCHED, Batch::STATUS_COMPLETED])) {
                $this->adjustStock($batch, $materials);
            }
            
            $workOrder->refreshProduction();

            // Send notification if batch is created as dispatched or completed
            if (in_array($batch->status, [Batch::STATUS_DISPATCHED, Batch::STATUS_COMPLETED])) {
                auth()->user()->notify(new \App\Notifications\BatchCompletedNotification($batch));
            }

            // Create initial Dispatch
            $dispatchData = [
                'work_order_id' => $payload['work_order_id'],
                'batch_id' => $batch->id,
                'plant_id' => session('active_plant_id', $workOrder->plant_id),
                'customer_id' => $workOrder->customer_id,
                'mixdesign_id' => $workOrder->mix_design_id,
                'unload_site_id' => $workOrder->site_id,
                'truck_id' => $payload['truck_id'] ?? null,
                'transport_id' => $payload['transport_id'] ?? null,
                'driver_id' => $payload['driver_id'] ?? null,
                'sales_executive_id' => $payload['sales_executive_id'] ?? null,
                'empty_weight_truck' => $payload['empty_weight_truck'] ?? 0,
                'loaded_weight_truck' => $payload['loaded_weight_truck'] ?? null,
                'net_weight' => $payload['net_weight'] ?? null,
                'load_site_id' => $payload['site_id'] ?? null,
                'empty_time' => $payload['empty_time'] ?? null,
                'load_time' => $payload['load_time'] ?? null,
                'dispatch_status' => 'Draft',
            ];

            // Generate Dispatch Number
            $currentDate = now();
            $startYear = $currentDate->month >= 4 ? $currentDate->year : $currentDate->year - 1;
            $endYear = $startYear + 1;
            $fyString = substr($startYear, -2) . substr($endYear, -2);
            $prefix = "DP-{$fyString}-";
            
            $maxNumber = Dispatch::where('plant_id', $dispatchData['plant_id'])
                ->where('prefix', $prefix)
                ->max(DB::raw('CAST(dispatch_no AS UNSIGNED)'));
            $dispatchData['prefix'] = $prefix;
            $dispatchData['dispatch_no'] = (string)(($maxNumber ?: 0) + 1);

            $dispatch = Dispatch::create($dispatchData);
            $dispatch->status()->updateOrCreate(['dispatch_id' => $dispatch->id]);

            if ($emptyPhoto) $this->storeBatchImage($batch, $emptyPhoto, 'empty');
            if ($loadedPhoto) $this->storeBatchImage($batch, $loadedPhoto, 'loaded');

            return $batch;
        });

        // Try to push to scheduler
        $this->pushToSchedulerAPI($batch, $materialsData);

        return redirect()->back()->with('success', 'Batch created successfully.');
    }

    private function pushToSchedulerAPI(Batch $batch, array $materialsData): bool
    {
        try {
            $workOrder = $batch->workOrder;
            $workOrder->loadMissing(['plant', 'customer.addresses', 'site', 'mixDesign']);
            
            $batchMaterialsForPayload = $materialsData;

            $matArray = [];
            foreach ($batchMaterialsForPayload as $mat) {
                $matName = $mat['material_name'] ?? null;
                if (!$matName && isset($mat['product_id'])) {
                    $matName = Product::find($mat['product_id'])->title ?? "";
                }
                $matArray[] = [
                    "item" => $matName ?? "",
                    "tar" => (string)($mat['target_qty'] ?? 0)
                ];
            }

            $schedulerPayload = [
                "plant_sl" => $workOrder->plant->code ?? "",
                "plant_type" => $workOrder->plant->plant_type ?? "",
                "order_no" => $workOrder->order_no ?? "",
                "order_date" => $workOrder->created_at ? $workOrder->created_at->format('Y-m-d') : "",
                "order_status" => (string)$workOrder->status,
                "cust_id" => current(explode('-', $workOrder->customer->code ?? "")) ?: ($workOrder->customer->id ?? ""),
                "cust_name" => $workOrder->customer->legal_name ?? "",
                "cust_add_l1" => $workOrder->customer->addresses->first()->line_1 ?? "",
                "cust_add_l2" => $workOrder->customer->addresses->first()->city ?? "",
                "site_name" => $workOrder->site->name ?? "",
                "site_add_l1" => $workOrder->site->site_address_1 ?? "",
                "site_add_l2" => $workOrder->site->zipcode ?? "", 
                "strength" => "",
                "consistency" => "",
                "slump" => "",
                "wat_cem_ratio" => "",
                "mix_time" => "",
                "mix_dis_time" => "",
                "pre_mix_time" => "",
                "rec_id" => current(explode('-', $workOrder->mixDesign->design_code ?? "")) ?: ($workOrder->mixDesign->design_code ?? ""),
                "rec_name" => $workOrder->mixDesign->design_name ?? "",
                "qty" => (string)($payload['batch_size'] ?? $batch->batch_size ?? "0"),
                "mat" => $matArray
            ];

            $token = $this->getSchedulerToken($workOrder->plant);
            
            $request = \Illuminate\Support\Facades\Http::withHeaders([
                'Accept' => 'application/json',
            ]);

            if ($token) {
                $request = $request->withToken($token);
            }

            $apiUrl = $workOrder->plant->scheduler_api_url ?: url('/api/production__Order__data');
            $response = $request->post($apiUrl, $schedulerPayload);
            
            if ($response->successful()) {
                $batch->sync_status = 'success';
                if ($batch->status == Batch::STATUS_PLANNED) {
                    $batch->status = Batch::STATUS_LOADING;
                }
                $batch->save();
                return true;
            } else {
                $batch->sync_status = 'failed';
                $batch->save();
                return false;
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to post batch data to scheduler: " . $e->getMessage());
            $batch->sync_status = 'failed';
            $batch->save();
            return false;
        }
    }

    public function syncToScheduler(Batch $batch)
    {
        $this->authorizeModule('edit');
        $this->ensurePlantScope($batch->workOrder);
        
        $success = $this->pushToSchedulerAPI($batch, $batch->materials->toArray());
        
        if ($success) {
            return redirect()->back()->with('success', 'Batch successfully pushed to scheduler.');
        } else {
            return redirect()->back()->with('error', 'Failed to push batch to scheduler.');
        }
    }

    private function getSchedulerToken(\App\Models\Plant $plant)
    {
        if ($staticToken = $plant->scheduler_api_token) {
            return $staticToken;
        }

        return \Illuminate\Support\Facades\Cache::remember('scheduler_oauth_token_' . $plant->id, 3000, function() use ($plant) {
            $authUrl = $plant->scheduler_oauth_url;
            if (!$authUrl) return '';

            try {
                $response = \Illuminate\Support\Facades\Http::asForm()->post($authUrl, [
                    'grant_type' => 'client_credentials',
                    'client_id' => $plant->scheduler_client_id,
                    'client_secret' => $plant->scheduler_client_secret,
                ]);

                if ($response->successful()) {
                    return $response->json('access_token');
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Failed to generate scheduler OAuth token for plant {$plant->id}: " . $e->getMessage());
            }

            return '';
        });
    }

    public function show(Batch $batch)
    {
        $this->authorizeModule('menu');
        $this->ensurePlantScope($batch->workOrder);

        $batch->load([
            'workOrder.customer',
            'workOrder.mixDesign.items.product',
            'workOrder.mixDesign.items.uom',
            'workOrder.mixDesign.concrete_grade',
            'workOrder.site',
            'materials.product:id,title', 
            'materials.uom:id,unit_name,unit_code',
            'dispatches.status.invoice.createdBy:id,username',
            'dispatches.truck',
            'dispatches.driver',
            'dispatches.salesExecutive',
            'dispatches.creator:id,email',
            'dispatches.modifier:id,email'
        ]);

        return response()->json($batch);
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
            
            $batch->workOrder->refreshProduction();

            // Send notification if batch is newly dispatched or completed
            if (in_array($batch->status, [Batch::STATUS_DISPATCHED, Batch::STATUS_COMPLETED]) && 
                $oldStatus != $batch->status) {
                auth()->user()->notify(new \App\Notifications\BatchCompletedNotification($batch));
            }

            // Update associated dispatch if it exists
            $dispatch = $batch->dispatches()->first();
            if ($dispatch) {
                $dispatch->update([
                    'truck_id' => $payload['truck_id'] ?? $dispatch->truck_id,
                    'transport_id' => $payload['transport_id'] ?? $dispatch->transport_id,
                    'driver_id' => $payload['driver_id'] ?? $dispatch->driver_id,
                    'sales_executive_id' => $payload['sales_executive_id'] ?? $dispatch->sales_executive_id,
                    'empty_weight_truck' => $payload['empty_weight_truck'] ?? $dispatch->empty_weight_truck,
                    'loaded_weight_truck' => $payload['loaded_weight_truck'] ?? $dispatch->loaded_weight_truck,
                    'net_weight' => $payload['net_weight'] ?? $dispatch->net_weight,
                    'load_site_id' => $payload['site_id'] ?? $dispatch->load_site_id,
                    'empty_time' => $payload['empty_time'] ?? $dispatch->empty_time,
                    'load_time' => $payload['load_time'] ?? $dispatch->load_time,
                ]);
            }

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

            // Set audit fields before deletion
            $batch->deleted_by = auth()->id();
            $batch->save();

            // Delete related materials and dispatches
            $batch->materials()->delete();
            
            foreach ($batch->dispatches as $dispatch) {
                $dispatch->deleted_by = auth()->id();
                $dispatch->save();
                $dispatch->delete();
            }

            $batch->delete();
            
            // Recalculate production quantity for the work order
            $batch->workOrder->refreshProduction();
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

        $orderNo = $batch->workOrder?->order_no ?? 'order';
        $safeOrderNo = str_replace(['/', '\\'], '-', $orderNo);
        $filename = sprintf(
            'batch-sheet-%s-%s.pdf',
            $safeOrderNo,
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
            'dispatches.truck',
            'dispatches.driver',
            'materials.product.category',
            'materials.uom',
        ]);

        $this->ensurePlantScope($batch->workOrder);

        return $batch;
    }

    private function prepareBatchSheetData(Batch $batch): array
    {
        return $batch->getReportData();
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
        // Use created_at for the consumption date, fallback to now
        $date = $batch->created_at ? $batch->created_at->toDateString() : now()->toDateString();
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

    private function storeBatchImage(Batch $batch, ?string $base64Data, string $type): void
    {
        if (!$base64Data || !str_contains($base64Data, 'base64')) return;

        try {
            // Extract base64 content
            if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $typeMatch)) {
                $extension = strtolower($typeMatch[1]);
                $allowedExtensions = ['jpeg', 'png', 'jpg', 'gif', 'svg'];
                
                if (!in_array($extension, $allowedExtensions)) {
                    \Illuminate\Support\Facades\Log::warning("Blocked suspicious batch image upload with extension: {$extension}");
                    return;
                }

                $data = substr($base64Data, strpos($base64Data, ',') + 1);
                $data = base64_decode($data);
            } else {
                return;
            }

            $fileName = "batch_{$batch->id}_{$type}_" . time() . ".{$extension}";
            $path = "images/batching/{$fileName}";
            
            Storage::disk('public')->put($path, $data);

            Image::updateOrCreate(
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
