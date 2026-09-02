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
use App\Models\SalesOrder;
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

    protected string $module = 'batches';

    public function index()
    {
        $this->authorizeModule('menu');
        $activePlantId = session('active_plant_id');

        $batches = DB::table('mm_batches as b')
            ->join('mm_sales_orders as so', 'so.id', '=', 'b.sales_order_id')
            ->leftJoin('mm_dispatches as d', function ($join) {
                $join->on('d.batch_id', '=', 'b.id')
                     ->whereNull('d.deleted_at');
            })
            ->leftJoin('mm_patrons as dp', 'dp.id', '=', 'd.customer_id')
            ->leftJoin('mm_sites as ds_site', 'ds_site.id', '=', 'd.unload_site_id')
            ->leftJoin('mm_mix_designs as dm', 'dm.id', '=', 'd.mixdesign_id')
            ->leftJoin('mm_machines as t', 't.id', '=', 'd.truck_id')
            ->leftJoin('mm_patrons as p', 'p.id', '=', 'so.customer_id')
            ->leftJoin('mm_sites as s', 's.id', '=', 'so.site_id')
            ->leftJoin('mm_mix_designs as m', 'm.id', '=', 'so.mix_design_id')
            ->leftJoin('mm_dispatch_statuses as ds', 'ds.dispatch_id', '=', 'd.id')
            ->leftJoin('mm_invoices as inv', 'inv.id', '=', 'ds.invoice_id')
            ->leftJoin('mm_einvoice_invoice_rel as einv_rel', 'einv_rel.invoice_id', '=', 'inv.id')
            ->leftJoin('mm_ewaybill_details as ewb', function ($join) {
                $join->on('ewb.origin_id', '=', 'inv.id')
                     ->where('ewb.generation_type', '=', 'invoice');
            })
            ->where('so.plant_id', $activePlantId)
            ->whereNull('b.deleted_at')
            ->whereNull('so.deleted_at')
            ->select([
                'b.id',
                'b.batch_no',
                'b.batch_size',
                'b.status',
                'b.start_time',
                'b.end_time',
                'b.is_verified',
                'b.sync_status',
                'b.created_at',
                'b.sales_order_id',
                'so.prefix as so_prefix',
                'so.order_no as so_order_no',
                'so.rate as so_rate',
                'so.tax_id as so_tax_id',
                'so.is_tax_inclusive as so_is_tax_inclusive',
                'so.concrete_pump as so_concrete_pump',
                'so.pump_rate as so_pump_rate',
                DB::raw('COALESCE(d.customer_id, so.customer_id) as customer_id'),
                DB::raw('COALESCE(dp.legal_name, p.legal_name) as customer_name'),
                DB::raw('COALESCE(d.unload_site_id, so.site_id) as site_id'),
                DB::raw('COALESCE(ds_site.name, s.name) as site_name'),
                DB::raw('COALESCE(d.mixdesign_id, so.mix_design_id) as mix_design_id'),
                DB::raw('COALESCE(dm.design_name, m.design_name) as mix_design_name'),
                DB::raw('COALESCE(dm.design_code, m.design_code) as mix_design_code'),
                'd.id as dispatch_id',
                'd.truck_id as dispatch_truck_id',
                'd.delivered_qty as dispatch_delivered_qty',
                't.registration as truck_registration',
                'ds.id as dispatch_status_id',
                'ds.invoice_id',
                'd.load_total_amount',
                'ds.is_tax_inclusive as dispatch_is_tax_inclusive',
                'inv.prefix as invoice_prefix',
                'inv.invoice_number',
                'inv.status as invoice_status',
                'inv.is_duplicate as invoice_is_duplicate',
                'einv_rel.einv_irn as einvoice_irn',
                'einv_rel.einv_status as einvoice_status',
                'einv_rel.einv_ackno as einvoice_ack_no',
                'ewb.ewaybill_no as eway_bill_no',
            ])
            ->orderByDesc('b.created_at')
            ->get()
            ->map(function ($row) {
                $fullInvoiceNumber = ($row->invoice_prefix ?? '') . ($row->invoice_number ?? '');
                return [
                    'id' => $row->id,
                    'encrypted_id' => encrypt($row->id),
                    'batch_no' => $row->batch_no,
                    'batch_size' => $row->batch_size,
                    'status' => $row->status,
                    'start_time' => $row->start_time,
                    'end_time' => $row->end_time,
                    'is_verified' => (bool)$row->is_verified,
                    'sync_status' => $row->sync_status,
                    'load_total_amount' => $row->load_total_amount,
                    'created_at' => $row->created_at,
                    'sales_order_id' => $row->sales_order_id,
                    'customer_id' => $row->customer_id,
                    'customer_name' => $row->customer_name,
                    'site_id' => $row->site_id,
                    'site_name' => $row->site_name,
                    'mix_design_id' => $row->mix_design_id,
                    'mix_design_name' => $row->mix_design_name,
                    'mix_design_code' => $row->mix_design_code,
                    'truck_registration' => $row->truck_registration,
                    'rate' => (float)$row->so_rate,
                    'tax_id' => $row->so_tax_id,
                    'is_tax_inclusive' => (bool)$row->so_is_tax_inclusive,
                    'invoice_id' => $row->invoice_id,
                    'invoice_number' => $fullInvoiceNumber,
                    'invoice_prefix' => $row->invoice_prefix,
                    'has_invoice' => !empty($row->invoice_id),
                    'einvoice_irn' => $row->einvoice_irn,
                    'einvoice_status' => $row->einvoice_status,
                    'has_einvoice' => !empty($row->einvoice_irn) || $row->einvoice_status === 'generated',
                    'sales_order' => [
                        'id' => $row->sales_order_id,
                        'prefix' => $row->so_prefix,
                        'order_no' => $row->so_order_no,
                        'full_number' => ($row->so_prefix ?? '') . ($row->so_order_no ?? ''),
                        'rate' => (float)$row->so_rate,
                        'tax_id' => $row->so_tax_id,
                        'is_tax_inclusive' => (bool)$row->so_is_tax_inclusive,
                        'concrete_pump' => $row->so_concrete_pump,
                        'pump_rate' => (float)$row->so_pump_rate,
                        'customer' => [
                            'id' => $row->customer_id,
                            'legal_name' => $row->customer_name,
                        ],
                        'site' => [
                            'id' => $row->site_id,
                            'name' => $row->site_name,
                        ],
                        'mix_design' => [
                            'id' => $row->mix_design_id,
                            'design_name' => $row->mix_design_name,
                        ],
                    ],
                    'dispatches' => $row->dispatch_id ? [
                        [
                            'id' => $row->dispatch_id,
                            'batch_id' => $row->id,
                            'truck_id' => $row->dispatch_truck_id,
                            'delivered_qty' => $row->dispatch_delivered_qty,
                            'truck' => [
                                'id' => $row->dispatch_truck_id,
                                'registration' => $row->truck_registration,
                            ],
                            'customer' => [
                                'id' => $row->customer_id,
                                'legal_name' => $row->customer_name,
                            ],
                            'site' => [
                                'id' => $row->site_id,
                                'name' => $row->site_name,
                            ],
                            'mix_design' => [
                                'id' => $row->mix_design_id,
                                'design_name' => $row->mix_design_name,
                                'design_code' => $row->mix_design_code,
                            ],
                            'status' => [
                                'id' => $row->dispatch_status_id,
                                'invoice_id' => $row->invoice_id,
                                'invoice_status' => $row->invoice_id ? 1 : 0,
                                'invoice_number' => $fullInvoiceNumber,
                                'is_tax_inclusive' => $row->dispatch_is_tax_inclusive !== null ? (bool)$row->dispatch_is_tax_inclusive : (bool)$row->so_is_tax_inclusive,
                                'invoice' => $row->invoice_id ? [
                                    'id' => $row->invoice_id,
                                    'encrypted_id' => encrypt($row->invoice_id),
                                    'invoice_number' => $fullInvoiceNumber,
                                    'invoice_prefix' => $row->invoice_prefix,
                                    'full_number' => $fullInvoiceNumber,
                                    'status' => $row->invoice_status,
                                    'is_duplicate' => (int)($row->invoice_is_duplicate ?? 0),
                                    'einvoice_irn' => $row->einvoice_irn,
                                    'einvoice_status' => $row->einvoice_status,
                                ] : null,
                            ],
                        ]
                    ] : [],
                ];
            }); 

        $batchingSettings = CustomSetting::getForModule($activePlantId, 'batching');
        $autoCarryPump = !empty($batchingSettings['auto_carry_pump']);

        $latestDispatches = DB::table('mm_dispatches as d')
            ->select('d.sales_order_id', 'd.concrete_pump', 'd.truck_id', 'd.driver_id', 'd.transport_id', 'd.sales_executive_id', 'd.empty_weight_truck', 'd.loaded_weight_truck', 'd.net_weight')
            ->whereIn('d.id', function ($query) use ($activePlantId) {
                $query->select(DB::raw('MAX(id)'))
                    ->from('mm_dispatches')
                    ->where('plant_id', $activePlantId)
                    ->whereNull('deleted_at')
                    ->groupBy('sales_order_id');
            })
            ->get()
            ->keyBy('sales_order_id');

        $latestDispatchesWithPump = collect();
        if ($autoCarryPump) {
            $latestDispatchesWithPump = DB::table('mm_dispatches as d')
                ->select('d.sales_order_id', 'd.concrete_pump')
                ->whereIn('d.id', function ($query) use ($activePlantId) {
                    $query->select(DB::raw('MAX(id)'))
                        ->from('mm_dispatches')
                        ->where('plant_id', $activePlantId)
                        ->whereNotNull('concrete_pump')
                        ->whereNull('deleted_at')
                        ->groupBy('sales_order_id');
                })
                ->get()
                ->keyBy('sales_order_id');
        }

        $salesOrders = DB::table('mm_sales_orders as so')
                ->leftJoin('mm_patrons as p', 'p.id', '=', 'so.customer_id')
                ->leftJoin('mm_sites as s', 's.id', '=', 'so.site_id')
                ->leftJoin('mm_mix_designs as m', 'm.id', '=', 'so.mix_design_id')
                ->leftJoin('mm_customer_pos as cpo', 'cpo.id', '=', 'so.customer_po_id')
                ->leftJoin('mm_quotations as q', 'q.id', '=', 'cpo.quotation_id')
                ->select([
                    'so.id',
                    DB::raw("CONCAT(so.prefix, so.order_no) as full_number"),
                    'so.produced_qty',
                    'p.legal_name as customer_name',
                    's.name as site_name',
                    'm.design_name as mix_design_name',
                    'so.total_qty',
                    'so.is_tax_inclusive',
                    'so.concrete_pump',
                    'cpo.concrete_pump as cpo_concrete_pump',
                    'q.concrete_pump as q_concrete_pump',
                    'so.sales_executive_id',
                ])
                ->where('so.plant_id', $activePlantId)
                ->whereNull('so.deleted_at')
                ->where('so.status', SalesOrder::STATUS_IN_PROGRESS)
                ->orderBy('so.order_no')
                ->get()
                ->map(function ($so) use ($latestDispatches, $latestDispatchesWithPump, $autoCarryPump) {
                    $ld = $latestDispatches->get($so->id);
                    $ldPump = $autoCarryPump ? $latestDispatchesWithPump->get($so->id) : null;
                    $concretePump = $autoCarryPump
                        ? ($ldPump?->concrete_pump ?? $ld?->concrete_pump ?? $so->concrete_pump ?? $so->cpo_concrete_pump ?? $so->q_concrete_pump ?? null)
                        : null;
                    $fullNumber = $so->customer_name ? "{$so->full_number} ({$so->customer_name})" : $so->full_number;
                    return array_merge((array)$so, [
                        'full_number' => $fullNumber,
                        'order_number' => $so->full_number,
                        'concrete_pump' => $concretePump,
                        'latest_dispatch' => $ld ? (array)$ld + ['concrete_pump' => $concretePump] : ($concretePump ? ['concrete_pump' => $concretePump] : null),
                    ]);
                });

        $now = now();
        $startYear = $now->month >= 4 ? $now->year : $now->year - 1;
        $fyStart = Carbon::create($startYear, 4, 1, 0, 0, 0);

        $nextBatchNo = Batch::query()
            ->whereHas('salesOrder', fn ($q) => $q->where('plant_id', $activePlantId))
            ->where('created_at', '>=', $fyStart)
            ->max('batch_no') + 1;

            // return response()->json([
            //     'batches' => $batches,
            //     'salesOrders' => $salesOrders,
            // ]);

        return Inertia::render('Batches/Index', [
            'batches'           => $batches,
            'salesOrders'        => $salesOrders,
            'trucks'            => MachinesDropdown(['Transit Mixer', 'Truck']),
            'customers'         => PatronsDropdown('Customer'),
            'transporters'      => PatronsDropdown('Transporter'),
            'loading_sites'     => SitesDropdown('loading'),
            'unloading_sites'   => SitesDropdown(),
            'drivers'           => DriversDropdown(),
            'operators'         => OperatorsDropdown(),
            'sales_executives'  => PersonnelDropdown('','Sales Executive'),
            'taxes'             => TaxesDropdown('sales'),
            'products'          => fn () => ProductsDropdown(),
            'uoms'              => Productunit(),
            'statuses'          => Batch::statusOptions(),
            'payment_methods'   => PaymentMethodsDropdown(),
            'sales_ledgers'     => toSelectOptions(LedgersDropdown('REVENUE'), 'title', 'id'),
            'nextBatchNo'       => $nextBatchNo ?: 1,
            'batchingSettings'  => CustomSetting::getForModule($activePlantId, 'batching'),
            'concretePumpOptions' => ConcretePumpOptions(),
        ]);
    }

    /**
     * Store a newly created batch in storage.
     */
   public function store(StoreBatchRequest $request)
{
    $this->authorizeModule('create');
    
    $payload = $request->validated();
   
    $salesOrder = SalesOrder::query()->findOrFail($payload['sales_order_id']);
    $this->ensurePlantScope($salesOrder);
 
        // Extract photos before unsetting
        $emptyPhoto = $payload['empty_weight_photo'] ?? null;
        $loadedPhoto = $payload['loaded_weight_photo'] ?? null;
        unset($payload['empty_weight_photo'], $payload['loaded_weight_photo']);

    $materialsData = $payload['materials'] ?? [];
    $activePlantId = session('active_plant_id', $salesOrder->plant_id);

    try {
        $batch = DB::transaction(function () use ($payload, $salesOrder, $emptyPhoto, $loadedPhoto, $materialsData, $activePlantId) {
            $payload['batch_no'] = $payload['batch_no'] ?? ($salesOrder->batches()->max('batch_no') + 1);
            $payload['status'] = $payload['status'] ?? Batch::STATUS_PLANNED;
            $payload['plant_id'] = $activePlantId; // ensure plant_id is set

                unset($payload['materials']);

                if (empty($payload['shift'])) {
                    $plant = Plant::find($activePlantId);
                    if ($plant) {
                        $shiftInfo = $plant->getCurrentShiftInfo($payload['start_time'] ?? null);
                        $payload['shift'] = $shiftInfo['shift'];
                    }
                }

                $batchData = array_intersect_key($payload, array_flip([
                    'sales_order_id',
                    'batch_no',
                    'batch_size',
                    'start_time',
                    'end_time',
                    'status',
                    'operator_id',
                    'shift',
                    'sync_status',
                    'batch_sheet_path',
                    'batch_original_sheet_path',
                    'created_by',
                    'updated_by',
                ]));
                $batch = Batch::create($batchData);
                $this->syncMaterials($batch, $materialsData);
                
                $batchingSettings = \App\Models\CustomSetting::getForModule($activePlantId, 'batching');
                $withStock = filter_var($batchingSettings['with_inventory'] ?? true, FILTER_VALIDATE_BOOLEAN);

                if ($withStock && in_array($batch->status, [Batch::STATUS_DISPATCHED, Batch::STATUS_COMPLETED])) {
                    $this->checkStock($batch, $materialsData);
                    $this->adjustStock($batch, $materialsData);
                }
                
                $salesOrder->refreshProduction();

                if (in_array($batch->status, [Batch::STATUS_DISPATCHED, Batch::STATUS_COMPLETED])) {
                    $this->sendBatchCompletedMail($batch);
                }

            // Only create dispatch if we have minimum required data
            $shouldCreateDispatch = !empty($payload['truck_id']) || !empty($payload['transport_id']);
            
            if ($shouldCreateDispatch) {
                $currentDate = now();
                $startYear = $currentDate->month >= 4 ? $currentDate->year : $currentDate->year - 1;
                $endYear = $startYear + 1;
                $fyString = substr($startYear, -2) . substr($endYear, -2);
                $prefix = "DP-{$fyString}-";
                
                $maxNumber = Dispatch::query()
                    ->where('plant_id', $activePlantId)
                    ->where('prefix', $prefix)
                    ->max(DB::raw('CAST(dispatch_no AS UNSIGNED)'));
                
                $baseRate = (float)($batch->rate ?? $salesOrder->rate ?? 0);
                $taxId = $batch->tax_id ?? $salesOrder->tax_id ?? null;

                $batchSize = (float)($batch->batch_size ?? 0);
                $netWeight = (float)($payload['net_weight'] ?? 0);

                $units = $batchSize;
                $loadRate = $baseRate;

                $isTaxInclusive = (bool)($salesOrder->is_tax_inclusive ?? false);

                $grossTotal = $units * $loadRate;
                $loadUntaxAmount = 0.0;
                $loadTaxAmount = 0.0;

                if ($taxId) {
                    $tax = \App\Models\Tax::find($taxId);
                    $taxRate = $tax ? (float)($tax->tax_rate ?? $tax->rate ?? 0) : 0.0;
                    if ($isTaxInclusive && $taxRate > 0) {
                        $loadUntaxAmount = $grossTotal / (1 + ($taxRate / 100));
                        $loadTaxAmount = $grossTotal - $loadUntaxAmount;
                    } else {
                        $loadUntaxAmount = $grossTotal;
                        $loadTaxAmount = ($loadUntaxAmount * $taxRate) / 100;
                    }
                } else {
                    $loadUntaxAmount = $grossTotal;
                }

                $loadTotalAmount = $isTaxInclusive ? $grossTotal : ($loadUntaxAmount + $loadTaxAmount);

                $dispatchData = [
                    'sales_order_id'      => $payload['sales_order_id'],
                    // 'customer_po_id'      => $salesOrder->customer_po_id,
                    'batch_id'            => $batch->id,
                    'plant_id'            => $activePlantId,
                    'customer_id'         => $salesOrder->customer_id,
                    'mixdesign_id'        => $salesOrder->mix_design_id,
                    'unload_site_id'      => $salesOrder->site_id,
                    
                    'load_site_id'        => $activePlantId, // usually plant is the load site, not $payload['site_id']
                    'uom_id'              => $payload['uom_id'] ?? null,
                    'truck_id'            => $payload['truck_id'] ?? null,
                    'transport_id'        => $payload['transport_id'] ?? null,
                    'driver_id'           => $payload['driver_id'] ?? null,
                    'operator_id'         => $payload['operator_id'] ?? $batch->operator_id ?? null,
                    'sales_executive_id'  => $payload['sales_executive_id'] ?? $salesOrder->sales_executive_id ?? $salesOrder->customerPO?->sales_executive_id ?? null,
                    'concrete_pump'       => $payload['concrete_pump'] ?? null,
                    'empty_weight_truck'  => $payload['empty_weight_truck'] ?? 0,
                    'loaded_weight_truck' => $payload['loaded_weight_truck'] ?? 0,
                    'net_weight'          => $payload['net_weight'] ?? 0,
                    'empty_time'          => $payload['empty_time'] ?? null,
                    'load_time'           => $payload['load_time'] ?? null,
                    'dispatch_status'     => 'Draft',
                    'prefix'              => $prefix,
                    'dispatch_no'         => (string)(($maxNumber ?: 0) + 1),
                    'dispatch_time'       => now()->format('H:i:s'),
                    'delivered_qty'       => $units,
                    'load_rate'           => $loadRate,
                    'load_tax_id'         => $taxId,
                    'load_untax_amount'   => $loadUntaxAmount,
                    'load_tax_amount'     => $loadTaxAmount,
                    'load_total_amount'   => $loadTotalAmount,
                    'created_at'          => now(),
                ];

                Log::info('Creating Dispatch', $dispatchData);
                $dispatch = Dispatch::create($dispatchData);
                    
                $dispatch->status()->updateOrCreate(
                    ['dispatch_id' => $dispatch->id],
                    [
                        'plant_id' => $dispatch->plant_id,
                        'is_tax_inclusive' => (bool)$salesOrder->is_tax_inclusive,
                    ]
                );
            }

            if ($emptyPhoto) $this->storeBatchImage($batch, $emptyPhoto, 'empty');
            if ($loadedPhoto) $this->storeBatchImage($batch, $loadedPhoto, 'loaded');

            return $batch;
        });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Batch Store Failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'payload' => $payload
            ]);
            throw ValidationException::withMessages([
                'error' => ['Failed to create batch: ' . $e->getMessage()]
            ]);
        }

    return redirect()->route('batches.token', $batch->id);
}
    /**
     * Pushes structural context configurations out to remote active hardware loops.
     */
    private function pushToSchedulerAPI(Batch $batch, array $materialsData): bool
    {
        try {
            $salesOrder = $batch->salesOrder;
            $salesOrder->loadMissing(['plant', 'customer.addresses', 'site', 'mixDesign']);
            
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
                "plant_sl"     => $salesOrder->plant->code ?? "",
                "plant_type"   => $salesOrder->plant->plant_type ?? "",
                "order_no"     => $salesOrder->order_no ?? "",
                "order_date"   => $salesOrder->created_at ? $salesOrder->created_at->format('Y-m-d') : "",
                "order_status" => (string)$salesOrder->status,
                "cust_id"      => current(explode('-', $salesOrder->customer->code ?? "")) ?: ($salesOrder->customer->id ?? ""),
                "cust_name"    => $salesOrder->customer->legal_name ?? "",
                "cust_add_l1"  => $salesOrder->customer->addresses->first()->line_1 ?? "",
                "cust_add_l2"  => $salesOrder->customer->addresses->first()->city ?? "",
                "site_name"    => $salesOrder->site->name ?? "",
                "site_add_l1"  => $salesOrder->site->site_address_1 ?? "",
                "site_add_l2"  => $salesOrder->site->zipcode ?? "", 
                "strength"     => "",
                "consistency"  => "",
                "slump"        => "",
                "wat_cem_ratio"=> "",
                "mix_time"     => "",
                "mix_dis_time" => "",
                "pre_mix_time" => "",
                "rec_id"       => current(explode('-', $salesOrder->mixDesign->design_code ?? "")) ?: ($salesOrder->mixDesign->design_code ?? ""),
                "rec_name"     => $salesOrder->mixDesign->design_name ?? "",
                "qty"          => (string)($batch->batch_size ?? "0"),
                "mat"          => $matArray
            ];

            $token = $this->getSchedulerToken($salesOrder->plant);
            $request = Http::withHeaders(['Accept' => 'application/json']);

            if ($token) {
                $request = $request->withToken($token);
            }

            $apiUrl = $salesOrder->plant->scheduler_api_url ?: url('/api/production__Order__data');
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
        $this->ensurePlantScope($batch->salesOrder);
        
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

    public function show($batch, Request $request)
    {
        $this->authorizeModule('menu');

        $batchId = $batch instanceof Batch ? $batch->id : (int)$batch;

        $batchRow = DB::table('mm_batches as b')
            ->join('mm_sales_orders as so', function ($join) {
                $join->on('so.id', '=', 'b.sales_order_id')
                     ->whereNull('so.deleted_at');
            })
            ->leftJoin('mm_patrons as p', function ($join) {
                $join->on('p.id', '=', 'so.customer_id')
                     ->whereNull('p.deleted_at');
            })
            ->leftJoin('mm_sites as s', function ($join) {
                $join->on('s.id', '=', 'so.site_id')
                     ->whereNull('s.deleted_at');
            })
            ->leftJoin('mm_mix_designs as m', function ($join) {
                $join->on('m.id', '=', 'so.mix_design_id')
                     ->whereNull('m.deleted_at');
            })
            ->leftJoin('mm_concrete_grades as cg', function ($join) {
                $join->on('cg.id', '=', 'm.concrete_grade_id')
                     ->whereNull('cg.deleted_at');
            })
            ->where('b.id', $batchId)
            ->whereNull('b.deleted_at')
            ->select([
                'b.id',
                'b.plant_id',
                'b.sales_order_id',
                'b.batch_no',
                'b.batch_size',
                'b.status',
                'b.start_time',
                'b.end_time',
                'b.operator_id',
                'b.shift',
                'b.is_verified',
                'b.sync_status',
                'b.created_at',
                'b.updated_at',
                // Sales Order
                'so.prefix as so_prefix',
                'so.order_no as so_order_no',
                'so.total_qty as so_total_qty',
                'so.produced_qty as so_produced_qty',
                'so.rate as so_rate',
                'so.tax_id as so_tax_id',
                'so.is_tax_inclusive as so_is_tax_inclusive',
                'so.concrete_pump as so_concrete_pump',
                'so.pump_rate as so_pump_rate',
                'so.sales_executive_id as so_sales_executive_id',
                // Customer / Patron
                'p.id as customer_id',
                'p.legal_name as customer_legal_name',
                'p.code as customer_code',
                // Site
                's.id as site_id',
                's.name as site_name',
                's.site_address_1',
                // Mix Design
                'm.id as mix_design_id',
                'm.design_name as mix_design_name',
                'm.design_code as mix_design_code',
                'cg.id as concrete_grade_id',
                'cg.name as concrete_grade_name',
            ])
            ->first();

        if (!$batchRow) {
            abort(404, 'Batch not found');
        }

        $materials = DB::table('mm_batch_materials as bm')
            ->leftJoin('mm_products as p', 'p.id', '=', 'bm.product_id')
            ->leftJoin('mm_product_units as u', 'u.id', '=', 'bm.uom_id')
            ->where('bm.batch_id', $batchId)
            ->whereNull('bm.deleted_at')
            ->select([
                'bm.id',
                'bm.batch_id',
                'bm.product_id',
                'bm.material_name',
                'bm.target_qty',
                'bm.actual_qty',
                'bm.deviation_quantity',
                'bm.uom_id',
                'p.title as product_title',
                'u.unit_code',
                'u.unit_name',
            ])
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'batch_id' => $item->batch_id,
                    'product_id' => $item->product_id,
                    'material_name' => $item->material_name,
                    'target_qty' => (float)$item->target_qty,
                    'actual_qty' => (float)$item->actual_qty,
                    'deviation_quantity' => (float)$item->deviation_quantity,
                    'uom_id' => $item->uom_id,
                    'product' => $item->product_id ? [
                        'id' => $item->product_id,
                        'title' => $item->product_title,
                    ] : null,
                    'uom' => $item->uom_id ? [
                        'id' => $item->uom_id,
                        'unit_code' => $item->unit_code,
                        'unit_name' => $item->unit_name,
                    ] : null,
                ];
            });

        $mixDesignItems = collect();
        if ($batchRow->mix_design_id) {
            $mixDesignItems = DB::table('mm_mix_design_items as mdi')
                ->leftJoin('mm_products as p', 'p.id', '=', 'mdi.product_id')
                ->leftJoin('mm_product_units as u', 'u.id', '=', 'mdi.uom_id')
                ->where('mdi.mix_design_id', $batchRow->mix_design_id)
                ->whereNull('mdi.deleted_at')
                ->select([
                    'mdi.id',
                    'mdi.mix_design_id',
                    'mdi.product_id',
                    'mdi.uom_id',
                    'mdi.rate',
                    'mdi.actual_quantity',
                    'mdi.cross_quantity',
                    'mdi.variation_quantity',
                    'p.title as product_title',
                    'p.material_code',
                    'u.unit_code',
                ])
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'mix_design_id' => $item->mix_design_id,
                        'product_id' => $item->product_id,
                        'uom_id' => $item->uom_id,
                        'rate' => (float)$item->rate,
                        'actual_quantity' => (float)$item->actual_quantity,
                        'cross_quantity' => (float)$item->cross_quantity,
                        'variation_quantity' => (float)$item->variation_quantity,
                        'product' => $item->product_id ? [
                            'id' => $item->product_id,
                            'title' => $item->product_title,
                            'material_code' => $item->material_code,
                        ] : null,
                        'uom' => $item->uom_id ? [
                            'id' => $item->uom_id,
                            'unit_code' => $item->unit_code,
                        ] : null,
                    ];
                });
        }

        $dispatches = DB::table('mm_dispatches as d')
            ->leftJoin('mm_machines as t', 't.id', '=', 'd.truck_id')
            ->leftJoin('mm_patrons as tr', 'tr.id', '=', 'd.transport_id')
            ->leftJoin('mm_personnels as dr', 'dr.id', '=', 'd.driver_id')
            ->leftJoin('mm_personnels as op', 'op.id', '=', 'd.operator_id')
            ->leftJoin('mm_personnels as se', 'se.id', '=', 'd.sales_executive_id')
            ->leftJoin('mm_dispatch_statuses as ds', 'ds.dispatch_id', '=', 'd.id')
            ->leftJoin('mm_invoices as inv', 'inv.id', '=', 'ds.invoice_id')
            ->leftJoin('mm_einvoice_invoice_rel as einv_rel', 'einv_rel.invoice_id', '=', 'inv.id')
            ->leftJoin('mm_ewaybill_details as ewb', function ($join) {
                $join->on('ewb.origin_id', '=', 'inv.id')
                     ->where('ewb.generation_type', '=', 'invoice');
            })
            ->leftJoin('mm_users as inv_user', 'inv_user.id', '=', 'inv.created_by')
            ->where('d.batch_id', $batchId)
            ->whereNull('d.deleted_at')
            ->select([
                'd.id',
                'd.batch_id',
                'd.sales_order_id',
                'd.plant_id',
                'd.customer_id',
                'd.mixdesign_id',
                'd.unload_site_id',
                'd.load_site_id',
                'd.uom_id',
                'd.truck_id',
                'd.transport_id',
                'd.driver_id',
                'd.operator_id',
                'd.sales_executive_id',
                'd.concrete_pump',
                'd.pump_charges',
                'd.pump_charge_with_tax',
                'd.empty_weight_truck',
                'd.loaded_weight_truck',
                'd.net_weight',
                'd.empty_time',
                'd.load_time',
                'd.dispatch_status',
                'd.prefix',
                'd.dispatch_no',
                'd.dispatch_reference',
                'd.dispatch_time',
                'd.delivered_qty',
                'd.load_rate',
                'd.load_tax_id',
                'd.load_untax_amount',
                'd.load_tax_amount',
                'd.load_total_amount',
                'd.pass_amount',
                'd.discount_amount',
                'd.transport_expenses',
                'd.adjustment_amount',
                'd.round_off',
                'd.payment_mode',
                'd.created_at',
                'd.updated_at',
                't.registration as truck_registration',
                'tr.legal_name as transport_legal_name',
                'dr.first_name as driver_first_name',
                'dr.last_name as driver_last_name',
                'op.first_name as operator_first_name',
                'op.last_name as operator_last_name',
                'se.first_name as sales_executive_first_name',
                'se.last_name as sales_executive_last_name',
                'ds.id as dispatch_status_id',
                'ds.invoice_id',
                'ds.invoice_status',
                'ds.invoice_date as status_invoice_date',
                'ds.invoice_number as status_invoice_number',
                'ds.is_tax_inclusive as dispatch_is_tax_inclusive',
                'ds.transport_units as status_transport_units',
                'ds.transport_rate as status_transport_rate',
                'ds.transport_tax_id as status_transport_tax_id',
                'ds.transport_tax_amount as status_transport_tax_amount',
                'ds.transport_total_amount as status_transport_total_amount',
                'ds.total_amount as status_total_amount',
                'ds.transport_reference as status_transport_reference',
                'ds.transport_km as status_transport_km',
                'ds.receiver_name as status_receiver_name',
                'ds.receive_mobile as status_receive_mobile',
                'ds.note as status_note',
                'inv.prefix as invoice_prefix',
                'inv.invoice_number',
                'inv.status as invoice_status_text',
                'inv.total_amount as invoice_total_amount',
                'inv_user.username as invoice_creator_username',
                'inv_user.email as invoice_creator_email',
                'einv_rel.einv_irn as invoice_einvoice_irn',
                'einv_rel.einv_status as invoice_einvoice_status',
                'einv_rel.einv_ackno as invoice_einvoice_ack_no',
                'einv_rel.einv_ack_date as invoice_einvoice_ack_date',
                'einv_rel.einv_signed_qrcode as invoice_einvoice_qr_code',
                'ewb.ewaybill_no as invoice_eway_bill_no',
                'ewb.ewaybill_date as invoice_eway_bill_date',
                'ewb.valid_upto as invoice_eway_bill_valid_until',
            ])
            ->get();

        $dispatchIds = $dispatches->pluck('id')->filter()->toArray();
        $paymentsByDispatch = [];
        if (!empty($dispatchIds)) {
            $paymentsByDispatch = DB::table('mm_dispatch_payments')
                ->whereIn('dispatch_id', $dispatchIds)
                ->whereNull('deleted_at')
                ->get()
                ->groupBy('dispatch_id');
        }

        $mappedDispatches = $dispatches->map(function ($d) use ($paymentsByDispatch) {
            $payments = ($paymentsByDispatch[$d->id] ?? collect())->map(function ($p) {
                return [
                    'id' => $p->id,
                    'dispatch_id' => $p->dispatch_id,
                    'payment_method_id' => $p->payment_method_id,
                    'amount' => (float)$p->amount,
                    'payment_type' => $p->payment_type ?? null,
                    'collected_by' => $p->collected_by ?? null,
                    'reference' => $p->reference ?? null,
                    'is_active' => (bool)($p->is_active ?? true),
                ];
            })->values()->all();

            $fullInvoiceNo = ($d->invoice_prefix ?? '') . ($d->invoice_number ?? $d->status_invoice_number ?? '');

            return [
                'id' => $d->id,
                'batch_id' => $d->batch_id,
                'sales_order_id' => $d->sales_order_id,
                'plant_id' => $d->plant_id,
                'customer_id' => $d->customer_id,
                'mixdesign_id' => $d->mixdesign_id,
                'unload_site_id' => $d->unload_site_id,
                'load_site_id' => $d->load_site_id,
                'uom_id' => $d->uom_id,
                'truck_id' => $d->truck_id,
                'transport_id' => $d->transport_id,
                'driver_id' => $d->driver_id,
                'operator_id' => $d->operator_id,
                'sales_executive_id' => $d->sales_executive_id,
                'concrete_pump' => $d->concrete_pump,
                'pump_charges' => (float)($d->pump_charges ?? 0),
                'pump_charge_with_tax' => (bool)$d->pump_charge_with_tax,
                'empty_weight_truck' => (float)$d->empty_weight_truck,
                'loaded_weight_truck' => (float)$d->loaded_weight_truck,
                'net_weight' => (float)$d->net_weight,
                'empty_time' => $d->empty_time,
                'load_time' => $d->load_time,
                'dispatch_status' => $d->dispatch_status,
                'prefix' => $d->prefix,
                'dispatch_no' => $d->dispatch_no,
                'dispatch_reference' => $d->dispatch_reference,
                'dispatch_time' => $d->dispatch_time,
                'delivered_qty' => (float)$d->delivered_qty,
                'load_units' => (float)($d->delivered_qty ?? 0),
                'load_rate' => (float)$d->load_rate,
                'load_tax_id' => $d->load_tax_id,
                'load_untax_amount' => (float)$d->load_untax_amount,
                'load_tax_amount' => (float)$d->load_tax_amount,
                'load_total_amount' => (float)$d->load_total_amount,
                'pass_amount' => (float)($d->pass_amount ?? 0),
                'discount_amount' => (float)($d->discount_amount ?? 0),
                'transport_expenses' => (float)($d->transport_expenses ?? 0),
                'adjustment_amount' => (float)($d->adjustment_amount ?? 0),
                'round_off' => (float)($d->round_off ?? 0),
                'payment_mode' => $d->payment_mode ?? 'credit',
                'created_at' => $d->created_at,
                'updated_at' => $d->updated_at,
                'truck' => $d->truck_id ? [
                    'id' => $d->truck_id,
                    'registration' => $d->truck_registration,
                ] : null,
                'transport' => $d->transport_id ? [
                    'id' => $d->transport_id,
                    'legal_name' => $d->transport_legal_name,
                ] : null,
                'driver' => $d->driver_id ? [
                    'id' => $d->driver_id,
                    'first_name' => $d->driver_first_name,
                    'last_name' => $d->driver_last_name,
                    'label' => trim(($d->driver_first_name ?? '') . ' ' . ($d->driver_last_name ?? '')),
                ] : null,
                'operator' => $d->operator_id ? [
                    'id' => $d->operator_id,
                    'first_name' => $d->operator_first_name,
                    'last_name' => $d->operator_last_name,
                ] : null,
                'sales_executive' => $d->sales_executive_id ? [
                    'id' => $d->sales_executive_id,
                    'first_name' => $d->sales_executive_first_name,
                    'last_name' => $d->sales_executive_last_name,
                    'label' => trim(($d->sales_executive_first_name ?? '') . ' ' . ($d->sales_executive_last_name ?? '')),
                ] : null,
                'status' => [
                    'id' => $d->dispatch_status_id,
                    'dispatch_id' => $d->id,
                    'invoice_id' => $d->invoice_id,
                    'invoice_status' => (int)($d->invoice_status ?? ($d->invoice_id ? 1 : 0)),
                    'invoice_date' => $d->status_invoice_date ?? null,
                    'invoice_number' => $fullInvoiceNo,
                    'is_tax_inclusive' => (bool)$d->dispatch_is_tax_inclusive,
                    'transport_units' => (float)($d->status_transport_units ?? 0),
                    'transport_rate' => (float)($d->status_transport_rate ?? 0),
                    'transport_tax_id' => $d->status_transport_tax_id,
                    'transport_tax_amount' => (float)($d->status_transport_tax_amount ?? 0),
                    'transport_total_amount' => (float)($d->status_transport_total_amount ?? 0),
                    'total_amount' => (float)($d->status_total_amount ?? 0),
                    'transport_reference' => $d->status_transport_reference ?? '',
                    'transport_km' => (float)($d->status_transport_km ?? 0),
                    'receiver_name' => $d->status_receiver_name ?? '',
                    'receive_mobile' => $d->status_receive_mobile ?? '',
                    'note' => $d->status_note ?? '',
                    'invoice' => $d->invoice_id ? [
                        'id' => $d->invoice_id,
                        'encrypted_id' => encrypt($d->invoice_id),
                        'invoice_number' => $fullInvoiceNo,
                        'invoice_prefix' => $d->invoice_prefix,
                        'full_number' => $fullInvoiceNo,
                        'status' => $d->invoice_status_text,
                        'total_amount' => (float)$d->invoice_total_amount,
                        'einvoice_irn' => $d->invoice_einvoice_irn,
                        'einvoice_status' => $d->invoice_einvoice_status,
                        'einvoice_ack_no' => $d->invoice_einvoice_ack_no,
                        'einvoice_ack_date' => $d->invoice_einvoice_ack_date,
                        'einvoice_qr_code' => $d->invoice_einvoice_qr_code,
                        'eway_bill_no' => $d->invoice_eway_bill_no,
                        'eway_bill_date' => $d->invoice_eway_bill_date,
                        'eway_bill_valid_until' => $d->invoice_eway_bill_valid_until,
                        'creator' => [
                            'username' => $d->invoice_creator_username,
                            'email' => $d->invoice_creator_email,
                        ],
                    ] : null,
                ],
                'payments' => $payments,
            ];
        });

        $primaryDispatch = $mappedDispatches->first();

        $batchingSettings = CustomSetting::getForModule($batchRow->plant_id ?? session('active_plant_id'), 'batching');
        $autoCarryPump = !empty($batchingSettings['auto_carry_pump']);

        $latestDispatch = DB::table('mm_dispatches')
            ->where('sales_order_id', $batchRow->sales_order_id)
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->first();

        $latestDispatchWithPump = null;
        if ($autoCarryPump) {
            $latestDispatchWithPump = DB::table('mm_dispatches')
                ->where('sales_order_id', $batchRow->sales_order_id)
                ->whereNotNull('concrete_pump')
                ->whereNull('deleted_at')
                ->orderByDesc('id')
                ->first();
        }

        $resolvedPump = $primaryDispatch['concrete_pump'] 
            ?? ($autoCarryPump ? ($latestDispatchWithPump?->concrete_pump ?? $latestDispatch?->concrete_pump ?? $batchRow->so_concrete_pump) : null)
            ?? null;

        $responseData = [
            'id' => $batchRow->id,
            'encrypted_id' => encrypt($batchRow->id),
            'plant_id' => $batchRow->plant_id,
            'sales_order_id' => $batchRow->sales_order_id,
            'batch_no' => $batchRow->batch_no,
            'batch_size' => (float)$batchRow->batch_size,
            'status' => $batchRow->status,
            'start_time' => $batchRow->start_time,
            'end_time' => $batchRow->end_time,
            'operator_id' => $batchRow->operator_id,
            'shift' => $batchRow->shift,
            'is_verified' => (bool)$batchRow->is_verified,
            'sync_status' => $batchRow->sync_status,
            'created_at' => $batchRow->created_at,
            'updated_at' => $batchRow->updated_at,
            'truck_id' => $primaryDispatch['truck_id'] ?? null,
            'transport_id' => $primaryDispatch['transport_id'] ?? null,
            'driver_id' => $primaryDispatch['driver_id'] ?? null,
            'sales_executive_id' => $primaryDispatch['sales_executive_id'] ?? $batchRow->so_sales_executive_id ?? null,
            'concrete_pump' => $resolvedPump,
            'empty_weight_truck' => (float)($primaryDispatch['empty_weight_truck'] ?? 0),
            'loaded_weight_truck' => (float)($primaryDispatch['loaded_weight_truck'] ?? 0),
            'net_weight' => (float)($primaryDispatch['net_weight'] ?? 0),
            'materials' => $materials,
            'dispatches' => $mappedDispatches,
            'dispatch' => $primaryDispatch,
            'sales_order' => [
                'id' => $batchRow->sales_order_id,
                'prefix' => $batchRow->so_prefix,
                'order_no' => $batchRow->so_order_no,
                'full_number' => ($batchRow->so_prefix ?? '') . ($batchRow->so_order_no ?? ''),
                'produced_qty' => (float)($batchRow->so_produced_qty ?? 0),
                'total_qty' => (float)($batchRow->so_total_qty ?? 0),
                'rate' => (float)($batchRow->so_rate ?? 0),
                'tax_id' => $batchRow->so_tax_id,
                'is_tax_inclusive' => (bool)$batchRow->so_is_tax_inclusive,
                'concrete_pump' => $resolvedPump,
                'pump_rate' => (float)($batchRow->so_pump_rate ?? 0),
                'sales_executive_id' => $batchRow->so_sales_executive_id,
                'customer_name' => $batchRow->customer_legal_name,
                'site_name' => $batchRow->site_name,
                'mix_design_name' => $batchRow->mix_design_name,
                'customer' => [
                    'id' => $batchRow->customer_id,
                    'legal_name' => $batchRow->customer_legal_name,
                    'code' => $batchRow->customer_code,
                ],
                'site' => [
                    'id' => $batchRow->site_id,
                    'name' => $batchRow->site_name,
                    'site_address_1' => $batchRow->site_address_1,
                ],
                'mix_design' => [
                    'id' => $batchRow->mix_design_id,
                    'design_name' => $batchRow->mix_design_name,
                    'design_code' => $batchRow->mix_design_code,
                    'concrete_grade' => [
                        'id' => $batchRow->concrete_grade_id,
                        'name' => $batchRow->concrete_grade_name,
                    ],
                    'items' => $mixDesignItems,
                ],
                'latest_dispatch' => $latestDispatch ? [
                    'id' => $latestDispatch->id,
                    'truck_id' => $latestDispatch->truck_id,
                    'driver_id' => $latestDispatch->driver_id,
                    'transport_id' => $latestDispatch->transport_id,
                    'sales_executive_id' => $latestDispatch->sales_executive_id,
                    'concrete_pump' => $latestDispatch->concrete_pump,
                    'empty_weight_truck' => (float)$latestDispatch->empty_weight_truck,
                    'loaded_weight_truck' => (float)$latestDispatch->loaded_weight_truck,
                    'net_weight' => (float)$latestDispatch->net_weight,
                ] : null,
            ],
        ];

        return response()->json($responseData);
    }

    public function update(UpdateBatchRequest $request, Batch $batch)
    {
        $this->authorizeModule('edit');

        $user = auth()->user();
        // $isAdmin = $user && method_exists($user, 'hasRole') && (
        //     $user->hasRole('Saas Owner') || 
        //     $user->hasRole('Platform Admin') || 
        //     $user->hasRole('Super Admin') || 
        //     $user->hasRole('Admin') || 
        //     $user->hasRole('Super Administrator') ||
        //     $user->hasRole('Administrator')
        // );

        // if (!$isAdmin) {
        //     $dispatch = $batch->dispatches()->first();
        //     $dispatchPump = $dispatch ? ($dispatch->concrete_pump ?? $dispatch->concrete_pump) : null;
        //     if (
        //         ($request->has('batch_size') && (float)$request->batch_size !== (float)$batch->batch_size) ||
        //         ($request->has('sales_order_id') && (int)$request->sales_order_id !== (int)$batch->sales_order_id) ||
        //         (($request->has('concrete_pump') || $request->has('concrete_pump')) && ($request->get('concrete_pump') ?? $request->get('concrete_pump')) !== $dispatchPump)
        //     ) {
        //         return redirect()->back()->withErrors(['error' => 'Only administrators are authorized to modify Sales Order, Batch Size, or Concrete Pump.']);
        //     }
        // }

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

                $newStatus = $payload['status'] ?? $batch->status;
                $hasActual = collect($materials)->contains(fn($m) => (float)($m['actual_qty'] ?? 0) > 0);
                if ($hasActual && $newStatus == Batch::STATUS_PLANNED) {
                    $newStatus = Batch::STATUS_DISPATCHED;
                    $payload['status'] = $newStatus;
                }

                $batchingSettings = \App\Models\CustomSetting::getForModule($batch->plant_id, 'batching');
                $withStock = filter_var($batchingSettings['with_inventory'] ?? true, FILTER_VALIDATE_BOOLEAN);

                if ($withStock && in_array($newStatus, [Batch::STATUS_DISPATCHED, Batch::STATUS_COMPLETED])) {
                    $wasDeducted = in_array($oldStatus, [Batch::STATUS_DISPATCHED, Batch::STATUS_COMPLETED]);
                    $this->checkStock($batch, $materials, $oldMaterials, $wasDeducted);
                }

                // Revert old consumption only if it was previously deducted
                if ($withStock && in_array($oldStatus, [Batch::STATUS_DISPATCHED, Batch::STATUS_COMPLETED])) {
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
            if ($withStock && in_array($batch->status, [Batch::STATUS_DISPATCHED, Batch::STATUS_COMPLETED])) {
                $this->adjustStock($batch, $materials);
            }
            $batch->salesOrder->refreshProduction();

                if (in_array($batch->status, [Batch::STATUS_DISPATCHED, Batch::STATUS_COMPLETED]) && 
                    $oldStatus != $batch->status) {
                    $this->sendBatchCompletedMail($batch);
                }
                if ($batch->status == Batch::STATUS_DISPATCHED && $oldStatus != Batch::STATUS_DISPATCHED) {
                    session()->flash('dispatched_batch_id', $batch->id);
                }

                $dispatch = $batch->dispatches()->first();
                if ($dispatch) {
                    $dispatch->update([
                        'truck_id' => array_key_exists('truck_id', $payload) ? $payload['truck_id'] : $dispatch->truck_id,
                        'transport_id' => array_key_exists('transport_id', $payload) ? $payload['transport_id'] : $dispatch->transport_id,
                        'driver_id' => array_key_exists('driver_id', $payload) ? $payload['driver_id'] : $dispatch->driver_id,
                        'operator_id' => array_key_exists('operator_id', $payload) ? $payload['operator_id'] : $dispatch->operator_id,
                        'sales_executive_id' => array_key_exists('sales_executive_id', $payload) ? $payload['sales_executive_id'] : $dispatch->sales_executive_id,
                        'concrete_pump' => array_key_exists('concrete_pump', $payload) ? $payload['concrete_pump'] : (array_key_exists('concrete_pump', $payload) ? $payload['concrete_pump'] : $dispatch->concrete_pump),
                        'empty_weight_truck' => array_key_exists('empty_weight_truck', $payload) ? $payload['empty_weight_truck'] : $dispatch->empty_weight_truck,
                        'loaded_weight_truck' => array_key_exists('loaded_weight_truck', $payload) ? $payload['loaded_weight_truck'] : $dispatch->loaded_weight_truck,
                        'net_weight' => array_key_exists('net_weight', $payload) ? $payload['net_weight'] : $dispatch->net_weight,
                        'uom_id' => array_key_exists('uom_id', $payload) ? $payload['uom_id'] : $dispatch->uom_id,
                        'empty_time' => array_key_exists('empty_time', $payload) ? $payload['empty_time'] : $dispatch->empty_time,
                        'load_time' => array_key_exists('load_time', $payload) ? $payload['load_time'] : $dispatch->load_time,
                        'updated_by' => auth()->id(),
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
        $batch->load('salesOrder');
        $this->ensurePlantScope($batch->salesOrder);

        $batchId = $batch->id;
        DB::transaction(function () use ($batch) {
            $materials = $batch->materials()->get()->toArray();
            
            $batchingSettings = \App\Models\CustomSetting::getForModule($batch->salesOrder->plant_id ?? session('active_plant_id'), 'batching');
            $withStock = filter_var($batchingSettings['with_inventory'] ?? true, FILTER_VALIDATE_BOOLEAN);

            // Revert stock only if it was previously deducted
            if ($withStock && in_array($batch->status, [Batch::STATUS_DISPATCHED, Batch::STATUS_COMPLETED])) {
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
            $batch->salesOrder->refreshProduction();
        });

        $this->broadcastBatchDeletion($batchId);

        return redirect()->back()->with('success', 'Batch deleted successfully.');
    }

    public function report($batchId)
    {
        try {
            $decryptedId = decrypt($batchId);
            $batch = Batch::findOrFail($decryptedId);
        } catch (\Exception $e) {
            try {
                $decryptedId = \Illuminate\Support\Facades\Crypt::decryptString($batchId);
                $batch = Batch::findOrFail($decryptedId);
            } catch (\Exception $e2) {
                if (is_numeric($batchId)) {
                    $batch = Batch::findOrFail($batchId);
                } else {
                    abort(404, 'Invalid Batch ID');
                }
            }
        }
       
        $batch = $this->resolveBatchSheetBatch($batch);
        $sheet = $this->prepareBatchSheetData($batch);

        return view('pdfs.batches.batch_sheet', [
            'batch' => $batch,
            'sheet' => $sheet,
            'isPreview' => true,
        ]);
    }

    public function downloadPdf($batchId)
    {
        try {
            $decryptedId = decrypt($batchId);
            $batch = Batch::findOrFail($decryptedId);
        } catch (\Exception $e) {
            try {
                $decryptedId = \Illuminate\Support\Facades\Crypt::decryptString($batchId);
                $batch = Batch::findOrFail($decryptedId);
            } catch (\Exception $e2) {
                if (is_numeric($batchId)) {
                    $batch = Batch::findOrFail($batchId);
                } else {
                    abort(404, 'Invalid Batch ID');
                }
            }
        }

        $batch = $this->resolveBatchSheetBatch($batch);
        $sheet = $this->prepareBatchSheetData($batch);

        $pdf = Pdf::loadView('pdfs.batches.batch_sheet', [
            'batch' => $batch,
            'sheet' => $sheet,
            'isPreview' => false,
        ])->setPaper('a4', 'landscape');

        $orderNo = $batch->salesOrder?->order_no ?? 'order';
        $safeOrderNo = str_replace(['/', '\\'], '-', $orderNo);
        $filename = sprintf(
            'batch-sheet-%s-%s.pdf',
            $safeOrderNo,
            $batch->batch_no ?? $batch->id
        );

        return $pdf->download($filename);
    }

    public function sendEmail(Batch $batch)
    {
        $batch = $this->resolveBatchSheetBatch($batch);

        try {
            $customer = $batch->salesOrder?->customer;
        
            $customerEmail = null;
            if ($customer) {
                $contact = $customer->contacts()->where('is_primary', true)->first() ?? $customer->contacts()->first();
                $customerEmail = $contact?->email ?? null;
            } 
            if (!$customerEmail) {
                return response()->json([
                    'error' => 'No email address configured for this customer.'
                ], 422);
            } 
            // \Illuminate\Support\Facades\Notification::route('mail', $customerEmail)
            //     ->notify(new \App\Notifications\BatchCompletedNotification($batch));

            return response()->json([
                'success' => true,
                'message' => 'Batch report email sent successfully to ' . $customerEmail
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Manual batch email failed: " . $e->getMessage());
            return response()->json([
                'error' => 'Failed to send email: ' . $e->getMessage()
            ], 500);
        }
    }

    public function token(Batch $batch)
    {
        $this->loadBatchForToken($batch);
        $this->ensurePlantScope($batch->salesOrder);

        $settings = \App\Models\CustomSetting::getForModule($batch->salesOrder->plant_id, 'batching');

        return view('pdfs.batches.batching_token', [
            'batch'     => $batch,
            'isPreview' => true,
            'settings'  => $settings,
        ]);
    }

    public function downloadTokenPdf(Batch $batch)
    {
        $this->loadBatchForToken($batch);
        $this->ensurePlantScope($batch->salesOrder);

        $settings = \App\Models\CustomSetting::getForModule($batch->salesOrder->plant_id, 'batching');

        // 80mm width = 226.77pt. DomPDF uses points (72pt = 1in). Custom portrait ticket size.
        $materialsCount = $batch->materials->count();
        $height = 320 + ($materialsCount * 15);

        $pdf = Pdf::loadView('pdfs.batches.batching_token', [
            'batch'     => $batch,
            'isPreview' => false,
            'settings'  => $settings,
        ])->setPaper([0, 0, 226.77, $height], 'portrait');

        $filename = sprintf('batch-token-%s.pdf', $batch->batch_no ?? $batch->id);
        return $pdf->download($filename);
    }

    /**
     * Load only the relationships the batching token blade actually uses.
     *
     * Blade audit (batching_token.blade.php):
     *   - batch: batch_no, batch_size, load_time, created_at, shift, operator->label
     *   - salesOrder: order_no, customer->legal_name, site->name,
     *                mixDesign->design_code/design_name, mixDesign->concrete_grade->name
     *   - plant: name, logo_path, addresses->first() (line_1, city, state, pincode)
     *   - dispatches->first(): truck->registration, driver->label, transport->legal_name,
     *                          loadSite->name, salesExecutive->label,
     *                          empty_weight_truck, empty_time
     *   - materials: COMMENTED OUT in blade — not loaded at all
     *
     * Total: 2 queries (1 belongsTo chain + 1 constrained dispatch with its belongsTo)
     * + 1 query for plant addresses (hasMany, kept separate to avoid row duplication)
     */
    private function loadBatchForToken(Batch $batch): void
    {
        // Query 1 – all pure belongsTo chains (no hasMany = no row duplication risk)
        $batch->load([
            'salesOrder:id,prefix,plant_id,customer_id,mix_design_id,site_id,order_no',
            'salesOrder.customer:id,legal_name',
            'salesOrder.site:id,name',
            'salesOrder.plant:id,entity_id,name,logo_path',
            'salesOrder.mixDesign:id,design_name,design_code,design_type',
            'salesOrder.mixDesign.concrete_grade:id,name',
            'operator:id,first_name,last_name',
        ]);

        // Query 2 – only the first dispatch + its belongsTo associations
        // (blade only ever calls $batch->dispatches->first(), never iterates all dispatches)
        $batch->load([
            'dispatches' => fn ($q) => $q
                ->select('id', 'batch_id', 'truck_id', 'driver_id', 'transport_id', 'load_site_id', 'sales_executive_id', 'empty_weight_truck', 'empty_time')
                ->oldest('id')
                ->limit(1),
            'dispatches.truck:id,registration',
            'dispatches.driver:id,first_name,last_name',
            'dispatches.salesExecutive:id,first_name,last_name',
            'dispatches.transport:id,legal_name',
            'dispatches.loadSite:id,name',
        ]);

        // Query 3 – plant addresses (hasMany on a nested model — isolated to prevent row duplication)
        $batch->salesOrder?->plant?->load('addresses');

        // Query 4 – materials for the batching token (target qty + per-CBM rate)
        $batch->load([
            'materials:id,batch_id,product_id,material_name,target_qty,uom_id',
            'materials.product:id,title',
            'materials.uom:id,unit_code',
        ]);
    }

    public function dispatchToken(Batch $batch)
    {
        $batch->load([
            'salesOrder:id,prefix,plant_id,customer_id,mix_design_id,site_id,order_no',
            'salesOrder.customer:id,legal_name',
            'salesOrder.site:id,name',
            'salesOrder.plant:id,entity_id,name,logo_path',
            'salesOrder.plant.entity:id,legal_name',
            'salesOrder.plant.addresses',
            'salesOrder.mixDesign:id,design_name,design_code,design_type',
            'salesOrder.mixDesign.concrete_grade:id,name',
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

        $this->ensurePlantScope($batch->salesOrder);

        $settings = \App\Models\CustomSetting::getForModule($batch->salesOrder->plant_id, 'batching');

        return view('pdfs.batches.dispatch_token', [
            'batch' => $batch,
            'isPreview' => true,
            'settings' => $settings,
        ]);
    }

    public function downloadDispatchTokenPdf(Batch $batch)
    {
        $batch->load([
            'salesOrder:id,prefix,plant_id,customer_id,mix_design_id,site_id,order_no',
            'salesOrder.customer:id,legal_name',
            'salesOrder.site:id,name',
            'salesOrder.plant:id,entity_id,name,logo_path',
            'salesOrder.plant.entity:id,legal_name',
            'salesOrder.plant.addresses',
            'salesOrder.mixDesign:id,design_name,design_code,design_type',
            'salesOrder.mixDesign.concrete_grade:id,name',
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

        $this->ensurePlantScope($batch->salesOrder);

        $settings = \App\Models\CustomSetting::getForModule($batch->salesOrder->plant_id, 'batching');

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
            'salesOrder:id,prefix,plant_id,customer_id,mix_design_id,site_id,order_no,customer_po_id',
            'salesOrder.customer:id,legal_name',
            'salesOrder.site:id,name',
            'salesOrder.plant:id,entity_id,name,logo_path,seal_sign_path,upi_qr_path',
            'salesOrder.plant.entity:id,legal_name',
            'salesOrder.plant.addresses',
            'salesOrder.mixDesign:id,design_name,design_code,design_type',
            'salesOrder.mixDesign.concrete_grade:id,name',
            'dispatches:id,batch_id,truck_id,driver_id,transport_id,load_site_id,sales_executive_id,empty_weight_truck,empty_time,loaded_weight_truck,load_time,net_weight,load_rate,load_untax_amount,load_tax_amount,load_total_amount,discount_amount,transport_expenses,adjustment_amount,round_off,load_tax_id',
            'dispatches.truck:id,registration',
            'dispatches.driver:id,first_name,last_name',
            'dispatches.salesExecutive:id,first_name,last_name',
            'dispatches.transport:id,legal_name',
            'dispatches.loadSite:id,name',
            'dispatches.loadTax',
            'materials:id,batch_id,product_id,material_name,target_qty,actual_qty,deviation_quantity,uom_id',
            'materials.product:id,title',
            'materials.uom:id,unit_code',
            'operator:id,first_name,last_name'
        ]);

        $this->ensurePlantScope($batch->salesOrder);

        $settings = \App\Models\CustomSetting::getForModule($batch->salesOrder->plant_id, 'batching');
        return view('pdfs.batches.delivery_token', [
            'batch' => $batch,
            'isPreview' => true,
            'settings' => $settings,
        ]);
    }

    public function downloadDeliveryTokenPdf(Batch $batch)
    {
        $batch->load([
            'salesOrder:id,prefix,plant_id,customer_id,mix_design_id,site_id,order_no',
            'salesOrder.customer:id,legal_name',
            'salesOrder.site:id,name',
            'salesOrder.plant:id,entity_id,name,logo_path,seal_sign_path,upi_qr_path',
            'salesOrder.plant.entity:id,legal_name',
            'salesOrder.plant.addresses',
            'salesOrder.mixDesign:id,design_name,design_code,design_type',
            'salesOrder.mixDesign.concrete_grade:id,name',
            'dispatches:id,batch_id,truck_id,driver_id,transport_id,load_site_id,sales_executive_id,empty_weight_truck,empty_time,loaded_weight_truck,load_time,net_weight,load_rate,load_untax_amount,load_tax_amount,load_total_amount,discount_amount,transport_expenses,adjustment_amount,round_off,load_tax_id',
            'dispatches.truck:id,registration',
            'dispatches.driver:id,first_name,last_name',
            'dispatches.salesExecutive:id,first_name,last_name',
            'dispatches.transport:id,legal_name',
            'dispatches.loadSite:id,name',
            'dispatches.loadTax',
            'materials:id,batch_id,product_id,material_name,target_qty,actual_qty,deviation_quantity,uom_id',
            'materials.product:id,title',
            'materials.uom:id,unit_code',
            'operator:id,first_name,last_name'
        ]);

        $this->ensurePlantScope($batch->salesOrder);

        $settings = \App\Models\CustomSetting::getForModule($batch->salesOrder->plant_id, 'batching');
        $pdf = Pdf::loadView('pdfs.batches.delivery_token', [
            'batch' => $batch,
            'isPreview' => false,
            'settings' => $settings,
        ])->setPaper('a4', 'portrait');

        $filename = sprintf('delivery-token-%s.pdf', $batch->batch_no ?? $batch->id);
        return $pdf->download($filename);
    }

    /**
     * Gate Pass – view (thermal 80mm).
     */
    public function gatePass(Batch $batch)
    {
        $batch->load([
            'workOrder:id,prefix,plant_id,customer_id,mix_design_id,site_id,order_no',
            'workOrder.customer:id,legal_name',
            'workOrder.site:id,name',
            'workOrder.plant:id,name,logo_path',
            'workOrder.plant.addresses',
            'workOrder.mixDesign:id,design_name,design_code',
            'workOrder.mixDesign.concrete_grade:id,name',
            'dispatches:id,batch_id,truck_id,driver_id,transport_id,empty_weight_truck,empty_time,loaded_weight_truck,load_time,net_weight',
            'dispatches.truck:id,registration',
            'dispatches.driver:id,first_name,last_name',
            'dispatches.transport:id,legal_name',
            'materials:id,batch_id,product_id,material_name,target_qty,actual_qty,deviation_quantity,uom_id',
            'materials.product:id,title',
            'operator:id,first_name,last_name',
        ]);

        $this->ensurePlantScope($batch->workOrder);

        $settings = \App\Models\CustomSetting::getForModule($batch->workOrder->plant_id, 'batching');

        return view('pdfs.batches.gate_pass', [
            'batch'     => $batch,
            'settings'  => $settings,
            'isPreview' => true,
        ]);
    }

    /**
     * Gate Pass – PDF download.
     */
    public function downloadGatePassPdf(Batch $batch)
    {
        $batch->load([
            'workOrder:id,prefix,plant_id,customer_id,mix_design_id,site_id,order_no',
            'workOrder.customer:id,legal_name',
            'workOrder.site:id,name',
            'workOrder.plant:id,name,logo_path',
            'workOrder.plant.addresses',
            'workOrder.mixDesign:id,design_name,design_code',
            'workOrder.mixDesign.concrete_grade:id,name',
            'dispatches:id,batch_id,truck_id,driver_id,transport_id,empty_weight_truck,empty_time,loaded_weight_truck,load_time,net_weight',
            'dispatches.truck:id,registration',
            'dispatches.driver:id,first_name,last_name',
            'dispatches.transport:id,legal_name',
            'materials:id,batch_id,product_id,material_name,target_qty,actual_qty,deviation_quantity,uom_id',
            'materials.product:id,title',
            'operator:id,first_name,last_name',
        ]);

        $this->ensurePlantScope($batch->workOrder);

        $settings = \App\Models\CustomSetting::getForModule($batch->workOrder->plant_id, 'batching');
        $materialsCount = $batch->materials->count();
        $height = 480 + ($materialsCount * 20);

        $pdf = Pdf::loadView('pdfs.batches.gate_pass', [
            'batch'     => $batch,
            'settings'  => $settings,
            'isPreview' => false,
        ])->setPaper([0, 0, 226.77, $height], 'portrait');

        $filename = sprintf('gate-pass-B%s.pdf', str_pad($batch->batch_no ?? $batch->id, 4, '0', STR_PAD_LEFT));
        return $pdf->download($filename);
    }

    private function resolveBatchSheetBatch(Batch $batch): Batch
    {
        $batch->load([
            'salesOrder.customer',
            'salesOrder.site',
            'salesOrder.plant.entity',
            'salesOrder.mixDesign.concrete_grade',
            'dispatches.truck',
            'dispatches.driver',
            'materials.product.category',
            'materials.uom',
        ]);

        $this->ensurePlantScope($batch->salesOrder);

        return $batch;
    }

    private function prepareBatchSheetData(Batch $batch): array
    {
        return $batch->getReportData();
    }

    private function syncMaterials(Batch $batch, array $materials): void
    {
        $plantId = $batch->plant_id ?? session('active_plant_id');
        
        // Remove existing material records for this batch using soft delete to prevent duplicate or orphaned rows
        $batch->materials()->delete();

        if (empty($materials)) {
            $salesOrder = $batch->salesOrder ?: SalesOrder::find($batch->sales_order_id);
            if ($salesOrder && $salesOrder->mixDesign && $salesOrder->mixDesign->items) {
                foreach ($salesOrder->mixDesign->items as $item) {
                    if (empty($item->product_id)) continue;
                    $batch->materials()->create([
                        'batch_id' => $batch->id,
                        'plant_id' => $plantId,
                        'product_id' => $item->product_id,
                        'material_name' => $item->product?->title ?? 'Material',
                        'target_qty' => (float)($item->cross_quantity ?: $item->actual_quantity ?: $item->quantity ?: 0) * (float)$batch->batch_size,
                        'actual_qty' => 0,
                        'deviation_quantity' => 0,
                        'uom_id' => $item->uom_id ?: $item->product?->unit_id,
                    ]);
                }
            }
            return;
        }

        $incomingProductIds = collect($materials)->pluck('product_id')->filter()->unique()->toArray();
        $productTitles = !empty($incomingProductIds) 
            ? Product::query()->whereIn('id', $incomingProductIds)->pluck('title', 'id') 
            : collect();

        foreach ($materials as $item) {
            if (empty($item['product_id'])) continue;
            
            $materialName = $item['material_name'] ?? ($productTitles[$item['product_id']] ?? 'Material');

            $batch->materials()->create([
                'batch_id' => $batch->id,
                'plant_id' => $plantId,
                'product_id' => $item['product_id'],
                'material_name' => $materialName,
                'target_qty' => $item['target_qty'] ?? 0,
                'actual_qty' => $item['actual_qty'] ?? 0,
                'deviation_quantity' => $item['deviation_quantity'] ?? 0,
                'uom_id' => $item['uom_id'],
            ]);
        }
    }

    private function checkStock(Batch $batch, array $newMaterials, array $oldMaterials = [], bool $wasDeducted = false): void
    {
        $plantId = $batch->salesOrder->plant_id ?? session('active_plant_id');

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
        $plantId = $batch->salesOrder->plant_id ?? session('active_plant_id');

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

    private function ensurePlantScope(SalesOrder $salesOrder): void
    {
        if ((int) $salesOrder->plant_id !== (int) session('active_plant_id')) {
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
                'salesOrder.customer',
                'salesOrder.mixDesign',
                'salesOrder.site',
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

    /**
     * Send Batch Completed email notification to the customer or fallback to authenticated user.
     */
    private function sendBatchCompletedMail(Batch $batch): void
    {
        try {
            $customer = $batch->salesOrder?->customer;
            $customerEmail = null;
            if ($customer) {
                $contact = $customer->contacts()->where('is_primary', true)->first() ?? $customer->contacts()->first();
                $customerEmail = $contact?->email ?? null;
            }

            if ($customerEmail) {
                \Illuminate\Support\Facades\Notification::route('mail', $customerEmail)
                    ->notify(new \App\Notifications\BatchCompletedNotification($batch));
            } else {
                auth()->user()?->notify(new \App\Notifications\BatchCompletedNotification($batch));
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to send batch completed mail: " . $e->getMessage());
        }
    }

    /**
     * Get the latest empty weight of a truck.
     */
    public function getTruckEmptyWeight(Request $request)
    {
        $request->validate([
            'truck_id' => 'required|exists:mm_machines,id',
        ]);

        $latestWeight = \App\Models\TruckEmptyWeight::where('truck_id', $request->truck_id)
            ->where('plant_id', session('active_plant_id'))
            ->latest('created_at')
            ->first();

        return response()->json([
            'empty_weight' => $latestWeight ? (float) $latestWeight->empty_weight : 0.00,
        ]);
    }

    /**
     * Store a new truck empty weight.
     */
    public function storeTruckEmptyWeight(Request $request)
    {
        $request->validate([
            'truck_id' => 'required|exists:mm_machines,id',
            'empty_weight' => 'required|numeric|min:0',
        ]);

        $weight = \App\Models\TruckEmptyWeight::create([
            'truck_id' => $request->truck_id,
            'empty_weight' => $request->empty_weight,
            'plant_id' => session('active_plant_id'),
        ]);

        return response()->json([
            'message' => 'Empty weight registered successfully.',
            'empty_weight' => (float) $weight->empty_weight,
        ]);
    }

    /**
     * Public Gate Pass Verification View (Guest Access).
     */
    public function publicVerifyGatePass(Batch $batch, string $hash)
    {
        // Cryptographic verification of URL signature to prevent ID enumeration
        $expectedHash = md5($batch->id . 'gatepass-secret-salt-2026');
        if ($hash !== $expectedHash) {
            abort(404, 'Invalid verification link.');
        }

        $batch->load([
            'workOrder:id,prefix,plant_id,customer_id,mix_design_id,site_id,order_no',
            'workOrder.customer:id,legal_name',
            'workOrder.site:id,name',
            'workOrder.plant:id,name,logo_path',
            'workOrder.plant.addresses',
            'workOrder.mixDesign:id,design_name,design_code',
            'workOrder.mixDesign.concrete_grade:id,name',
            'dispatches:id,batch_id,truck_id,driver_id,transport_id,empty_weight_truck,empty_time,loaded_weight_truck,load_time,net_weight',
            'dispatches.truck:id,registration',
            'dispatches.driver:id,first_name,last_name',
            'dispatches.transport:id,legal_name',
            'materials:id,batch_id,product_id,material_name,target_qty,actual_qty,deviation_quantity,uom_id',
            'materials.product:id,title',
            'operator:id,first_name,last_name',
        ]);

        $settings = \App\Models\CustomSetting::getForModule($batch->workOrder->plant_id, 'batching');

        return view('gatepass.verify', [
            'batch'     => $batch,
            'hash'      => $hash,
            'settings'  => $settings,
            'isPreview' => true,
        ]);
    }

    /**
     * Public Gate Pass Confirmation Action (Guest Access).
     */
    public function publicConfirmGatePass(Batch $batch, string $hash)
    {
        $expectedHash = md5($batch->id . 'gatepass-secret-salt-2026');
        if ($hash !== $expectedHash) {
            abort(404, 'Invalid verification link.');
        }

        if (!$batch->is_verified) {
            $batch->is_verified = true;
            $batch->verified_at = now();
            $batch->save();

            // Optional: Log activity
            try {
                activity()
                    ->performedOn($batch)
                    ->log('Gate Pass verified via QR scan (public access).');
            } catch (\Exception $e) {
                // Ignore log failures if package is not configured
            }
        }

        return redirect()->route('public.gatepass.verify', ['batch' => $batch->id, 'hash' => $hash])
            ->with('success', 'Trip successfully verified.');
    }
}