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
            'workOrder:id,prefix,order_no,customer_id,mix_design_id,site_id,produced_qty,total_qty',
            'workOrder.customer:id,legal_name',
            'workOrder.mixDesign:id,design_name,design_code',
            'workOrder.site:id,name',
            'dispatches',
            'dispatches.truck:id,registration',
            'dispatches.status:id,dispatch_id,invoice_id,invoice_number,invoice_status,invoice_date',
            'dispatches.status.invoice:id,einvoice_status,eway_bill_no,status,created_at,created_by',
            'dispatches.status.invoice.creator:id,username,email',
            'dispatches.payments'
        ])
        ->whereHas('workOrder', fn ($q) => $q->where('plant_id', $activePlantId))
        ->latest()
        ->get(); 

        $batches->each(function ($batch) {
            $batch->makeHidden(['created_at', 'created_by', 'updated_at', 'updated_by', 'deleted_by', 'deleted_at']);
            if ($batch->workOrder) {
                $batch->workOrder->makeHidden(['created_at', 'created_by', 'updated_at', 'updated_by', 'deleted_by', 'deleted_at']);
                if ($batch->workOrder->mixDesign) {
                    $batch->workOrder->mixDesign->makeHidden([
                        'created_at', 'created_by', 'updated_at', 'updated_by', 'deleted_by', 'deleted_at',
                        'is_used_in_quotations', 'is_used_in_batching'
                    ]);
                }
                if ($batch->workOrder->customer) {
                    $batch->workOrder->customer->makeHidden([
                        'created_at', 'created_by', 'updated_at', 'updated_by', 'deleted_by', 'deleted_at',
                        'is_in_use'
                    ]);
                }
                if ($batch->workOrder->site) {
                    $batch->workOrder->site->makeHidden([
                        'created_at', 'created_by', 'updated_at', 'updated_by', 'deleted_by', 'deleted_at',
                        'is_in_use'
                    ]);
                }
            }
            $batch->dispatches->each(function ($dispatch) {
                $dispatch->makeHidden(['created_at', 'created_by', 'updated_at', 'updated_by', 'deleted_by', 'deleted_at']);
                if ($dispatch->truck) {
                    $dispatch->truck->makeHidden([
                        'created_at', 'created_by', 'updated_at', 'updated_by', 'deleted_by', 'deleted_at',
                        'can_delete', 'can_update', 'is_in_use'
                    ]);
                }
            });
        });

        $workOrders = WorkOrder::query()
            ->with([
                'customer:id,plant_id,legal_name,code,patron_type,gstin,email,mobile',
                'site:id,plant_id,name,site_address_1,type',
                'mixDesign:id,plant_id,partner_id,concrete_grade_id,design_name,design_code,design_type,unit_id,rate_per_qty',
                'mixDesign.items:id,plant_id,mix_design_id,product_id,uom_id,rate,actual_quantity,cross_quantity,variation_quantity',
                'mixDesign.items.product:id,plant_id,category_id,unit_id,is_service,purchase_tax_id,sale_tax_id,purchase_price,sales_price,title,material_code,product_type,conversion_quantity,code,hsn_code',
                'mixDesign.items.uom:id,unit_code',
                'mixDesign.concrete_grade:id,name,concrete_ratio'
            ])
            ->withCount('batches')
            ->where('plant_id', $activePlantId)
            ->whereIn('status', [WorkOrder::STATUS_IN_PROGRESS])
             ->where(function ($query) {
        $query->whereNull('scheduled_end')
              ->orWhere('scheduled_end', '>', now());              // to display the workorders which are active as per scheduled end date
    })
            ->orderBy('order_no')
            ->get();

        $workOrders->each(function ($wo) {
            $wo->makeHidden(['created_at', 'created_by', 'updated_at', 'updated_by', 'deleted_by', 'deleted_at']);
            
            if ($wo->mixDesign) {
                $wo->mixDesign->makeHidden([
                    'created_at', 'created_by', 'updated_at', 'updated_by', 'deleted_by', 'deleted_at',
                    'is_used_in_quotations', 'is_used_in_batching'
                ]);
                if ($wo->mixDesign->concreteGrade) {
                    $wo->mixDesign->concreteGrade->makeHidden(['created_at', 'created_by', 'updated_at', 'updated_by', 'deleted_by', 'deleted_at']);
                }
                $wo->mixDesign->items->each(function ($item) {
                    $item->makeHidden([
                        'created_at', 'created_by', 'updated_at', 'updated_by', 'deleted_by', 'deleted_at',
                        'used_in_batching'
                    ]);
                    if ($item->product) {
                        $item->product->makeHidden([
                            'created_at', 'created_by', 'updated_at', 'updated_by', 'deleted_by', 'deleted_at',
                            'can_delete', 'can_update', 'is_in_use'
                        ]);
                    }
                    if ($item->uom) {
                        $item->uom->makeHidden(['created_at', 'created_by', 'updated_at', 'updated_by', 'deleted_by', 'deleted_at']);
                    }
                });
            }

            if ($wo->customer) {
                $wo->customer->makeHidden([
                    'created_at', 'created_by', 'updated_at', 'updated_by', 'deleted_by', 'deleted_at',
                    'is_in_use'
                ]);
            }

            if ($wo->site) {
                $wo->site->makeHidden([
                    'created_at', 'created_by', 'updated_at', 'updated_by', 'deleted_by', 'deleted_at',
                    'is_in_use'
                ]);
            }
        });

        $now = now();
        $startYear = $now->month >= 4 ? $now->year : $now->year - 1;
        $fyStart = Carbon::create($startYear, 4, 1, 0, 0, 0);

        $nextBatchNo = Batch::query()
            ->whereHas('workOrder', fn ($q) => $q->where('plant_id', $activePlantId))
            ->where('created_at', '>=', $fyStart)
            ->max('batch_no') + 1;

            // return response()->json([
            //     'batches' => $batches,
            //     'workOrders' => $workOrders,
            // ]);
      
        return Inertia::render('Batches/Index', [
            'batches'           => $batches,
            'workOrders'        => $workOrders,
            'trucks'            => MachinesDropdown(),
            'customers'         => PatronsDropdown('Customer'),
            'transporters'      => PatronsDropdown('Transporter'),
            'loading_sites'     => SitesDropdown('loading'),
            'unloading_sites'   => SitesDropdown('unloading'),
            'drivers'            => PersonnelDropdown('','Driver'),
            'sales_executives'   => PersonnelDropdown('','Sales Executive'),
            'taxes'             => TaxesDropdown('sales'),
            'products'          => fn () => ProductsDropdown(),
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
                    $plant = Plant::where('id','=',session('active_plant_id'))->first();
                    if ($plant) {
                        $shiftInfo = $plant->getCurrentShiftInfo($payload['start_time'] ?? null);
                        $payload['shift'] = $shiftInfo['shift'];
                    }
                }

                $batch = Batch::create($payload);
                $this->syncMaterials($batch, $materials);
                
                if (in_array($batch->status, [Batch::STATUS_DISPATCHED, Batch::STATUS_COMPLETED])) {
                    $this->checkStock($batch, $materials);
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
                    'uom_id'              => $batch->uom_id ?? null,
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
        // $this->pushToSchedulerAPI($batch, $materialsData);
        // $this->broadcastBatchChange('BatchCreated', $batch);

        return redirect()->route('batches.token', $batch->id);
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
        // Log::info($request->all());
        $this->authorizeModule('menu');
        $batch->unsetRelation('workOrder');
        $batch->unsetRelation('dispatches');

        $batch->load([
            'workOrder.customer',
            'workOrder.mixDesign.items.product',
            'workOrder.mixDesign.items.uom',
            'workOrder.mixDesign.concrete_grade',
            'workOrder.site',
            'materials',
            'materials.product:id,title', 
            'materials.uom:id,unit_name,unit_code',
            'dispatches.status.invoice.creator:id,username,email',
            'dispatches.payments',
            'dispatches.truck',
            'dispatches.driver',
            'dispatches.salesExecutive',
            'dispatches.creator:id,email',
            'dispatches.modifier:id,email'
        ]);

        if ($batch->workOrder?->mixDesign) {
            $batch->workOrder->mixDesign->makeHidden(['is_used_in_quotations', 'is_used_in_batching']);
        }

        $batch->workOrder?->mixDesign?->items->each(function ($item) {
            $item->makeHidden(['used_in_batching']);
            $item->product?->makeHidden(['can_delete', 'can_update', 'is_in_use']);
        });

        $batch->materials->each(function ($material) {
            $material->product?->makeHidden(['can_delete', 'can_update', 'is_in_use']);
        });
        dd($batch);
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
// dd($materials);
            // Check stock first before proceeding with saving and new consumption
            $newStatus = $payload['status'] ?? $batch->status;
            $hasActual = collect($materials)->contains(fn($m) => (float)($m['actual_qty'] ?? 0) > 0);
            if ($hasActual && $newStatus == Batch::STATUS_PLANNED) {
                $newStatus = Batch::STATUS_DISPATCHED;
            }

            if (in_array($newStatus, [Batch::STATUS_DISPATCHED, Batch::STATUS_COMPLETED])) {
                $wasDeducted = in_array($oldStatus, [Batch::STATUS_DISPATCHED, Batch::STATUS_COMPLETED]);
                $this->checkStock($batch, $materials, $oldMaterials, $wasDeducted);
            }

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

            if ($batch->status == Batch::STATUS_DISPATCHED && $oldStatus != Batch::STATUS_DISPATCHED) {
                session()->flash('dispatched_batch_id', $batch->id);
            }

            // Update associated dispatch if it exists
            $dispatch = $batch->dispatches()->first();
            if ($dispatch) {
                $dispatch->update([
                    'truck_id' => array_key_exists('truck_id', $payload) ? $payload['truck_id'] : $dispatch->truck_id,
                    'transport_id' => array_key_exists('transport_id', $payload) ? $payload['transport_id'] : $dispatch->transport_id,
                    'driver_id' => array_key_exists('driver_id', $payload) ? $payload['driver_id'] : $dispatch->driver_id,
                    'sales_executive_id' => array_key_exists('sales_executive_id', $payload) ? $payload['sales_executive_id'] : $dispatch->sales_executive_id,
                    'empty_weight_truck' => array_key_exists('empty_weight_truck', $payload) ? $payload['empty_weight_truck'] : $dispatch->empty_weight_truck,
                    'loaded_weight_truck' => array_key_exists('loaded_weight_truck', $payload) ? $payload['loaded_weight_truck'] : $dispatch->loaded_weight_truck,
                    'net_weight' => array_key_exists('net_weight', $payload) ? $payload['net_weight'] : $dispatch->net_weight,
                    'load_site_id' => array_key_exists('site_id', $payload) ? $payload['site_id'] : $dispatch->load_site_id,
                    'empty_time' => array_key_exists('empty_time', $payload) ? $payload['empty_time'] : $dispatch->empty_time,
                    'load_time' => array_key_exists('load_time', $payload) ? $payload['load_time'] : $dispatch->load_time,
                ]);
            }

            if ($emptyPhoto) $this->storeBatchImage($batch, $emptyPhoto, 'empty');
            if ($loadedPhoto) $this->storeBatchImage($batch, $loadedPhoto, 'loaded');
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

        $this->broadcastBatchDeletion($batchId);

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

    public function token(Batch $batch)
    {
        $batch->load([
            'workOrder:id,prefix,plant_id,customer_id,mix_design_id,site_id,order_no',
            'workOrder.customer:id,legal_name',
            'workOrder.site:id,name',
            'workOrder.plant:id,entity_id,name,logo_path',
            'workOrder.plant.entity:id,legal_name',
            'workOrder.plant.addresses',
            'workOrder.mixDesign:id,design_name,design_code,design_type',
            'workOrder.mixDesign.concrete_grade:id,name',
            'dispatches:id,batch_id,truck_id,driver_id,transport_id,load_site_id,sales_executive_id,empty_weight_truck,empty_time',
            'dispatches.truck:id,registration',
            'dispatches.driver:id,first_name,last_name',
            'dispatches.salesExecutive:id,first_name,last_name',
            'dispatches.transport:id,legal_name',
            'dispatches.loadSite:id,name',
            'materials:id,batch_id,product_id,material_name,target_qty,uom_id',
            'materials.product:id,title',
            'materials.uom:id,unit_code',
            'operator:id,first_name,last_name'
        ]);

        $this->ensurePlantScope($batch->workOrder);

        $settings = \App\Models\CustomSetting::getForModule($batch->workOrder->plant_id, 'batching');

        return view('pdfs.batches.batching_token', [
            'batch' => $batch,
            'isPreview' => true,
            'settings' => $settings,
        ]);
    }

    public function downloadTokenPdf(Batch $batch)
    {
        $batch->load([
            'workOrder:id,prefix,plant_id,customer_id,mix_design_id,site_id,order_no',
            'workOrder.customer:id,legal_name',
            'workOrder.site:id,name',
            'workOrder.plant:id,entity_id,name,logo_path',
            'workOrder.plant.entity:id,legal_name',
            'workOrder.plant.addresses',
            'workOrder.mixDesign:id,design_name,design_code,design_type',
            'workOrder.mixDesign.concrete_grade:id,name',
            'dispatches:id,batch_id,truck_id,driver_id,transport_id,load_site_id,sales_executive_id,empty_weight_truck,empty_time',
            'dispatches.truck:id,registration',
            'dispatches.driver:id,first_name,last_name',
            'dispatches.salesExecutive:id,first_name,last_name',
            'dispatches.transport:id,legal_name',
            'dispatches.loadSite:id,name',
            'materials:id,batch_id,product_id,material_name,target_qty,uom_id',
            'materials.product:id,title',
            'materials.uom:id,unit_code',
            'operator:id,first_name,last_name'
        ]);

        $this->ensurePlantScope($batch->workOrder);

        $settings = \App\Models\CustomSetting::getForModule($batch->workOrder->plant_id, 'batching');

        // 80mm width = 226.77pt. DomPDF uses points (72pt = 1in). Custom portrait ticket size.
        $materialsCount = $batch->materials->count();
        $height = 320 + ($materialsCount * 15);

        $pdf = Pdf::loadView('pdfs.batches.batching_token', [
            'batch' => $batch,
            'isPreview' => false,
            'settings' => $settings,
        ])->setPaper([0, 0, 226.77, $height], 'portrait');

        $filename = sprintf('batch-token-%s.pdf', $batch->batch_no ?? $batch->id);
        return $pdf->download($filename);
    }

    public function dispatchToken(Batch $batch)
    {
        $batch->load([
            'workOrder:id,prefix,plant_id,customer_id,mix_design_id,site_id,order_no',
            'workOrder.customer:id,legal_name',
            'workOrder.site:id,name',
            'workOrder.plant:id,entity_id,name,logo_path',
            'workOrder.plant.entity:id,legal_name',
            'workOrder.plant.addresses',
            'workOrder.mixDesign:id,design_name,design_code,design_type',
            'workOrder.mixDesign.concrete_grade:id,name',
            'dispatches:id,batch_id,truck_id,driver_id,transport_id,load_site_id,sales_executive_id,empty_weight_truck,empty_time,loaded_weight_truck,load_time,net_weight',
            'dispatches.truck:id,registration',
            'dispatches.driver:id,first_name,last_name',
            'dispatches.salesExecutive:id,first_name,last_name',
            'dispatches.transport:id,legal_name',
            'dispatches.loadSite:id,name',
            'materials:id,batch_id,product_id,material_name,target_qty,actual_qty,deviation_quantity,uom_id',
            'materials.product:id,title',
            'materials.uom:id,unit_code',
            'operator:id,first_name,last_name'
        ]);

        $this->ensurePlantScope($batch->workOrder);

        $settings = \App\Models\CustomSetting::getForModule($batch->workOrder->plant_id, 'batching');

        return view('pdfs.batches.dispatch_token', [
            'batch' => $batch,
            'isPreview' => true,
            'settings' => $settings,
        ]);
    }

    public function downloadDispatchTokenPdf(Batch $batch)
    {
        $batch->load([
            'workOrder:id,prefix,plant_id,customer_id,mix_design_id,site_id,order_no',
            'workOrder.customer:id,legal_name',
            'workOrder.site:id,name',
            'workOrder.plant:id,entity_id,name,logo_path',
            'workOrder.plant.entity:id,legal_name',
            'workOrder.plant.addresses',
            'workOrder.mixDesign:id,design_name,design_code,design_type',
            'workOrder.mixDesign.concrete_grade:id,name',
            'dispatches:id,batch_id,truck_id,driver_id,transport_id,load_site_id,sales_executive_id,empty_weight_truck,empty_time,loaded_weight_truck,load_time,net_weight',
            'dispatches.truck:id,registration',
            'dispatches.driver:id,first_name,last_name',
            'dispatches.salesExecutive:id,first_name,last_name',
            'dispatches.transport:id,legal_name',
            'dispatches.loadSite:id,name',
            'materials:id,batch_id,product_id,material_name,target_qty,actual_qty,deviation_quantity,uom_id',
            'materials.product:id,title',
            'materials.uom:id,unit_code',
            'operator:id,first_name,last_name'
        ]);

        $this->ensurePlantScope($batch->workOrder);

        $settings = \App\Models\CustomSetting::getForModule($batch->workOrder->plant_id, 'batching');

        $materialsCount = $batch->materials->count();
        $height = 360 + ($materialsCount * 18);

        $pdf = Pdf::loadView('pdfs.batches.dispatch_token', [
            'batch' => $batch,
            'isPreview' => false,
            'settings' => $settings,
        ])->setPaper([0, 0, 226.77, $height], 'portrait');

        $filename = sprintf('dispatch-token-%s.pdf', $batch->batch_no ?? $batch->id);
        return $pdf->download($filename);
    }

    public function deliveryToken(Batch $batch)
    {
        $batch->load([
            'workOrder:id,prefix,plant_id,customer_id,mix_design_id,site_id,order_no',
            'workOrder.customer:id,legal_name',
            'workOrder.site:id,name',
            'workOrder.plant:id,entity_id,name,logo_path',
            'workOrder.plant.entity:id,legal_name',
            'workOrder.plant.addresses',
            'workOrder.mixDesign:id,design_name,design_code,design_type',
            'workOrder.mixDesign.concrete_grade:id,name',
            'dispatches:id,batch_id,truck_id,driver_id,transport_id,load_site_id,sales_executive_id,empty_weight_truck,empty_time,loaded_weight_truck,load_time,net_weight',
            'dispatches.truck:id,registration',
            'dispatches.driver:id,first_name,last_name',
            'dispatches.salesExecutive:id,first_name,last_name',
            'dispatches.transport:id,legal_name',
            'dispatches.loadSite:id,name',
            'materials:id,batch_id,product_id,material_name,target_qty,actual_qty,deviation_quantity,uom_id',
            'materials.product:id,title',
            'materials.uom:id,unit_code',
            'operator:id,first_name,last_name'
        ]);

        $this->ensurePlantScope($batch->workOrder);

        $templateKey = \App\Services\PrintDataFormatter::resolveTemplateKey('delivery_challans', $batch->workOrder->plant_id);
        $view = \App\Services\PrintDataFormatter::resolveView($templateKey);

        if ($templateKey === 'delivery_challan_a4') {
            $settings = \App\Models\CustomSetting::getForModule($batch->workOrder->plant_id, 'batching');
            return view($view, [
                'batch' => $batch,
                'isPreview' => true,
                'settings' => $settings,
            ]);
        }

        $data = \App\Services\PrintDataFormatter::fromDeliveryChallan($batch);
        return view($view, ['data' => $data]);
    }

    public function downloadDeliveryTokenPdf(Batch $batch)
    {
        $batch->load([
            'workOrder:id,prefix,plant_id,customer_id,mix_design_id,site_id,order_no',
            'workOrder.customer:id,legal_name',
            'workOrder.site:id,name',
            'workOrder.plant:id,entity_id,name,logo_path',
            'workOrder.plant.entity:id,legal_name',
            'workOrder.plant.addresses',
            'workOrder.mixDesign:id,design_name,design_code,design_type',
            'workOrder.mixDesign.concrete_grade:id,name',
            'dispatches:id,batch_id,truck_id,driver_id,transport_id,load_site_id,sales_executive_id,empty_weight_truck,empty_time,loaded_weight_truck,load_time,net_weight',
            'dispatches.truck:id,registration',
            'dispatches.driver:id,first_name,last_name',
            'dispatches.salesExecutive:id,first_name,last_name',
            'dispatches.transport:id,legal_name',
            'dispatches.loadSite:id,name',
            'materials:id,batch_id,product_id,material_name,target_qty,actual_qty,deviation_quantity,uom_id',
            'materials.product:id,title',
            'materials.uom:id,unit_code',
            'operator:id,first_name,last_name'
        ]);

        $this->ensurePlantScope($batch->workOrder);

        $templateKey = \App\Services\PrintDataFormatter::resolveTemplateKey('delivery_challans', $batch->workOrder->plant_id);
        $view = \App\Services\PrintDataFormatter::resolveView($templateKey);

        if ($templateKey === 'delivery_challan_a4') {
            $settings = \App\Models\CustomSetting::getForModule($batch->workOrder->plant_id, 'batching');
            $pdf = Pdf::loadView($view, [
                'batch' => $batch,
                'isPreview' => false,
                'settings' => $settings,
            ])->setPaper('a4', 'portrait');
        } else {
            $data = \App\Services\PrintDataFormatter::fromDeliveryChallan($batch);
            $pdf = Pdf::loadView($view, ['data' => $data])->setPaper('a4', 'portrait');
        }

        $filename = sprintf('delivery-token-%s.pdf', $batch->batch_no ?? $batch->id);
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
        $plantId = $batch->plant_id ?? session('active_plant_id');
        $incomingProductIds = collect($materials)->pluck('product_id')->filter()->unique()->toArray();
        
        // Delete materials that are no longer in the payload
        $batch->materials()->whereNotIn('product_id', $incomingProductIds)->delete();

        $productTitles = !empty($incomingProductIds) 
            ? Product::query()->whereIn('id', $incomingProductIds)->pluck('title', 'id') 
            : collect();

        foreach ($materials as $item) {
            if (empty($item['product_id'])) continue;
            
            $materialName = $item['material_name'] ?? ($productTitles[$item['product_id']] ?? 'Material');

            $batch->materials()->updateOrCreate(
                [
                    'batch_id' => $batch->id,
                    'product_id' => $item['product_id'],
                ],
                [
                    'plant_id' => $plantId,
                    'material_name' => $materialName,
                    'target_qty' => $item['target_qty'],
                    'actual_qty' => $item['actual_qty'],
                    'deviation_quantity' => $item['deviation_quantity'] ?? 0,
                    'uom_id' => $item['uom_id'],
                ]
            );
        }
    }

    private function checkStock(Batch $batch, array $newMaterials, array $oldMaterials = [], bool $wasDeducted = false): void
    {
        $plantId = $batch->workOrder->plant_id ?? session('active_plant_id');

        // 1. Aggregate new quantities required (grouped by product_id and uom_id)
        $newAggregated = [];
        foreach ($newMaterials as $item) {
            if (empty($item['product_id']) || (float)($item['actual_qty'] ?? 0) <= 0) continue;
            $uomId = $item['uom_id'] ?? null;
            $key = $item['product_id'] . '_' . $uomId;
            if (!isset($newAggregated[$key])) {
                $newAggregated[$key] = [
                    'product_id' => $item['product_id'],
                    'uom_id' => $uomId,
                    'actual_qty' => 0
                ];
            }
            $newAggregated[$key]['actual_qty'] += (float)$item['actual_qty'];
        }

        if (empty($newAggregated)) {
            return;
        }

        // 2. Aggregate old quantities that were deducted (grouped by product_id and uom_id)
        $oldAggregated = [];
        if ($wasDeducted) {
            foreach ($oldMaterials as $item) {
                if (empty($item['product_id']) || (float)($item['actual_qty'] ?? 0) <= 0) continue;
                $uomId = $item['uom_id'] ?? null;
                $key = $item['product_id'] . '_' . $uomId;
                if (!isset($oldAggregated[$key])) {
                    $oldAggregated[$key] = 0;
                }
                $oldAggregated[$key] += (float)$item['actual_qty'];
            }
        }

        // 3. Fetch current stock levels from the database for the required products
        $productIds = collect($newAggregated)->pluck('product_id')->toArray();
        $quantityRecords = !empty($productIds) ? Quantity::query()->where('plant_id', $plantId)
            ->whereIn('product_id', $productIds)
            ->get()
            ->keyBy(function ($q) {
                return $q->product_id . '_' . $q->uom_id;
            }) : collect();

        // 4. Validate for each product
        $errors = [];
        foreach ($newAggregated as $key => $item) {
            $quantityRecord = $quantityRecords->get($key);
            $currentStock = $quantityRecord ? (float)$quantityRecord->quantity : 0.0;
            
            // Add back the old quantity if it was previously deducted, because it will be reverted
            $oldQty = $oldAggregated[$key] ?? 0.0;
            $availableStock = $currentStock + $oldQty;
            
            $requiredQty = (float)$item['actual_qty'];

            if ($availableStock < $requiredQty) {
                $productName = '';
                if ($quantityRecord && $quantityRecord->relationLoaded('product') && $quantityRecord->product) {
                    $productName = $quantityRecord->product->title;
                } else {
                    $product = Product::find($item['product_id']);
                    $productName = $product ? $product->title : "Product #{$item['product_id']}";
                }
                $errors[] = "Insufficient stock for '{$productName}'. Available: " . number_format($availableStock, 2) . ", Required: " . number_format($requiredQty, 2);
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages([
                'materials' => $errors
            ]);
        }
    }

    private function adjustStock(Batch $batch, array $materials, bool $isReverting = false): void
    {
        $userId = auth()->id();
        $date = $batch->created_at ? $batch->created_at->toDateString() : now()->toDateString();
        $plantId = $batch->workOrder->plant_id ?? session('active_plant_id');

        // Aggregate adjustments by product and uom to reduce time complexity and DB operations
        $aggregated = [];
        foreach ($materials as $item) {
            if (empty($item['product_id']) || (float)($item['actual_qty'] ?? 0) <= 0) continue;
            $key = $item['product_id'] . '_' . $item['uom_id'];
            if (!isset($aggregated[$key])) {
                $aggregated[$key] = [
                    'product_id' => $item['product_id'],
                    'uom_id' => $item['uom_id'],
                    'actual_qty' => 0
                ];
            }
            $aggregated[$key]['actual_qty'] += (float)$item['actual_qty'];
        }

        $productIds = collect($aggregated)->pluck('product_id')->toArray();
        
        // Use query() to prevent IDE 'Not enough arguments' warning on where()
        $quantityRecords = !empty($productIds) ? Quantity::query()->where('plant_id', $plantId)
            ->whereIn('product_id', $productIds)
            ->get()
            ->keyBy(function ($q) {
                return $q->product_id . '_' . $q->uom_id;
            }) : collect();

        foreach ($aggregated as $key => $item) {
            $quantityRecord = $quantityRecords->get($key);
            
            if (!$quantityRecord) {
                $quantityRecord = new Quantity([
                    'plant_id' => $plantId,
                    'product_id' => $item['product_id'],
                    'uom_id' => $item['uom_id']
                ]);
                $quantityRecord->opening_quantity = 0;
                $quantityRecord->created_by = $userId;
                $quantityRecord->status = 1;
                $quantityRecord->quantity = 0;
            }

            $adjustment = (float)$item['actual_qty'];
            
            if ($isReverting) {
                $quantityRecord->quantity += $adjustment;
            } else {
                $quantityRecord->quantity -= $adjustment;
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

    /**
     * Broadcast batch updates to WebSocket clients.
     */
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
            \Illuminate\Support\Facades\Log::warning("Batch broadcast failed: " . $e->getMessage());
        }
    }

    /**
     * Broadcast batch deletion to WebSocket clients.
     */
    private function broadcastBatchDeletion(int $batchId): void
    {
        try {
            broadcast(new \App\Events\BatchUpdated('BatchDeleted', ['id' => $batchId]));
        } catch (\Exception $e) {
            Log::warning("Batch deletion broadcast failed: " . $e->getMessage());
        }
    }
}