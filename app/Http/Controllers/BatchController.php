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
use App\Models\Plant;
use App\Models\CustomSetting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class BatchController extends Controller
{
    use AuthorizesModule;

    protected string $module = 'work_orders';

    public function index()
    {
        $this->authorizeModule('menu');
        $activePlantId = session('active_plant_id');

        $batches = Batch::with([
            'workOrder.customer:id,legal_name',
            'workOrder.mixDesign:id,design_name,design_code',
            'workOrder.site:id,name',
            'dispatches:id,batch_id,truck_id',
            'dispatches.truck:id,registration'
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
        $fyStart = Carbon::create($startYear, 4, 1, 0, 0, 0);

        $nextBatchNo = Batch::query()
            ->whereHas('workOrder', fn ($q) => $q->where('plant_id', $activePlantId))
            ->where('created_at', '>=', $fyStart)
            ->max('batch_no') + 1;

        return Inertia::render('Batches/Index', [
            'batches'           => $batches,
            'workOrders'        => $workOrders,
            'trucks'            => MachinesDropdown(),
            'customers'         => PatronsDropdown('Customer'),
            'transporters'      => PatronsDropdown('Transporter'),
            'loading_sites'     => SitesDropdown('loading'),
            'unloading_sites'   => SitesDropdown('unloading'),
            'personnel'         => PersonnelDropdown(),
            'taxes'             => TaxesDropdown('sales'),
            'products'          => ProductsDropdown(),
            'uoms'              => Productunit(),
            'statuses'          => Batch::statusOptions(),
            'payment_methods'   => PaymentMethodsDropdown(),
            'sales_ledgers'     => toSelectOptions(LedgersDropdown('REVENUE'), 'title', 'id'),
            'nextBatchNo'       => $nextBatchNo ?: 1,
            'batchingSettings'  => CustomSetting::getForModule($activePlantId, 'batching'),
        ]);
    }

    /**
     * Store a newly created batch in storage.
     */
    public function store(StoreBatchRequest $request)
    {
        $this->authorizeModule('create');
        
        // Reverted back to custom FormRequest rules container safely
        $payload = $request->validated();
        
        $workOrder = WorkOrder::query()->findOrFail($payload['work_order_id']);
        $this->ensurePlantScope($workOrder);

        $emptyPhoto = $payload['empty_weight_photo'] ?? null;
        $loadedPhoto = $payload['loaded_weight_photo'] ?? null;
        unset($payload['empty_weight_photo'], $payload['loaded_weight_photo']);

        $materialsData = $payload['materials'] ?? [];

        try {
            $batch = DB::transaction(function () use ($payload, $workOrder, $emptyPhoto, $loadedPhoto, $materialsData) {
                $payload['batch_no'] = $payload['batch_no'] ?? ($workOrder->batches()->max('batch_no') + 1);
                $payload['status'] = $payload['status'] ?? Batch::STATUS_PLANNED;

                $materials = $materialsData;
                unset($payload['materials']);

                if (empty($payload['shift'])) {
                    $plant = Plant::find(session('active_plant_id', $workOrder->plant_id));
                    if ($plant) {
                        $shiftInfo = $plant->getCurrentShiftInfo($payload['start_time'] ?? null);
                        $payload['shift'] = $shiftInfo['shift'];
                    }
                }

                $batch = Batch::create($payload);
                $this->syncMaterials($batch, $materials);
                
                if (in_array($batch->status, [Batch::STATUS_DISPATCHED, Batch::STATUS_COMPLETED])) {
                    $this->adjustStock($batch, $materials);
                }
                
                $workOrder->refreshProduction();

                if (in_array($batch->status, [Batch::STATUS_DISPATCHED, Batch::STATUS_COMPLETED])) {
                    auth()->user()->notify(new \App\Notifications\BatchCompletedNotification($batch));
                }

                // Compile structured parameters safely
                $dispatchData = [
                    'work_order_id'       => $payload['work_order_id'],
                    'batch_id'            => $batch->id,
                    'plant_id'            => session('active_plant_id', $workOrder->plant_id),
                    'customer_id'         => $workOrder->customer_id,
                    'mixdesign_id'        => $workOrder->mix_design_id,
                    'unload_site_id'      => $workOrder->site_id,
                    'truck_id'            => $payload['truck_id'] ?? null,
                    'transport_id'        => $payload['transport_id'] ?? null,
                    'driver_id'           => $payload['driver_id'] ?? null,
                    'sales_executive_id'  => $payload['sales_executive_id'] ?? null,
                    'empty_weight_truck'  => $payload['empty_weight_truck'] ?? 0,
                    'loaded_weight_truck' => $payload['loaded_weight_truck'] ?? null,
                    'net_weight'          => $payload['net_weight'] ?? null,
                    'load_site_id'        => $payload['site_id'] ?? null,
                    'empty_time'          => $payload['empty_time'] ?? null,
                    'load_time'           => $payload['load_time'] ?? null,
                    'dispatch_status'     => 'Draft',
                ];

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
                $dispatch->status()->updateOrCreate(
                    ['dispatch_id' => $dispatch->id],
                    ['plant_id' => $dispatch->plant_id]
                );

                if ($emptyPhoto) $this->storeBatchImage($batch, $emptyPhoto, 'empty');
                if ($loadedPhoto) $this->storeBatchImage($batch, $loadedPhoto, 'loaded');

                return $batch;
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'error' => [$e->getMessage()]
            ]);
        }

        // Executed outside the database transaction scope safely to avoid table deadlocks
        $this->pushToSchedulerAPI($batch, $materialsData);
        $this->broadcastBatchChange('BatchCreated', $batch);

        return redirect()->back()->with('success', 'Batch created successfully.');
    }

    /**
     * Pushes structural context configurations out to remote active hardware loops.
     */
    private function pushToSchedulerAPI(Batch $batch, array $materialsData): bool
    {
        try {
            $workOrder = $batch->workOrder;
            $workOrder->loadMissing(['plant', 'customer.addresses', 'site', 'mixDesign']);
            
            // Fixed N+1 anomaly: Query product definitions in a single trip
            $productIds = collect($materialsData)->pluck('product_id')->filter()->unique()->toArray();
            $products = !empty($productIds) ? Product::whereIn('id', $productIds)->pluck('title', 'id') : collect();

            $matArray = [];
            foreach ($materialsData as $mat) {
                $matName = $mat['material_name'] ?? null;
                if (!$matName && isset($mat['product_id'])) {
                    $matName = $products->get($mat['product_id']) ?? "";
                }
                $matArray[] = [
                    "item" => $matName ?? "",
                    "tar"  => (string)($mat['target_qty'] ?? 0)
                ];
            }

            // Fixed structural reference anomaly on payload properties
            $schedulerPayload = [
                "plant_sl"     => $workOrder->plant->code ?? "",
                "plant_type"   => $workOrder->plant->plant_type ?? "",
                "order_no"     => $workOrder->order_no ?? "",
                "order_date"   => $workOrder->created_at ? $workOrder->created_at->format('Y-m-d') : "",
                "order_status" => (string)$workOrder->status,
                "cust_id"      => current(explode('-', $workOrder->customer->code ?? "")) ?: ($workOrder->customer->id ?? ""),
                "cust_name"    => $workOrder->customer->legal_name ?? "",
                "cust_add_l1"  => $workOrder->customer->addresses->first()->line_1 ?? "",
                "cust_add_l2"  => $workOrder->customer->addresses->first()->city ?? "",
                "site_name"    => $workOrder->site->name ?? "",
                "site_add_l1"  => $workOrder->site->site_address_1 ?? "",
                "site_add_l2"  => $workOrder->site->zipcode ?? "", 
                "strength"     => "",
                "consistency"  => "",
                "slump"        => "",
                "wat_cem_ratio"=> "",
                "mix_time"     => "",
                "mix_dis_time" => "",
                "pre_mix_time" => "",
                "rec_id"       => current(explode('-', $workOrder->mixDesign->design_code ?? "")) ?: ($workOrder->mixDesign->design_code ?? ""),
                "rec_name"     => $workOrder->mixDesign->design_name ?? "",
                "qty"          => (string)($batch->batch_size ?? "0"),
                "mat"          => $matArray
            ];

            $token = $this->getSchedulerToken($workOrder->plant);
            $request = Http::withHeaders(['Accept' => 'application/json']);

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
                $this->broadcastBatchChange('BatchUpdated', $batch);
                return true;
            }

            $batch->sync_status = 'failed';
            $batch->save();
            $this->broadcastBatchChange('BatchUpdated', $batch);
            return false;

        } catch (\Exception $e) {
            Log::error("Failed to post batch data to scheduler: " . $e->getMessage());
            $batch->sync_status = 'failed';
            $batch->save();
            $this->broadcastBatchChange('BatchUpdated', $batch);
            return false;
        }
    }

    public function syncToScheduler(Batch $batch)
    {
        $this->authorizeModule('edit');
        $this->ensurePlantScope($batch->workOrder);
        
        $success = $this->pushToSchedulerAPI($batch, $batch->materials->toArray());
        
        return $success 
            ? redirect()->back()->with('success', 'Batch successfully pushed to scheduler.')
            : redirect()->back()->with('error', 'Failed to push batch to scheduler.');
    }

    private function getSchedulerToken(Plant $plant)
    {
        if ($staticToken = $plant->scheduler_api_token) {
            return $staticToken;
        }

        return Cache::remember('scheduler_oauth_token_' . $plant->id, 3000, function() use ($plant) {
            $authUrl = $plant->scheduler_oauth_url;
            if (!$authUrl) return '';

            try {
                $response = Http::asForm()->post($authUrl, [
                    'grant_type'    => 'client_credentials',
                    'client_id'     => $plant->scheduler_client_id,
                    'client_secret' => $plant->scheduler_client_secret,
                ]);

                if ($response->successful()) {
                    return $response->json('access_token');
                }
            } catch (\Exception $e) {
                Log::error("Failed to generate scheduler OAuth token for plant {$plant->id}: " . $e->getMessage());
            }

            return '';
        });
    }

    public function show(Batch $batch, Request $request)
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
    // public function update(Request $request, Batch $batch)
    {
        // dd($request->all());
        $this->authorizeModule('edit');
        $batch->load('workOrder');
        $this->ensurePlantScope($batch->workOrder);

        $payload = $request->validated();
        
        $emptyPhoto = $payload['empty_weight_photo'] ?? null;
        $loadedPhoto = $payload['loaded_weight_photo'] ?? null;
        unset($payload['empty_weight_photo'], $payload['loaded_weight_photo']);

        $oldMaterials = $batch->materials()->get()->toArray();
        $oldStatus = $batch->status;

        try {
          DB::transaction(function () use ($batch, $payload, $emptyPhoto, $loadedPhoto, $oldMaterials, $oldStatus) {

    $materials = $payload['materials'] ?? [];
    unset($payload['materials']);

    // Restore stock if editing an already dispatched/completed batch
    if (in_array($oldStatus, [Batch::STATUS_DISPATCHED, Batch::STATUS_COMPLETED])) {
        $this->adjustStock($batch, $oldMaterials, true);
    }

    $batch->fill($payload);

    $batch->updated_by = auth()->id();
    $batch->updated_at = now();
    $batch->save();

    // Sync materials first
    $this->syncMaterials($batch, $materials);

    // Determine status based on actual quantities stored in DB
    $hasActual = $batch->materials()
        ->where('actual_qty', '>', 0)
        ->exists();

    if ($hasActual) {
        $newStatus = match ($oldStatus) {
            Batch::STATUS_PLANNED => Batch::STATUS_LOADING,
            Batch::STATUS_LOADING => Batch::STATUS_DISPATCHED,
            default => $batch->status,
        };

        if ($newStatus !== $batch->status) {
            $batch->status = $newStatus;
            $batch->save();
        }
    }

    // Deduct stock only when batch becomes dispatched/completed
    if (in_array($batch->status, [Batch::STATUS_DISPATCHED, Batch::STATUS_COMPLETED])) {

        $materialsForStock = $batch->materials()
            ->get()
            ->map(fn ($material) => [
                'product_id' => $material->product_id,
                'uom_id'     => $material->uom_id,
                'actual_qty' => $material->actual_qty,
            ])
            ->toArray();

        $this->adjustStock($batch, $materialsForStock);
    }

    $batch->workOrder->refreshProduction();

    if (
        in_array($batch->status, [Batch::STATUS_DISPATCHED, Batch::STATUS_COMPLETED]) &&
        $oldStatus !== $batch->status
    ) {
        auth()->user()->notify(
            new \App\Notifications\BatchCompletedNotification($batch)
        );
    }

    $dispatch = $batch->dispatches()->first();

    if ($dispatch) {
        $dispatch->update(array_filter([
            'truck_id'            => $payload['truck_id'] ?? null,
            'transport_id'        => $payload['transport_id'] ?? null,
            'driver_id'           => $payload['driver_id'] ?? null,
            'sales_executive_id'  => $payload['sales_executive_id'] ?? null,
            'empty_weight_truck'  => $payload['empty_weight_truck'] ?? null,
            'loaded_weight_truck' => $payload['loaded_weight_truck'] ?? null,
            'net_weight'          => $payload['net_weight'] ?? null,
            'load_site_id'        => $payload['site_id'] ?? null,
            'empty_time'          => $payload['empty_time'] ?? null,
            'load_time'           => $payload['load_time'] ?? null,
        ], fn ($value) => !is_null($value)));
    }

    if ($emptyPhoto) {
        $this->storeBatchImage($batch, $emptyPhoto, 'empty');
    }

    if ($loadedPhoto) {
        $this->storeBatchImage($batch, $loadedPhoto, 'loaded');
    }
});
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'error' => [$e->getMessage()]
            ]);
        }

        $this->broadcastBatchChange('BatchUpdated', $batch);

        return redirect()->back()->with('success', 'Batch updated successfully.');
    }

    public function destroy(Batch $batch)
    {
        $this->authorizeModule('delete');
        $batch->load('workOrder');
        $this->ensurePlantScope($batch->workOrder);

        $batchId = $batch->id;
        DB::transaction(function () use ($batch) {
            $materials = $batch->materials()->get()->toArray();
            
            if (in_array($batch->status, [Batch::STATUS_DISPATCHED, Batch::STATUS_COMPLETED])) {
                $this->adjustStock($batch, $materials, true);
            }

            $batch->deleted_by = auth()->id();
            $batch->save();

            $batch->materials()->delete();
            
            foreach ($batch->dispatches as $dispatch) {
                $dispatch->deleted_by = auth()->id();
                $dispatch->save();
                $dispatch->delete();
            }

            $batch->delete();
            $batch->workOrder->refreshProduction();
        });

        $this->broadcastBatchDeletion($batchId);

        return redirect()->back()->with('success', 'Batch deleted successfully.');
    }

    public function report(Batch $batch)
    {
        $batch = $this->resolveBatchSheetBatch($batch);
        $sheet = $this->prepareBatchSheetData($batch);

        return view('pdfs.batches.batch_sheet', [
            'batch'     => $batch,
            'sheet'     => $sheet,
            'isPreview' => true,
        ]);
    }

    public function downloadPdf(Batch $batch)
    {
        $batch = $this->resolveBatchSheetBatch($batch);
        $sheet = $this->prepareBatchSheetData($batch);

        $pdf = Pdf::loadView('pdfs.batches.batch_sheet', [
            'batch'     => $batch,
            'sheet'     => $sheet,
            'isPreview' => false,
        ])->setPaper('a4', 'landscape');

        $orderNo = $batch->workOrder?->order_no ?? 'order';
        $safeOrderNo = str_replace(['/', '\\'], '-', $orderNo);
        $filename = sprintf('batch-sheet-%s-%s.pdf', $safeOrderNo, $batch->batch_no ?? $batch->id);

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

        $productIds = collect($materials)->pluck('product_id')->filter()->unique()->toArray();
        $productTitles = !empty($productIds) ? Product::query()->whereIn('id', $productIds)->pluck('title', 'id') : collect();
        $existingMaterials = !empty($existingIds) ? BatchMaterial::query()->whereIn('id', $existingIds)->where('batch_id', $batch->id)->get()->keyBy('id') : collect();

        foreach ($materials as $item) {
            $materialName = $item['material_name'] ?? ($productTitles[$item['product_id']] ?? 'Material');

            $row = [
                'product_id'         => $item['product_id'],
                'material_name'      => $materialName,
                'target_qty'         => $item['target_qty'],
                'actual_qty'         => $item['actual_qty'],
                'deviation_quantity' => $item['deviation_quantity'] ?? 0,
                'uom_id'             => $item['uom_id'],
            ];

            if (!empty($item['id']) && $batchMat = $existingMaterials->get($item['id'])) {
                $batchMat->update($row);
            } else {
                $batch->materials()->create($row);
            }
        }
    }

    private function adjustStock(Batch $batch, array $materials, bool $isReverting = false): void
    {
        $userId = auth()->id();
        $plantId = $batch->workOrder->plant_id ?? session('active_plant_id');

     $aggregated = [];

foreach ($materials as $item) {
    if (
        empty($item['product_id']) ||
        (float)($item['actual_qty'] ?? 0) <= 0
    ) {
        continue;
    }

    $key = $item['product_id'] . '_' . $item['uom_id'];

    if (!isset($aggregated[$key])) {
        $aggregated[$key] = [
            'product_id'   => $item['product_id'],
            'uom_id'       => $item['uom_id'],
            'actual_qty'   => 0,
            'product_name' => $item['product_name'] ?? 'Unknown Product',
        ];
    }

    $aggregated[$key]['actual_qty'] += (float)$item['actual_qty'];
}

$productIds = collect($aggregated)
    ->pluck('product_id')
    ->unique()
    ->values()
    ->toArray();

$quantityRecords = !empty($productIds)
    ? Quantity::query()
        ->where('plant_id', $plantId)
        ->whereIn('product_id', $productIds)
        ->get()
        ->keyBy(fn ($q) => $q->product_id . '_' . $q->uom_id)
    : collect();

foreach ($aggregated as $key => $item) {

    $quantityRecord = $quantityRecords->get($key);

    if (!$quantityRecord && !$isReverting) {
        throw new \Exception(
            "Stock record not found for Product {$item['product_name']}"
        );
    }

    $availableQty = (float)($quantityRecord?->quantity ?? 0);
    $adjustment = (float)$item['actual_qty'];

    if (!$isReverting && $adjustment > $availableQty) {
        throw new \Exception(
            "Insufficient stock  Available: {$availableQty}, Required: {$adjustment}"
        );
    }

    $newQty = $isReverting
        ? $availableQty + $adjustment
        : $availableQty - $adjustment;

    $quantityRecord->quantity = $newQty;
    $quantityRecord->updated_by = $userId;
    $quantityRecord->save();
}
    }

    private function storeBatchImage(Batch $batch, ?string $base64Data, string $type): void
    {
        if (!$base64Data || !str_contains($base64Data, 'base64')) return;

        try {
            if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $typeMatch)) {
                $extension = strtolower($typeMatch[1]);
                $allowedExtensions = ['jpeg', 'png', 'jpg', 'gif', 'svg'];
                
                if (!in_array($extension, $allowedExtensions)) {
                    Log::warning("Blocked suspicious batch image upload with extension: {$extension}");
                    return;
                }

                $data = substr($base64Data, strpos($base64Data, ',') + 1);
                $data = base64_decode($data);
            } else {
                Log::warning("Malformed Base64 payload supplied for Batch Image processing.");
                return;
            }

            $fileName = "batch_{$batch->id}_{$type}_" . time() . ".{$extension}";
            $path = "images/batching/{$fileName}";
            
            Storage::disk('public')->put($path, $data);

            Image::updateOrCreate(
                ['category' => 'Batching', 'ref_no' => (string)$batch->id, 'image_name' => "{$type}_weight_snap"],
                [
                    'alt_txt'    => ucfirst($type) . ' Weight Photo',
                    'image_path' => $path,
                    'plant_id'   => session('active_plant_id'),
                ]
            );
        } catch (\Exception $e) {
            Log::error("Failed to store batch image: " . $e->getMessage());
        }
    }

    private function ensurePlantScope(WorkOrder $workOrder): void
    {
        if ((int) $workOrder->plant_id !== (int) session('active_plant_id')) {
            abort(403, 'You can only manage batches from the active plant.');
        }
    }

    private function broadcastBatchChange(string $event, Batch $batch): void
    {
        try {
            $batch->loadMissing([
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
            ]);

            broadcast(new \App\Events\BatchUpdated($event, ['batch' => $batch->toArray()]));
        } catch (\Exception $e) {
            Log::warning("Batch broadcast failed: " . $e->getMessage());
        }
    }

    private function broadcastBatchDeletion(int $batchId): void
    {
        try {
            broadcast(new \App\Events\BatchUpdated('BatchDeleted', ['id' => $batchId]));
        } catch (\Exception $e) {
            Log::warning("Batch deletion broadcast failed: " . $e->getMessage());
        }
    }
}