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

    /**
     * Generate next dispatch number & prefix matching DispatchController.
     */
    protected function getNextDispatchDetails($plantId): array
    {
        $currentDate = now();
        $startYear = $currentDate->month >= 4 ? $currentDate->year : $currentDate->year - 1;
        $endYear = $startYear + 1;
        $fyString = substr($startYear, -2) . substr($endYear, -2);
        $prefix = "DP-{$fyString}-";

        $maxNumber = Dispatch::where('plant_id', $plantId)
            ->where('prefix', $prefix)
            ->max(DB::raw('CAST(dispatch_no AS UNSIGNED)'));

        $newNumber = ($maxNumber ?: 0) + 1;

        return [
            'prefix'     => $prefix,
            'nextNumber' => (string) $newNumber,
        ];
    }

    /**
     * Display the main Batching & Dispatch Scheduling Dashboard.
     */
    public function index(Request $request): Response
    {
        $this->authorizeModule('view');

        $plantId = session('active_plant_id') ?? auth()->user()->default_plant_id ?? 1;
        $plants  = Plant::all(['id', 'name']);

        $scheduleDate = $request->input('schedule_date', now()->toDateString());

        return Inertia::render('Production/BatchingScheduleDashboard', [
            'plants'         => $plants,
            'activePlantId'  => (int) $plantId,
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

        $plantId = session('active_plant_id') ?? auth()->user()->default_plant_id ?? 1;

        $scheduleDate  = $request->input('schedule_date', now()->toDateString());
        $status        = $request->input('status', 'all');
        $pourReference = $request->input('pour_reference');
        $siteId        = $request->input('site_id');

        $query = ConcreteBatchingSchedule::where('plant_id', $plantId)
            ->with([
                'site:id,name,site_address_1',
                'mixDesign:id,design_name,design_code',
                'vehicle:id,registration,vehicle_model',
                'driver:id,first_name,last_name,employee_code,mobile,designation_id',
                'driver.designation:id,name',
                'pumpVehicle:id,registration,vehicle_model',
                'salesOrder:id,order_no,prefix',
                'dispatch:id,dispatch_no,dispatch_status',
            ]);

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

        // 1. Grouped Pour Tracking Analytics
        $pours = $this->aggregatePourTracking($schedules);

        // 2. High-level Operational Metrics
        $metrics = [
            'total_scheduled_m3'      => round((float) $schedules->sum('qty_m3'), 3),
            'total_delivered_m3'      => round((float) $schedules->where('status', 'completed')->sum('qty_m3'), 3),
            'active_in_transit_tms'   => $schedules->where('status', 'in_transit')->count(),
            'active_pouring_tms'      => $schedules->where('status', 'pouring')->count(),
            'hydration_warning_count' => $schedules->filter(fn($s) => in_array($s->hydration_status, ['warning', 'critical']))->count(),
        ];

        return response()->json([
            'schedules' => $schedules,
            'pours'     => $pours,
            'metrics'   => $metrics,
        ]);
    }

    /**
     * Master dropdowns needed for scheduling modals.
     */
    public function dropdowns(Request $request): JsonResponse
    {
        $this->authorizeModule('view');

        $plantId = session('active_plant_id') ?? auth()->user()->default_plant_id ?? 1;

        $sites = Site::where('plant_id', $plantId)
            ->whereNull('deleted_at')
            ->get(['id', 'name', 'site_address_1']);

        $mixDesigns = MixDesign::where('plant_id', $plantId)
            ->whereNull('deleted_at')
            ->get(['id', 'design_name', 'design_code']);

        $vehicles = Machine::where('plant_id', $plantId)
            ->whereNull('deleted_at')
            ->get(['id', 'registration', 'vehicle_model', 'vehicle_type', 'capacity']);

        $drivers = Personnel::where('plant_id', $plantId)
            ->whereNull('deleted_at')
            ->whereRelation('designation', 'name', 'like', '%Driver%')
            ->get(['id', 'first_name', 'last_name', 'employee_code', 'mobile']);

        $salesOrders = SalesOrder::where('plant_id', $plantId)
            ->whereNull('deleted_at')
            ->get(['id', 'order_no', 'prefix'])
            ->map(fn($so) => [
                'id'           => $so->id,
                'order_number' => ($so->prefix ?? '') . $so->order_no,
            ]);

        return response()->json([
            'sites'       => $sites,
            'mixDesigns'  => $mixDesigns,
            'vehicles'    => $vehicles,
            'drivers'     => $drivers,
            'salesOrders' => $salesOrders,
            'pumpTypes'   => [
                ['value' => 'boom_pump', 'label' => 'Boom Pump (Truck-Mounted Articulated)'],
                ['value' => 'line_pump', 'label' => 'Line Pump (Ground Pipeline)'],
                ['value' => 'stationary_pump', 'label' => 'Stationary High-Rise Pump'],
                ['value' => 'crane_bucket', 'label' => 'Crane & Bucket Pour'],
                ['value' => 'direct_pour', 'label' => 'Direct Chute Discharge'],
            ]
        ]);
    }

    /**
     * Store a new concrete batching schedule slot and create the real Batch & Dispatch models.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorizeModule('create');

        $plantId = session('active_plant_id') ?? auth()->user()->default_plant_id ?? 1;

        if ($request->has('pump_type')) {
            $request->merge([
                'pump_type' => strtolower(trim(str_replace(' ', '_', (string)$request->input('pump_type')))),
            ]);
        }

        $validated = $request->validate([
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
            'batching_time'    => 'nullable|date',
            'dispatch_time'    => 'nullable|date',
            'eta_site'         => 'nullable|date',
            'unloading_start'  => 'nullable|date',
            'unloading_end'    => 'nullable|date',
            'status'           => 'nullable|in:scheduled,batching,in_transit,on_site,pouring,completed,cancelled',
            'notes'            => 'nullable|string',
        ]);

        // Chronological Time Validations
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

        // Normalize all datetime strings to MySQL Y-m-d H:i:s format
        foreach (['batching_time', 'dispatch_time', 'eta_site', 'unloading_start', 'unloading_end'] as $dtField) {
            if (!empty($validated[$dtField])) {
                try {
                    $validated[$dtField] = Carbon::parse($validated[$dtField])->format('Y-m-d H:i:s');
                } catch (\Exception $e) {
                    $validated[$dtField] = null;
                }
            }
        }

        // Fleet Availability & Overlap Validations
        $this->validateTransitMixerOverlap($plantId, $validated);
        $this->validateDriverOverlap($plantId, $validated);
        $this->validatePumpConflict($plantId, $validated);

        return DB::transaction(function () use ($validated, $plantId) {
            $salesOrder = null;
            if (!empty($validated['sales_order_id'])) {
                $salesOrder = SalesOrder::find($validated['sales_order_id']);
            }

            $chosenStatus = $validated['status'] ?? 'scheduled';

            // 1. Create Real Batch (App\Models\Batch)
            $nextBatchNo = (Batch::where('plant_id', $plantId)->max('batch_no') ?? 0) + 1;
            $batchStatus = match ($chosenStatus) {
                'batching'  => Batch::STATUS_LOADING,
                'in_transit', 'on_site', 'pouring' => Batch::STATUS_DISPATCHED,
                'completed' => Batch::STATUS_COMPLETED,
                'cancelled' => Batch::STATUS_CANCELLED,
                default     => Batch::STATUS_PLANNED,
            };

            $batch = Batch::create([
                'plant_id'       => $plantId,
                'sales_order_id' => $validated['sales_order_id'] ?? null,
                'batch_no'       => $nextBatchNo,
                'batch_size'     => $validated['qty_m3'],
                'start_time'     => !empty($validated['batching_time']) ? $validated['batching_time'] : now()->format('Y-m-d H:i:s'),
                'end_time'       => $chosenStatus === 'completed' ? (!empty($validated['unloading_end']) ? $validated['unloading_end'] : now()->format('Y-m-d H:i:s')) : null,
                'operator_id'    => $validated['driver_id'] ?? auth()->id(),
                'shift'          => 'A',
                'status'         => $batchStatus,
            ]);

            // 2. Create Linked Dispatch (App\Models\Dispatch) if vehicle assigned
            $dispatch = null;
            if (!empty($validated['vehicle_id'])) {
                $dispatchDetails = $this->getNextDispatchDetails($plantId);
                $loadRate = (float) ($salesOrder?->rate ?? 0);
                $untaxAmount = round($loadRate * (float)$validated['qty_m3'], 2);

                $dispatchStatus = match ($chosenStatus) {
                    'batching'   => 'Loading',
                    'in_transit' => 'In Transit',
                    'on_site'    => 'On Site',
                    'pouring'    => 'Pouring',
                    'completed'  => 'Delivered',
                    'cancelled'  => 'Cancelled',
                    default      => 'Draft',
                };

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
                    'dispatch_status'   => $dispatchStatus,
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
            $validated['dispatch_id'] = $dispatch?->id;
            $validated['status']      = $chosenStatus;

            $existingDelivered = ConcreteBatchingSchedule::where('plant_id', $plantId)
                ->where('pour_reference', $validated['pour_reference'])
                ->where('status', '!=', 'cancelled')
                ->sum('qty_m3');

            $validated['remaining_volume_m3'] = max(0, (float)$validated['order_volume_m3'] - ((float)$existingDelivered + (float)$validated['qty_m3']));

            $schedule = ConcreteBatchingSchedule::create($validated);

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

        if ($request->has('pump_type')) {
            $request->merge([
                'pump_type' => strtolower(trim(str_replace(' ', '_', (string)$request->input('pump_type')))),
            ]);
        }

        $validated = $request->validate([
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
            'batching_time'    => 'nullable|date',
            'dispatch_time'    => 'nullable|date',
            'eta_site'         => 'nullable|date',
            'unloading_start'  => 'nullable|date',
            'unloading_end'    => 'nullable|date',
            'status'           => 'nullable|in:scheduled,batching,in_transit,on_site,pouring,completed,cancelled',
            'notes'            => 'nullable|string',
        ]);

        $plantId = $schedule->plant_id ?? (session('active_plant_id') ?? auth()->user()->default_plant_id ?? 1);

        // Chronological Time Validations
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

        // Normalize all datetime strings to MySQL Y-m-d H:i:s format
        foreach (['batching_time', 'dispatch_time', 'eta_site', 'unloading_start', 'unloading_end'] as $dtField) {
            if (!empty($validated[$dtField])) {
                try {
                    $validated[$dtField] = Carbon::parse($validated[$dtField])->format('Y-m-d H:i:s');
                } catch (\Exception $e) {
                    $validated[$dtField] = null;
                }
            }
        }

        // Fleet Availability & Overlap Validations
        $this->validateTransitMixerOverlap($plantId, $validated, $schedule->id);
        $this->validateDriverOverlap($plantId, $validated, $schedule->id);
        $this->validatePumpConflict($plantId, $validated, $schedule->id);

        $newStatus = $validated['status'] ?? $schedule->status ?? 'scheduled';
        $validated['status'] = $newStatus;

        return DB::transaction(function () use ($request, $schedule, $validated, $newStatus) {
            $schedule->update($validated);

            // Sync Batch
            if ($schedule->batch_id) {
                $batchStatus = match ($newStatus) {
                    'scheduled' => Batch::STATUS_PLANNED,
                    'batching'  => Batch::STATUS_LOADING,
                    'in_transit', 'on_site', 'pouring' => Batch::STATUS_DISPATCHED,
                    'completed' => Batch::STATUS_COMPLETED,
                    'cancelled' => Batch::STATUS_CANCELLED,
                    default     => Batch::STATUS_PLANNED,
                };

                $batchUpdates = [
                    'batch_size'     => $validated['qty_m3'],
                    'start_time'     => !empty($validated['batching_time']) ? $validated['batching_time'] : now()->format('Y-m-d H:i:s'),
                    'sales_order_id' => $validated['sales_order_id'] ?? null,
                    'status'         => $batchStatus,
                ];
                if ($newStatus === 'completed') {
                    $batchUpdates['end_time'] = !empty($validated['unloading_end']) ? $validated['unloading_end'] : now()->format('Y-m-d H:i:s');
                }
                Batch::where('id', $schedule->batch_id)->update($batchUpdates);
            }

            // Sync Dispatch
            if ($schedule->dispatch_id) {
                $dispatchStatus = match ($newStatus) {
                    'scheduled'  => 'Draft',
                    'batching'   => 'Loading',
                    'in_transit' => 'In Transit',
                    'on_site'    => 'On Site',
                    'pouring'    => 'Pouring',
                    'completed'  => 'Delivered',
                    'cancelled'  => 'Cancelled',
                    default      => 'Draft',
                };

                $dispatchUpdates = [
                    'truck_id'        => $validated['vehicle_id'] ?? null,
                    'driver_id'       => $validated['driver_id'] ?? null,
                    'unload_site_id'  => $validated['site_id'],
                    'mixdesign_id'    => $validated['mix_design_id'],
                    'concrete_pump'   => $validated['pump_vehicle_id'] ?? null,
                    'delivered_qty'   => $validated['qty_m3'],
                    'dispatch_status' => $dispatchStatus,
                ];
                if (!empty($validated['dispatch_time'])) {
                    $dispatchUpdates['dispatch_time'] = $validated['dispatch_time'];
                }
                if ($newStatus === 'completed') {
                    $dispatchUpdates['delivery_time'] = !empty($validated['unloading_end']) ? $validated['unloading_end'] : now()->format('Y-m-d H:i:s');
                }
                Dispatch::where('id', $schedule->dispatch_id)->update($dispatchUpdates);
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
                if (!$schedule->batching_time) {
                    $updates['batching_time'] = $now;
                }
                break;

            case 'in_transit':
                if (!$schedule->dispatch_time) {
                    $updates['dispatch_time'] = $now;
                }
                if (!$schedule->eta_site) {
                    $updates['eta_site'] = (clone $now)->addMinutes(35);
                }
                break;

            case 'on_site':
                $updates['eta_site'] = $now;
                break;

            case 'pouring':
                if (!$schedule->unloading_start) {
                    $updates['unloading_start'] = $now;
                }
                break;

            case 'completed':
                if (!$schedule->unloading_end) {
                    $updates['unloading_end'] = $now;
                }
                break;
        }

        return DB::transaction(function () use ($schedule, $updates, $newStatus, $now) {
            $schedule->update($updates);

            // 1. Sync Batch Status (App\Models\Batch)
            $batchStatus = match ($newStatus) {
                'scheduled' => Batch::STATUS_PLANNED,
                'batching'  => Batch::STATUS_LOADING,
                'in_transit', 'on_site', 'pouring' => Batch::STATUS_DISPATCHED,
                'completed' => Batch::STATUS_COMPLETED,
                'cancelled' => Batch::STATUS_CANCELLED,
                default     => Batch::STATUS_PLANNED,
            };

            if ($schedule->batch_id) {
                Batch::where('id', $schedule->batch_id)->update([
                    'status'   => $batchStatus,
                    'end_time' => $newStatus === 'completed' ? $now : null,
                ]);
            }

            // 2. Sync Dispatch Status (App\Models\Dispatch)
            $dispatchStatus = match ($newStatus) {
                'scheduled'  => 'Draft',
                'batching'   => 'Loading',
                'in_transit' => 'In Transit',
                'on_site'    => 'On Site',
                'pouring'    => 'Pouring',
                'completed'  => 'Delivered',
                'cancelled'  => 'Cancelled',
                default      => 'Draft',
            };

            if ($schedule->dispatch_id) {
                Dispatch::where('id', $schedule->dispatch_id)->update([
                    'dispatch_status' => $dispatchStatus,
                    'delivery_time'   => $newStatus === 'completed' ? $now : null,
                ]);
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

        return response()->json([
            'success' => true,
            'message' => "Schedule slot deleted successfully.",
        ]);
    }

    /**
     * Helper to group and calculate pour progress.
     */
    private function aggregatePourTracking($schedules)
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
                'pour_reference'      => $pourRef,
                'site_name'           => $first->site?->name ?? 'Unspecified Site',
                'mix_design_name'     => $first->mixDesign?->name ?? 'Standard Mix',
                'pump_type'           => $first->pump_type,
                'pump_vehicle'        => $first->pumpVehicle?->registration ?? 'None Assigned',
                'order_volume_m3'     => $orderVol,
                'scheduled_volume_m3' => round($scheduledVol, 3),
                'delivered_volume_m3' => round($deliveredVol, 3),
                'in_progress_volume_m3' => round($inProgressVol, 3),
                'remaining_volume_m3' => round($remainingVol, 3),
                'progress_percent'    => $progress,
                'total_trips'         => $items->count(),
                'active_trips'        => $items->whereIn('status', ['batching', 'in_transit', 'on_site', 'pouring'])->count(),
                'completed_trips'     => $items->where('status', 'completed')->count(),
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
            // Unloading finished + 30 min return haulage to plant
            $end = Carbon::parse($data['unloading_end'])->addMinutes(30);
        } elseif (!empty($data['unloading_start'])) {
            // Unloading start + 30 min discharge + 30 min return haulage
            $end = Carbon::parse($data['unloading_start'])->addMinutes(60);
        } elseif (!empty($data['eta_site'])) {
            // ETA site + 30 min discharge + 30 min return haulage
            $end = Carbon::parse($data['eta_site'])->addMinutes(60);
        } else {
            // Standard trip cycle turnaround: 90 minutes
            $end = (clone $start)->addMinutes(90);
        }

        return [$start, $end];
    }

    /**
     * Validate that a Transit Mixer truck is not allocated to overlapping trip cycles.
     */
    private function validateTransitMixerOverlap(int $plantId, array $data, ?int $ignoreId = null): void
    {
        $vehicleId = $data['vehicle_id'] ?? null;
        if (!$vehicleId) {
            return;
        }

        [$newStart, $newEnd] = $this->resolveTripWindow($data);

        $query = ConcreteBatchingSchedule::where('plant_id', $plantId)
            ->where('vehicle_id', $vehicleId)
            ->whereNotIn('status', ['completed', 'cancelled']);

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
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
    }

    /**
     * Validate that a driver is not allocated to overlapping trips.
     */
    private function validateDriverOverlap(int $plantId, array $data, ?int $ignoreId = null): void
    {
        $driverId = $data['driver_id'] ?? null;
        if (!$driverId) {
            return;
        }

        [$newStart, $newEnd] = $this->resolveTripWindow($data);

        $query = ConcreteBatchingSchedule::where('plant_id', $plantId)
            ->where('driver_id', $driverId)
            ->whereNotIn('status', ['completed', 'cancelled']);

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
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
                // Pour references match; valid deployment at same pour site
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
