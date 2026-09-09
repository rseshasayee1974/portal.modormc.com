<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\ConcreteBatchingSchedule;
use App\Models\Dispatch;
use App\Models\Machine;
use App\Models\MixDesign;
use App\Models\Personnel;
use App\Models\Plant;
use App\Models\PumpBoomDeploymentSchedule;
use App\Models\SalesOrder;
use App\Models\Site;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Concerns\AuthorizesModule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ConcreteBatchingScheduleController extends Controller
{
    use AuthorizesModule;

    protected string $module = 'batching_schedules';

    public const PUMP_TYPE_OPTIONS = [
        ['value' => 'boom_pump', 'label' => 'Boom Pump (Truck-Mounted Articulated)'],
        ['value' => 'line_pump', 'label' => 'Line Pump (Ground Pipeline)'],
        ['value' => 'stationary_pump', 'label' => 'Stationary High-Rise Pump'],
        ['value' => 'crane_bucket', 'label' => 'Crane & Bucket Pour'],
        ['value' => 'direct_pour', 'label' => 'Direct Chute Discharge'],
    ];

    /**
     * Resolve active plant ID from session or user default.
     */
    protected function getActivePlantId(): int
    {
        return (int) (session('active_plant_id') ?? auth()->user()->default_plant_id ?? 1);
    }

    /**
     * Default eager-loaded relations for schedule query.
     */
    protected function getScheduleRelations(): array
    {
        return [
            'site:id,name,site_address_1',
            'mixDesign:id,design_name,design_code',
            'vehicle:id,registration,vehicle_model',
            'driver:id,first_name,last_name,employee_code,mobile,designation_id',
            'driver.designation:id,name',
            'pumpVehicle:id,registration,vehicle_model',
            'salesOrder:id,order_no,prefix',
            'dispatch:id,dispatch_no,dispatch_status',
        ];
    }

    /**
     * Normalize pump_type string.
     */
    protected function normalizePumpTypeInput(Request $request): void
    {
        if ($request->has('pump_type')) {
            $request->merge([
                'pump_type' => strtolower(trim(str_replace(' ', '_', (string) $request->input('pump_type')))),
            ]);
        }
    }

    /**
     * Common validation rules for schedule creation and update.
     */
    protected function getValidationRules(): array
    {
        return [
            'schedule_date'    => 'required|date',
            'pour_reference'   => 'required|string|max:100',
            'site_id'          => 'required|exists:mm_sites,id',
            'mix_design_id'    => 'required|exists:mm_mix_designs,id',
            'qty_m3'           => 'required|numeric|min:0.1',
            'order_volume_m3'  => 'required|numeric|min:0.1',
            'vehicle_id'       => 'nullable|exists:mm_machines,id',
            'driver_id'        => 'nullable|exists:mm_personnels,id',
            'pump_type'        => ['required', Rule::in(ConcreteBatchingSchedule::PUMP_TYPES)],
            'pump_vehicle_id'  => 'nullable|exists:mm_machines,id',
            'sales_order_id'   => 'nullable|exists:mm_sales_orders,id',
            'dispatch_id'      => 'nullable|exists:mm_dispatches,id',
            'batching_time'    => 'nullable|date',
            'dispatch_time'    => 'nullable|date',
            'eta_site'         => 'nullable|date',
            'unloading_start'  => 'nullable|date',
            'unloading_end'    => 'nullable|date',
            'status'           => 'nullable|in:scheduled,batching,in_transit,on_site,pouring,completed,cancelled',
            'notes'            => 'nullable|string',
        ];
    }

    /**
     * Validate chronological time order and normalize timestamps to Y-m-d H:i:s.
     */
    protected function validateAndNormalizeTimes(array &$validated): void
    {
        if (!empty($validated['batching_time']) && !empty($validated['dispatch_time'])) {
            if (Carbon::parse($validated['batching_time'])->gt(Carbon::parse($validated['dispatch_time']))) {
                throw ValidationException::withMessages([
                    'dispatch_time' => ['Dispatch time cannot be earlier than batching time.']
                ]);
            }
        }
        if (!empty($validated['dispatch_time']) && !empty($validated['eta_site'])) {
            if (Carbon::parse($validated['dispatch_time'])->gt(Carbon::parse($validated['eta_site']))) {
                throw ValidationException::withMessages([
                    'eta_site' => ['Estimated site arrival (ETA) cannot be earlier than dispatch time.']
                ]);
            }
        }
        if (!empty($validated['batching_time']) && !empty($validated['eta_site']) && empty($validated['dispatch_time'])) {
            if (Carbon::parse($validated['batching_time'])->gt(Carbon::parse($validated['eta_site']))) {
                throw ValidationException::withMessages([
                    'eta_site' => ['Estimated site arrival (ETA) cannot be earlier than batching time.']
                ]);
            }
        }
        if (!empty($validated['unloading_start']) && !empty($validated['unloading_end'])) {
            if (Carbon::parse($validated['unloading_start'])->gt(Carbon::parse($validated['unloading_end']))) {
                throw ValidationException::withMessages([
                    'unloading_end' => ['Unloading end time cannot be earlier than unloading start time.']
                ]);
            }
        }

        foreach (['batching_time', 'dispatch_time', 'eta_site', 'unloading_start', 'unloading_end'] as $dtField) {
            if (!empty($validated[$dtField])) {
                try {
                    $validated[$dtField] = Carbon::parse($validated[$dtField])->format('Y-m-d H:i:s');
                } catch (\Exception $e) {
                    $validated[$dtField] = null;
                }
            }
        }
    }

    /**
     * Map schedule status to Batch model status.
     */
    protected function mapBatchStatus(string $status): string
    {
        return match ($status) {
            'batching'  => Batch::STATUS_LOADING,
            'in_transit', 'on_site', 'pouring' => Batch::STATUS_DISPATCHED,
            'completed' => Batch::STATUS_COMPLETED,
            'cancelled' => Batch::STATUS_CANCELLED,
            default     => Batch::STATUS_PLANNED,
        };
    }

    /**
     * Map schedule status to Dispatch ticket status.
     */
    protected function mapDispatchStatus(string $status, ?string $fallback = 'Draft'): string
    {
        return match ($status) {
            'batching'   => 'Loading',
            'in_transit' => 'In Transit',
            'on_site'    => 'On Site',
            'pouring'    => 'Pouring',
            'completed'  => 'Delivered',
            'cancelled'  => 'Cancelled',
            default      => $fallback ?: 'Draft',
        };
    }

    /**
     * Generate next dispatch number & prefix matching DispatchController.
     */
    protected function getNextDispatchDetails(int $plantId): array
    {
        $currentDate = now();
        $startYear = $currentDate->month >= 4 ? $currentDate->year : $currentDate->year - 1;
        $fyString = substr($startYear, -2) . substr($startYear + 1, -2);
        $prefix = "DP-{$fyString}-";

        $maxNumber = Dispatch::where('plant_id', $plantId)
            ->where('prefix', $prefix)
            ->max(DB::raw('CAST(dispatch_no AS UNSIGNED)'));

        return [
            'prefix'     => $prefix,
            'nextNumber' => (string) (($maxNumber ?: 0) + 1),
        ];
    }

    /**
     * Display the main Batching & Dispatch Scheduling Dashboard.
     */
    public function index(Request $request): Response
    {
        $this->authorizeModule('view');

        $plantId = $this->getActivePlantId();
        $scheduleDate = $request->input('schedule_date', now()->toDateString());

        return Inertia::render('Production/BatchingScheduleDashboard', [
            'plants'         => Plant::all(['id', 'name']),
            'activePlantId'  => $plantId,
            'initialDate'    => $scheduleDate,
            'initialFilters' => [
                'schedule_date'  => $scheduleDate,
                'status'         => $request->input('status', 'all'),
                'pour_reference' => $request->input('pour_reference', ''),
                'site_id'        => $request->input('site_id', ''),
            ]
        ]);
    }

    /**
     * API endpoint returning filtered schedules, grouped pour tracking, and KPIs.
     */
    public function getData(Request $request): JsonResponse
    {
        $this->authorizeModule('view');

        $plantId = $this->getActivePlantId();
        $scheduleDate  = $request->input('schedule_date', now()->toDateString());
        $status        = $request->input('status', 'all');
        $pourReference = $request->input('pour_reference');
        $siteId        = $request->input('site_id');

        $query = ConcreteBatchingSchedule::where('plant_id', $plantId)
            ->with($this->getScheduleRelations());

        if ($scheduleDate) {
            $query->whereDate('schedule_date', $scheduleDate);
        }

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($pourReference) {
            $query->where('pour_reference', 'like', "%{$pourReference}%");
        }

        if ($siteId) {
            $query->where('site_id', $siteId);
        }

        $schedules = $query->orderBy('schedule_date', 'asc')
            ->orderBy('batching_time', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $metrics = [
            'total_scheduled_m3'      => round((float) $schedules->sum('qty_m3'), 3),
            'total_delivered_m3'      => round((float) $schedules->where('status', 'completed')->sum('qty_m3'), 3),
            'active_in_transit_tms'   => $schedules->where('status', 'in_transit')->count(),
            'active_pouring_tms'      => $schedules->where('status', 'pouring')->count(),
            'hydration_warning_count' => $schedules->filter(fn($s) => in_array($s->hydration_status, ['warning', 'critical']))->count(),
        ];

        return response()->json([
            'schedules' => $schedules,
            'pours'     => $this->aggregatePourTracking($schedules),
            'metrics'   => $metrics,
        ]);
    }

    /**
     * Master dropdowns needed for scheduling modals.
     */
    public function dropdowns(Request $request): JsonResponse
    {
        $this->authorizeModule('view');

        $plantId = $this->getActivePlantId();

        $sites = Site::where('plant_id', $plantId)->whereNull('deleted_at')->get(['id', 'name', 'site_address_1']);
        $mixDesigns = MixDesign::where('plant_id', $plantId)->whereNull('deleted_at')->get(['id', 'design_name', 'design_code']);
        $vehicles = Machine::where('plant_id', $plantId)->whereNull('deleted_at')->get(['id', 'registration', 'vehicle_model', 'vehicle_type', 'capacity']);
        $drivers = Personnel::where('plant_id', $plantId)->whereNull('deleted_at')->whereRelation('designation', 'name', 'like', '%Driver%')->get(['id', 'first_name', 'last_name', 'employee_code', 'mobile']);

        $salesOrders = SalesOrder::where('plant_id', $plantId)
            ->whereNull('deleted_at')
            ->with(['site:id,name', 'mixDesign:id,design_name,design_code', 'customer:id,legal_name'])
            ->get()
            ->map(function ($so) {
                $dispatched = (float) Dispatch::where('sales_order_id', $so->id)
                    ->whereNotIn('dispatch_status', ['Cancelled'])
                    ->sum('delivered_qty');
                $totalQty = (float) $so->total_qty;

                return [
                    'id'             => $so->id,
                    'order_number'   => ($so->prefix ?? '') . $so->order_no,
                    'total_qty'      => $totalQty,
                    'dispatched_qty' => $dispatched,
                    'remaining_qty'  => max(0, $totalQty - $dispatched),
                    'site_id'        => $so->site_id,
                    'site_name'      => $so->site?->name,
                    'mix_design_id'  => $so->mix_design_id,
                    'mix_name'       => $so->mixDesign?->design_name,
                    'customer_id'    => $so->customer_id,
                    'customer_name'  => $so->customer?->legal_name,
                ];
            });

        $dispatches = Dispatch::where('plant_id', $plantId)
            ->whereNull('deleted_at')
            ->whereNotIn('dispatch_status', ['Cancelled'])
            ->with([
                'batch:id,batch_no,batch_size,status',
                'unloadSite:id,name',
                'mixDesign:id,design_name,design_code',
                'salesOrder:id,order_no,prefix,total_qty',
                'truck:id,registration,vehicle_model',
                'driver:id,first_name,last_name,employee_code',
                'concretePump:id,registration,vehicle_model',
                'customer:id,legal_name',
            ])
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(function ($d) {
                $fullNo = ($d->prefix ?? '') . $d->dispatch_no;
                $siteName = $d->unloadSite?->name ?? 'Site';
                $qty = (float) $d->delivered_qty;
                $status = $d->dispatch_status ?? 'Draft';
                $batchNo = $d->batch?->batch_no ?? $d->batch_id;
                $batchTag = $batchNo ? " [Batch #{$batchNo}]" : '';
                $truckTag = $d->truck?->registration ? " • TM: {$d->truck->registration}" : '';

                return [
                    'id'              => $d->id,
                    'full_number'     => $fullNo,
                    'batch_id'        => $d->batch_id,
                    'batch_no'        => $batchNo,
                    'label'           => "{$fullNo}{$batchTag} — {$siteName} ({$qty} m³ | {$status}){$truckTag}",
                    'sales_order_id'  => $d->sales_order_id,
                    'site_id'         => $d->unload_site_id,
                    'site_name'       => $d->unloadSite?->name,
                    'mix_design_id'   => $d->mixdesign_id,
                    'mix_name'        => $d->mixDesign?->design_name,
                    'mix_code'        => $d->mixDesign?->design_code,
                    'vehicle_id'      => $d->truck_id,
                    'vehicle_reg'     => $d->truck?->registration,
                    'vehicle_model'   => $d->truck?->vehicle_model,
                    'driver_id'       => $d->driver_id,
                    'driver_name'     => $d->driver ? ($d->driver->first_name . ' ' . ($d->driver->last_name ?? '')) : null,
                    'pump_vehicle_id' => $d->concrete_pump,
                    'pump_type'       => $d->concrete_pump ? 'boom_pump' : 'direct_pour',
                    'qty_m3'          => $qty > 0 ? $qty : 6.0,
                    'order_volume_m3' => $d->salesOrder?->total_qty ? (float) $d->salesOrder->total_qty : ($qty > 0 ? $qty : 30.0),
                    'pour_reference'  => $d->dispatch_reference ?: ($d->salesOrder ? ($d->salesOrder->prefix ?? '') . $d->salesOrder->order_no : "DP-{$d->dispatch_no}"),
                    'dispatch_time'   => !empty($d->dispatch_time) ? Carbon::parse($d->dispatch_time)->format('Y-m-d\TH:i') : null,
                    'delivery_time'   => !empty($d->delivery_time) ? Carbon::parse($d->delivery_time)->format('Y-m-d\TH:i') : null,
                    'dispatch_status' => $d->dispatch_status,
                    'customer_id'     => $d->customer_id,
                    'customer_name'   => $d->customer?->legal_name,
                ];
            });

        return response()->json([
            'sites'       => $sites,
            'mixDesigns'  => $mixDesigns,
            'vehicles'    => $vehicles,
            'drivers'     => $drivers,
            'salesOrders' => $salesOrders,
            'dispatches'  => $dispatches,
            'pumpTypes'   => self::PUMP_TYPE_OPTIONS,
        ]);
    }

    /**
     * Store a new concrete batching schedule slot and create/link the Batch & Dispatch models.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorizeModule('create');

        $plantId = $this->getActivePlantId();
        $this->normalizePumpTypeInput($request);

        $validated = $request->validate($this->getValidationRules());
        $this->validateAndNormalizeTimes($validated);

        $ignoreDispatchId = !empty($validated['dispatch_id']) ? (int) $validated['dispatch_id'] : null;

        $this->validateSalesOrderCapacity($plantId, $validated, null, $ignoreDispatchId);
        $this->validateTransitMixerOverlap($plantId, $validated, null, $ignoreDispatchId);
        $this->validateDriverOverlap($plantId, $validated, null, $ignoreDispatchId);
        $this->validatePumpConflict($plantId, $validated);

        return DB::transaction(function () use ($validated, $plantId) {
            $chosenStatus = $validated['status'] ?? 'scheduled';
            $salesOrder = !empty($validated['sales_order_id']) ? SalesOrder::find($validated['sales_order_id']) : null;

            // 1. Create Real Batch
            $nextBatchNo = (Batch::where('plant_id', $plantId)->max('batch_no') ?? 0) + 1;
            $batch = Batch::create([
                'plant_id'       => $plantId,
                'sales_order_id' => $validated['sales_order_id'] ?? null,
                'batch_no'       => $nextBatchNo,
                'batch_size'     => $validated['qty_m3'],
                'start_time'     => !empty($validated['batching_time']) ? $validated['batching_time'] : now()->format('Y-m-d H:i:s'),
                'end_time'       => $chosenStatus === 'completed' ? (!empty($validated['unloading_end']) ? $validated['unloading_end'] : now()->format('Y-m-d H:i:s')) : null,
                'operator_id'    => $validated['driver_id'] ?? auth()->id(),
                'shift'          => 'A',
                'status'         => $this->mapBatchStatus($chosenStatus),
            ]);

            // 2. Link or Create Dispatch
            $dispatch = null;
            if (!empty($validated['dispatch_id'])) {
                $dispatch = Dispatch::where('id', $validated['dispatch_id'])->where('plant_id', $plantId)->first();
                if ($dispatch) {
                    $dispatchUpdates = [
                        'batch_id'        => $batch->id,
                        'delivered_qty'   => $validated['qty_m3'],
                        'unload_site_id'  => $validated['site_id'],
                        'mixdesign_id'    => $validated['mix_design_id'],
                        'dispatch_status' => $this->mapDispatchStatus($chosenStatus, $dispatch->dispatch_status),
                    ];
                    if (!empty($validated['vehicle_id'])) $dispatchUpdates['truck_id'] = $validated['vehicle_id'];
                    if (!empty($validated['driver_id'])) $dispatchUpdates['driver_id'] = $validated['driver_id'];
                    if (!empty($validated['pump_vehicle_id'])) $dispatchUpdates['concrete_pump'] = $validated['pump_vehicle_id'];
                    if (!empty($validated['dispatch_time'])) $dispatchUpdates['dispatch_time'] = $validated['dispatch_time'];
                    if ($chosenStatus === 'completed' && !empty($validated['unloading_end'])) $dispatchUpdates['delivery_time'] = $validated['unloading_end'];
                    $dispatch->update($dispatchUpdates);
                }
            } elseif (!empty($validated['vehicle_id'])) {
                $dispatchDetails = $this->getNextDispatchDetails($plantId);
                $loadRate = (float) ($salesOrder?->rate ?? 0);
                $untaxAmount = round($loadRate * (float)$validated['qty_m3'], 2);

                $dispatch = Dispatch::create([
                    'plant_id'          => $plantId,
                    'batch_id'          => $batch->id,
                    'sales_order_id'    => $validated['sales_order_id'] ?? null,
                    'customer_id'       => $salesOrder?->customer_id,
                    'load_site_id'      => $plantId,
                    'unload_site_id'    => $validated['site_id'],
                    'mixdesign_id'      => $validated['mix_design_id'],
                    'truck_id'          => $validated['vehicle_id'],
                    'driver_id'         => $validated['driver_id'] ?? null,
                    'concrete_pump'     => $validated['pump_vehicle_id'] ?? null,
                    'delivered_qty'     => $validated['qty_m3'],
                    'dispatch_time'     => !empty($validated['dispatch_time']) ? $validated['dispatch_time'] : (!empty($validated['batching_time']) ? $validated['batching_time'] : now()->format('Y-m-d H:i:s')),
                    'delivery_time'     => $chosenStatus === 'completed' ? (!empty($validated['unloading_end']) ? $validated['unloading_end'] : now()->format('Y-m-d H:i:s')) : null,
                    'dispatch_status'   => $this->mapDispatchStatus($chosenStatus, 'Draft'),
                    'payment_mode'      => 'credit',
                    'prefix'            => $dispatchDetails['prefix'],
                    'dispatch_no'       => $dispatchDetails['nextNumber'],
                    'load_rate'         => $loadRate,
                    'load_tax_id'       => $salesOrder?->tax_id,
                    'load_untax_amount' => $untaxAmount,
                    'load_total_amount' => $untaxAmount,
                ]);
            }

            // 3. Create ConcreteBatchingSchedule
            $validated['plant_id']    = $plantId;
            $validated['batch_id']    = $batch->id;
            $validated['dispatch_id'] = $dispatch?->id ?? ($validated['dispatch_id'] ?? null);
            $validated['status']      = $chosenStatus;

            if (empty($validated['sales_order_id'])) {
                $existingDelivered = ConcreteBatchingSchedule::where('plant_id', $plantId)
                    ->where('pour_reference', $validated['pour_reference'])
                    ->where('status', '!=', 'cancelled')
                    ->sum('qty_m3');

                $validated['remaining_volume_m3'] = max(0, (float)$validated['order_volume_m3'] - ((float)$existingDelivered + (float)$validated['qty_m3']));
            }

            $schedule = ConcreteBatchingSchedule::create($validated);

            if ($salesOrder) {
                $salesOrder->refreshProduction();
            }

            return response()->json([
                'success'  => true,
                'message'  => "Batch #{$batch->batch_no} scheduled successfully.",
                'schedule' => $schedule->load(['site', 'mixDesign', 'vehicle', 'driver', 'pumpVehicle', 'batch', 'dispatch']),
            ]);
        });
    }

    /**
     * Update an existing schedule slot and sync Batch & Dispatch models.
     */
    public function update(Request $request, ConcreteBatchingSchedule $schedule): JsonResponse
    {
        $this->authorizeModule('update');

        $plantId = $schedule->plant_id ?? $this->getActivePlantId();
        $this->normalizePumpTypeInput($request);

        $validated = $request->validate($this->getValidationRules());
        $this->validateAndNormalizeTimes($validated);

        $this->validateSalesOrderCapacity($plantId, $validated, $schedule->id, $schedule->dispatch_id);
        $this->validateTransitMixerOverlap($plantId, $validated, $schedule->id, $schedule->dispatch_id);
        $this->validateDriverOverlap($plantId, $validated, $schedule->id, $schedule->dispatch_id);
        $this->validatePumpConflict($plantId, $validated, $schedule->id);

        $newStatus = $validated['status'] ?? $schedule->status ?? 'scheduled';
        $validated['status'] = $newStatus;

        return DB::transaction(function () use ($schedule, $validated, $newStatus) {
            $schedule->update($validated);

            // Sync Batch
            if ($schedule->batch_id) {
                $batchUpdates = [
                    'batch_size'     => $validated['qty_m3'],
                    'start_time'     => !empty($validated['batching_time']) ? $validated['batching_time'] : now()->format('Y-m-d H:i:s'),
                    'sales_order_id' => $validated['sales_order_id'] ?? null,
                    'status'         => $this->mapBatchStatus($newStatus),
                ];
                if ($newStatus === 'completed') {
                    $batchUpdates['end_time'] = !empty($validated['unloading_end']) ? $validated['unloading_end'] : now()->format('Y-m-d H:i:s');
                }
                Batch::where('id', $schedule->batch_id)->update($batchUpdates);
            }

            // Sync Dispatch
            if ($schedule->dispatch_id) {
                $dispatchUpdates = [
                    'truck_id'        => $validated['vehicle_id'] ?? null,
                    'driver_id'       => $validated['driver_id'] ?? null,
                    'unload_site_id'  => $validated['site_id'],
                    'mixdesign_id'    => $validated['mix_design_id'],
                    'concrete_pump'   => $validated['pump_vehicle_id'] ?? null,
                    'delivered_qty'   => $validated['qty_m3'],
                    'dispatch_status' => $this->mapDispatchStatus($newStatus, 'Draft'),
                ];
                if (!empty($validated['dispatch_time'])) {
                    $dispatchUpdates['dispatch_time'] = $validated['dispatch_time'];
                }
                if ($newStatus === 'completed') {
                    $dispatchUpdates['delivery_time'] = !empty($validated['unloading_end']) ? $validated['unloading_end'] : now()->format('Y-m-d H:i:s');
                }
                Dispatch::where('id', $schedule->dispatch_id)->update($dispatchUpdates);
            }

            if (!empty($schedule->sales_order_id)) {
                SalesOrder::find($schedule->sales_order_id)?->refreshProduction();
            }

            return response()->json([
                'success'  => true,
                'message'  => "Schedule slot #{$schedule->id} updated successfully.",
                'schedule' => $schedule->fresh(['site', 'mixDesign', 'vehicle', 'driver', 'pumpVehicle', 'batch', 'dispatch']),
            ]);
        });
    }

    /**
     * Fast 1-click status transition with automated timestamp milestone capture & Batch/Dispatch syncing.
     */
    public function updateStatus(Request $request, ConcreteBatchingSchedule $schedule): JsonResponse
    {
        $this->authorizeModule('update');

        $validated = $request->validate([
            'status' => 'required|in:scheduled,batching,in_transit,on_site,pouring,completed,cancelled',
        ]);

        $newStatus = $validated['status'];
        $updates = ['status' => $newStatus];
        $now = now();

        switch ($newStatus) {
            case 'batching':
                if (!$schedule->batching_time) $updates['batching_time'] = $now;
                break;
            case 'in_transit':
                if (!$schedule->dispatch_time) $updates['dispatch_time'] = $now;
                if (!$schedule->eta_site) $updates['eta_site'] = (clone $now)->addMinutes(35);
                break;
            case 'on_site':
                $updates['eta_site'] = $now;
                break;
            case 'pouring':
                if (!$schedule->unloading_start) $updates['unloading_start'] = $now;
                break;
            case 'completed':
                if (!$schedule->unloading_end) $updates['unloading_end'] = $now;
                break;
        }

        return DB::transaction(function () use ($schedule, $updates, $newStatus, $now) {
            $schedule->update($updates);

            if ($schedule->batch_id) {
                Batch::where('id', $schedule->batch_id)->update([
                    'status'   => $this->mapBatchStatus($newStatus),
                    'end_time' => $newStatus === 'completed' ? $now : null,
                ]);
            }

            if ($schedule->dispatch_id) {
                Dispatch::where('id', $schedule->dispatch_id)->update([
                    'dispatch_status' => $this->mapDispatchStatus($newStatus, 'Draft'),
                    'delivery_time'   => $newStatus === 'completed' ? $now : null,
                ]);
            }

            if (!empty($schedule->sales_order_id)) {
                SalesOrder::find($schedule->sales_order_id)?->refreshProduction();
            }

            return response()->json([
                'success'  => true,
                'message'  => "Status changed to " . ucfirst(str_replace('_', ' ', $newStatus)),
                'schedule' => $schedule->fresh(['site', 'mixDesign', 'vehicle', 'driver', 'pumpVehicle', 'batch', 'dispatch']),
            ]);
        });
    }

    /**
     * Convert an active scheduled batch into an official Dispatch ticket (mm_dispatches).
     */
    public function createDispatchTicket(Request $request, ConcreteBatchingSchedule $schedule): JsonResponse
    {
        $this->authorizeModule('create');

        if ($schedule->dispatch_id) {
            return response()->json([
                'success' => false,
                'message' => 'Dispatch ticket already exists for this trip slot.'
            ], 422);
        }

        return DB::transaction(function () use ($schedule) {
            $plantId = $schedule->plant_id;
            $dispatchDetails = $this->getNextDispatchDetails($plantId);
            $salesOrder = $schedule->salesOrder;

            $loadRate = (float) ($salesOrder?->rate ?? 0);
            $untaxAmount = round($loadRate * (float)$schedule->qty_m3, 2);

            $dispatch = Dispatch::create([
                'plant_id'            => $plantId,
                'batch_id'            => $schedule->batch_id,
                'sales_order_id'      => $schedule->sales_order_id,
                'customer_id'         => $salesOrder?->customer_id,
                'load_site_id'        => $plantId,
                'unload_site_id'      => $schedule->site_id,
                'mixdesign_id'        => $schedule->mix_design_id,
                'truck_id'            => $schedule->vehicle_id,
                'driver_id'           => $schedule->driver_id,
                'concrete_pump'       => $schedule->pump_vehicle_id,
                'delivered_qty'       => $schedule->qty_m3,
                'prefix'              => $dispatchDetails['prefix'],
                'dispatch_no'         => $dispatchDetails['nextNumber'],
                'dispatch_reference'  => $schedule->pour_reference,
                'dispatch_time'       => $schedule->dispatch_time ?? now(),
                'dispatch_status'     => 'In Transit',
                'payment_mode'        => 'credit',
                'load_rate'           => $loadRate,
                'load_tax_id'         => $salesOrder?->tax_id,
                'load_untax_amount'   => $untaxAmount,
                'load_total_amount'   => $untaxAmount,
            ]);

            $schedule->update([
                'dispatch_id'   => $dispatch->id,
                'status'        => 'in_transit',
                'dispatch_time' => $schedule->dispatch_time ?? now(),
            ]);

            if ($schedule->batch_id) {
                Batch::where('id', $schedule->batch_id)->update(['status' => Batch::STATUS_DISPATCHED]);
            }

            if ($salesOrder) {
                $salesOrder->refreshProduction();
            }

            return response()->json([
                'success'     => true,
                'message'     => "Dispatch Ticket #{$dispatch->prefix}{$dispatch->dispatch_no} generated successfully.",
                'dispatch_id' => $dispatch->id,
                'dispatch_no' => $dispatch->dispatch_no,
                'schedule'    => $schedule->fresh(['dispatch', 'vehicle', 'driver', 'batch']),
            ]);
        });
    }

    /**
     * Delete schedule slot.
     */
    public function destroy(ConcreteBatchingSchedule $schedule): JsonResponse
    {
        $this->authorizeModule('delete');

        $pourRef = $schedule->pour_reference;
        $plantId = $schedule->plant_id;
        $salesOrderId = $schedule->sales_order_id;

        DB::transaction(function () use ($schedule) {
            if ($schedule->batch_id) {
                Batch::where('id', $schedule->batch_id)->delete();
            }
            if ($schedule->dispatch_id) {
                Dispatch::where('id', $schedule->dispatch_id)->delete();
            }
            $schedule->delete();
        });

        ConcreteBatchingSchedule::recalculatePourBalances($pourRef, $plantId);

        if ($salesOrderId) {
            SalesOrder::find($salesOrderId)?->refreshProduction();
        }

        return response()->json([
            'success' => true,
            'message' => "Schedule slot deleted successfully.",
        ]);
    }

    /**
     * Helper to group and calculate pour progress.
     */
    private function aggregatePourTracking($schedules): array
    {
        $grouped = $schedules->groupBy('pour_reference');
        $pours = [];

        foreach ($grouped as $pourRef => $items) {
            $first = $items->first();
            $orderVol = (float) $first->order_volume_m3;
            $scheduledVol = (float) $items->where('status', '!=', 'cancelled')->sum('qty_m3');
            $deliveredVol = (float) $items->where('status', 'completed')->sum('qty_m3');
            $inProgressVol = (float) $items->whereIn('status', ['batching', 'in_transit', 'on_site', 'pouring'])->sum('qty_m3');
            $remainingVol = max(0, $orderVol - $deliveredVol);

            $progress = $orderVol > 0 ? min(100, round(($deliveredVol / $orderVol) * 100, 1)) : 0;

            $pours[] = [
                'pour_reference'        => $pourRef,
                'site_name'             => $first->site?->name ?? 'Unspecified Site',
                'mix_design_name'       => $first->mixDesign?->name ?? 'Standard Mix',
                'pump_type'             => $first->pump_type,
                'pump_vehicle'          => $first->pumpVehicle?->registration ?? 'None Assigned',
                'order_volume_m3'       => $orderVol,
                'scheduled_volume_m3'   => round($scheduledVol, 3),
                'delivered_volume_m3'   => round($deliveredVol, 3),
                'in_progress_volume_m3' => round($inProgressVol, 3),
                'remaining_volume_m3'   => round($remainingVol, 3),
                'progress_percent'      => $progress,
                'total_trips'           => $items->count(),
                'active_trips'          => $items->whereIn('status', ['batching', 'in_transit', 'on_site', 'pouring'])->count(),
                'completed_trips'       => $items->where('status', 'completed')->count(),
            ];
        }

        return $pours;
    }

    /**
     * Resolve the operational time-window for a Transit Mixer delivery trip cycle.
     */
    private function resolveTripWindow(array $data): array
    {
        $scheduleDate = $data['schedule_date'] ?? now()->toDateString();

        if (!empty($data['batching_time'])) {
            $start = Carbon::parse($data['batching_time']);
        } elseif (!empty($data['dispatch_time'])) {
            $start = Carbon::parse($data['dispatch_time']);
        } else {
            $start = Carbon::parse($scheduleDate . ' 08:00:00');
        }

        if (!empty($data['unloading_end'])) {
            $end = Carbon::parse($data['unloading_end'])->addMinutes(30);
        } elseif (!empty($data['unloading_start'])) {
            $end = Carbon::parse($data['unloading_start'])->addMinutes(60);
        } elseif (!empty($data['eta_site'])) {
            $end = Carbon::parse($data['eta_site'])->addMinutes(60);
        } else {
            $end = (clone $start)->addMinutes(90);
        }

        return [$start, $end];
    }

    /**
     * Validate that scheduled volume does not exceed Sales Order total capacity minus dispatches.
     */
    private function validateSalesOrderCapacity(int $plantId, array &$validated, ?int $ignoreScheduleId = null, ?int $ignoreDispatchId = null): void
    {
        if (empty($validated['sales_order_id'])) {
            return;
        }

        $salesOrder = SalesOrder::where('id', $validated['sales_order_id'])
            ->where('plant_id', $plantId)
            ->first();

        if (!$salesOrder) {
            return;
        }

        $totalOrderQty = (float) $salesOrder->total_qty;
        if ($totalOrderQty <= 0) {
            return;
        }

        $dispatchedQuery = Dispatch::where('sales_order_id', $salesOrder->id)
            ->whereNotIn('dispatch_status', ['Cancelled']);

        if ($ignoreDispatchId) {
            $dispatchedQuery->where('id', '!=', $ignoreDispatchId);
        }

        $alreadyDispatchedQty = (float) $dispatchedQuery->sum('delivered_qty');

        $scheduleQuery = ConcreteBatchingSchedule::where('sales_order_id', $salesOrder->id)
            ->where('plant_id', $plantId)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereNull('dispatch_id');

        if ($ignoreScheduleId) {
            $scheduleQuery->where('id', '!=', $ignoreScheduleId);
        }

        $otherScheduledQty = (float) $scheduleQuery->sum('qty_m3');

        $totalCommitted = $alreadyDispatchedQty + $otherScheduledQty;
        $remainingAvailable = max(0.0, round($totalOrderQty - $totalCommitted, 3));
        $requestedQty = round((float) $validated['qty_m3'], 3);

        if ($requestedQty > ($remainingAvailable + 0.001)) {
            $soName = ($salesOrder->prefix ?? '') . $salesOrder->order_no;
            throw ValidationException::withMessages([
                'qty_m3' => [
                    "Requested volume ({$requestedQty} m³) exceeds the remaining available capacity ({$remainingAvailable} m³) for Sales Order #{$soName}. Total Ordered: {$totalOrderQty} m³, Dispatched: {$alreadyDispatchedQty} m³, Already Scheduled: {$otherScheduledQty} m³."
                ]
            ]);
        }

        $validated['order_volume_m3'] = $totalOrderQty;
        $validated['remaining_volume_m3'] = max(0.0, round($totalOrderQty - ($totalCommitted + $requestedQty), 3));
    }

    /**
     * Validate that a Transit Mixer truck is not allocated to overlapping trip cycles or active dispatches.
     */
    private function validateTransitMixerOverlap(int $plantId, array $data, ?int $ignoreScheduleId = null, ?int $ignoreDispatchId = null): void
    {
        $vehicleId = $data['vehicle_id'] ?? null;
        if (!$vehicleId) {
            return;
        }

        [$newStart, $newEnd] = $this->resolveTripWindow($data);

        $query = ConcreteBatchingSchedule::where('plant_id', $plantId)
            ->where('vehicle_id', $vehicleId)
            ->whereNotIn('status', ['completed', 'cancelled']);

        if ($ignoreScheduleId) {
            $query->where('id', '!=', $ignoreScheduleId);
        }

        $candidates = $query->with('site')->get();

        foreach ($candidates as $existing) {
            [$exStart, $exEnd] = $this->resolveTripWindow($existing->toArray());

            if ($newStart->lt($exEnd) && $newEnd->gt($exStart)) {
                $vehicle  = Machine::find($vehicleId);
                $reg      = $vehicle?->registration ?? 'Selected TM Truck';
                $siteName = $existing->site?->name ?? 'Site';
                $timeSpan = $exStart->format('d-m-Y H:i') . ' to ' . $exEnd->format('H:i');
                throw ValidationException::withMessages([
                    'vehicle_id' => [
                        "Transit Mixer {$reg} is already allocated to trip #{$existing->id} (Pour: '{$existing->pour_reference}' at '{$siteName}') from {$timeSpan}. A transit mixer cannot be assigned to overlapping delivery trips."
                    ]
                ]);
            }
        }

        $dispatchQuery = Dispatch::where('plant_id', $plantId)
            ->where('truck_id', $vehicleId)
            ->whereNotIn('dispatch_status', ['Delivered', 'Cancelled', 'Invoiced']);

        if ($ignoreDispatchId) {
            $dispatchQuery->where('id', '!=', $ignoreDispatchId);
        }

        $trackedDispatchIds = $candidates->pluck('dispatch_id')->filter()->toArray();
        if (!empty($trackedDispatchIds)) {
            $dispatchQuery->whereNotIn('id', $trackedDispatchIds);
        }

        $activeDispatches = $dispatchQuery->with('unloadSite')->get();

        foreach ($activeDispatches as $disp) {
            $dispStart = $disp->dispatch_time ?? $disp->load_time ?? $disp->created_at ?? now();
            $dispEnd = $disp->delivery_time ?? (clone $dispStart)->addMinutes(90);

            if ($newStart->lt($dispEnd) && $newEnd->gt($dispStart)) {
                $vehicle = Machine::find($vehicleId);
                $reg = $vehicle?->registration ?? 'Selected TM Truck';
                $siteName = $disp->unloadSite?->name ?? 'Site';
                $timeSpan = $dispStart->format('d-m-Y H:i') . ' to ' . $dispEnd->format('H:i');
                throw ValidationException::withMessages([
                    'vehicle_id' => [
                        "Transit Mixer {$reg} is currently dispatched on Ticket #{$disp->prefix}{$disp->dispatch_no} (Status: {$disp->dispatch_status} at '{$siteName}') from {$timeSpan}. A transit mixer cannot be assigned while out on a dispatch."
                    ]
                ]);
            }
        }
    }

    /**
     * Validate that a driver is not allocated to overlapping trips or active dispatches.
     */
    private function validateDriverOverlap(int $plantId, array $data, ?int $ignoreScheduleId = null, ?int $ignoreDispatchId = null): void
    {
        $driverId = $data['driver_id'] ?? null;
        if (!$driverId) {
            return;
        }

        [$newStart, $newEnd] = $this->resolveTripWindow($data);

        $query = ConcreteBatchingSchedule::where('plant_id', $plantId)
            ->where('driver_id', $driverId)
            ->whereNotIn('status', ['completed', 'cancelled']);

        if ($ignoreScheduleId) {
            $query->where('id', '!=', $ignoreScheduleId);
        }

        $candidates = $query->with('site')->get();

        foreach ($candidates as $existing) {
            [$exStart, $exEnd] = $this->resolveTripWindow($existing->toArray());

            if ($newStart->lt($exEnd) && $newEnd->gt($exStart)) {
                $driver     = Personnel::find($driverId);
                $driverName = $driver ? $driver->first_name . ' ' . ($driver->last_name ?? '') : 'Selected Driver';
                $siteName   = $existing->site?->name ?? 'Site';
                $timeSpan   = $exStart->format('d-m-Y H:i') . ' to ' . $exEnd->format('H:i');
                throw ValidationException::withMessages([
                    'driver_id' => [
                        "Driver {$driverName} is already assigned to trip #{$existing->id} (Pour: '{$existing->pour_reference}' at '{$siteName}') from {$timeSpan}. A driver cannot be assigned to overlapping delivery trips."
                    ]
                ]);
            }
        }

        $dispatchQuery = Dispatch::where('plant_id', $plantId)
            ->where('driver_id', $driverId)
            ->whereNotIn('dispatch_status', ['Delivered', 'Cancelled', 'Invoiced']);

        if ($ignoreDispatchId) {
            $dispatchQuery->where('id', '!=', $ignoreDispatchId);
        }

        $trackedDispatchIds = $candidates->pluck('dispatch_id')->filter()->toArray();
        if (!empty($trackedDispatchIds)) {
            $dispatchQuery->whereNotIn('id', $trackedDispatchIds);
        }

        $activeDispatches = $dispatchQuery->with('unloadSite')->get();

        foreach ($activeDispatches as $disp) {
            $dispStart = $disp->dispatch_time ?? $disp->load_time ?? $disp->created_at ?? now();
            $dispEnd = $disp->delivery_time ?? (clone $dispStart)->addMinutes(90);

            if ($newStart->lt($dispEnd) && $newEnd->gt($dispStart)) {
                $driver = Personnel::find($driverId);
                $driverName = $driver ? $driver->first_name . ' ' . ($driver->last_name ?? '') : 'Selected Driver';
                $siteName = $disp->unloadSite?->name ?? 'Site';
                $timeSpan = $dispStart->format('d-m-Y H:i') . ' to ' . $dispEnd->format('H:i');
                throw ValidationException::withMessages([
                    'driver_id' => [
                        "Driver {$driverName} is currently operating Dispatch Ticket #{$disp->prefix}{$disp->dispatch_no} (Status: {$disp->dispatch_status} at '{$siteName}') from {$timeSpan}. A driver cannot be assigned while on an active dispatch."
                    ]
                ]);
            }
        }
    }

    /**
     * Validate that a concrete pump is not committed to a conflicting pour deployment.
     */
    private function validatePumpConflict(int $plantId, array $data, ?int $ignoreId = null): void
    {
        $pumpVehicleId = $data['pump_vehicle_id'] ?? null;
        if (!$pumpVehicleId) {
            return;
        }

        [$newStart, $newEnd] = $this->resolveTripWindow($data);

        $deployments = PumpBoomDeploymentSchedule::where('plant_id', $plantId)
            ->where('pump_vehicle_id', $pumpVehicleId)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->get();

        foreach ($deployments as $existing) {
            if ($existing->pour_reference === ($data['pour_reference'] ?? '')) {
                continue;
            }

            $depStart = $existing->setup_start_time ?? $existing->pump_arrival_time ?? Carbon::parse($existing->schedule_date->format('Y-m-d') . ' 07:00:00');
            $depEnd   = $existing->actual_end_time ?? $existing->planned_end_time ?? (clone $depStart)->addHours(4);

            if ($newStart->lt($depEnd) && $newEnd->gt($depStart)) {
                $pump     = Machine::find($pumpVehicleId);
                $reg      = $pump?->registration ?? 'Selected Pump';
                $timeSpan = $depStart->format('d-m-Y H:i') . ' to ' . $depEnd->format('H:i');
                throw ValidationException::withMessages([
                    'pump_vehicle_id' => [
                        "Pump {$reg} is deployed to a conflicting pour ('{$existing->pour_reference}' at '{$existing->site_name}') from {$timeSpan}. A pump cannot be allocated to conflicting jobs."
                    ]
                ]);
            }
        }
    }
}

