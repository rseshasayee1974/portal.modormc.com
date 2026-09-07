<?php

namespace App\Http\Controllers;

use App\Models\ConcreteBatchingSchedule;
use App\Models\Dispatch;
use App\Models\Machine;
use App\Models\MixDesign;
use App\Models\Personnel;
use App\Models\Plant;
use App\Models\SalesOrder;
use App\Models\Site;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Concerns\AuthorizesModule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ConcreteBatchingScheduleController extends Controller
{
    use AuthorizesModule;

    protected string $module = 'batching_schedules';

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
        $plantId = session('active_plant_id') ?? auth()->user()->default_plant_id ?? 1;

        $scheduleDate  = $request->input('schedule_date', now()->toDateString());
        $status        = $request->input('status', 'all');
        $pourReference = $request->input('pour_reference');
        $siteId        = $request->input('site_id');

        $query = ConcreteBatchingSchedule::where('plant_id', $plantId)
            ->with([
                'site:id,name,address',
                'mixDesign:id,name,code',
                'vehicle:id,registration,vehicle_model',
                'driver:id,first_name,last_name,employee_code,phone',
                'pumpVehicle:id,registration,vehicle_model',
                'salesOrder:id,order_number',
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
        $plantId = session('active_plant_id') ?? auth()->user()->default_plant_id ?? 1;

        $sites = Site::where('plant_id', $plantId)
            ->whereNull('deleted_at')
            ->get(['id', 'name', 'address']);

        $mixDesigns = MixDesign::where('plant_id', $plantId)
            ->whereNull('deleted_at')
            ->get(['id', 'name', 'code']);

        $vehicles = Machine::where('plant_id', $plantId)
            ->whereNull('deleted_at')
            ->get(['id', 'registration', 'vehicle_model', 'vehicle_type', 'capacity']);

        $drivers = Personnel::where('plant_id', $plantId)
            ->whereNull('deleted_at')
            ->get(['id', 'first_name', 'last_name', 'employee_code', 'phone']);

        $salesOrders = SalesOrder::where('plant_id', $plantId)
            ->whereNull('deleted_at')
            ->get(['id', 'order_number']);

        return response()->json([
            'sites'       => $sites,
            'mixDesigns'  => $mixDesigns,
            'vehicles'    => $vehicles,
            'drivers'     => $drivers,
            'salesOrders' => $salesOrders,
            'pumpTypes'   => [
                ['value' => 'boom_pump', 'label' => 'Boom Pump (Mobile Articulated)'],
                ['value' => 'line_pump', 'label' => 'Line Pump (Ground / Stationary Pipeline)'],
                ['value' => 'crane_bucket', 'label' => 'Crane & Bucket'],
                ['value' => 'direct_pour', 'label' => 'Direct Chute Discharge'],
            ]
        ]);
    }

    /**
     * Store a new concrete batching schedule slot.
     */
    public function store(Request $request): JsonResponse
    {
        $plantId = session('active_plant_id') ?? auth()->user()->default_plant_id ?? 1;

        $validated = $request->validate([
            'schedule_date'    => 'required|date',
            'pour_reference'   => 'required|string|max:100',
            'site_id'          => 'required|exists:mm_sites,id',
            'mix_design_id'    => 'required|exists:mm_mix_designs,id',
            'qty_m3'           => 'required|numeric|min:0.1',
            'order_volume_m3'  => 'required|numeric|min:0.1',
            'vehicle_id'       => 'nullable|exists:mm_machines,id',
            'driver_id'        => 'nullable|exists:mm_personnels,id',
            'pump_type'        => 'required|in:boom_pump,line_pump,crane_bucket,direct_pour',
            'pump_vehicle_id'  => 'nullable|exists:mm_machines,id',
            'sales_order_id'   => 'nullable|exists:mm_sales_orders,id',
            'batching_time'    => 'nullable|date',
            'dispatch_time'    => 'nullable|date',
            'eta_site'         => 'nullable|date',
            'notes'            => 'nullable|string',
        ]);

        $validated['plant_id'] = $plantId;
        $validated['status']   = 'scheduled';

        // Initial remaining volume computation
        $existingDelivered = ConcreteBatchingSchedule::where('plant_id', $plantId)
            ->where('pour_reference', $validated['pour_reference'])
            ->where('status', '!=', 'cancelled')
            ->sum('qty_m3');

        $validated['remaining_volume_m3'] = max(0, (float)$validated['order_volume_m3'] - ((float)$existingDelivered + (float)$validated['qty_m3']));

        $schedule = ConcreteBatchingSchedule::create($validated);

        return response()->json([
            'success'  => true,
            'message'  => "Schedule slot #{$schedule->id} created successfully.",
            'schedule' => $schedule->load(['site', 'mixDesign', 'vehicle', 'driver', 'pumpVehicle']),
        ]);
    }

    /**
     * Update an existing schedule slot.
     */
    public function update(Request $request, ConcreteBatchingSchedule $schedule): JsonResponse
    {
        $validated = $request->validate([
            'schedule_date'    => 'required|date',
            'pour_reference'   => 'required|string|max:100',
            'site_id'          => 'required|exists:mm_sites,id',
            'mix_design_id'    => 'required|exists:mm_mix_designs,id',
            'qty_m3'           => 'required|numeric|min:0.1',
            'order_volume_m3'  => 'required|numeric|min:0.1',
            'vehicle_id'       => 'nullable|exists:mm_machines,id',
            'driver_id'        => 'nullable|exists:mm_personnels,id',
            'pump_type'        => 'required|in:boom_pump,line_pump,crane_bucket,direct_pour',
            'pump_vehicle_id'  => 'nullable|exists:mm_machines,id',
            'sales_order_id'   => 'nullable|exists:mm_sales_orders,id',
            'batching_time'    => 'nullable|date',
            'dispatch_time'    => 'nullable|date',
            'eta_site'         => 'nullable|date',
            'unloading_start'  => 'nullable|date',
            'unloading_end'    => 'nullable|date',
            'status'           => 'required|in:scheduled,batching,in_transit,on_site,pouring,completed,cancelled',
            'notes'            => 'nullable|string',
        ]);

        $schedule->update($validated);

        return response()->json([
            'success'  => true,
            'message'  => "Schedule slot #{$schedule->id} updated successfully.",
            'schedule' => $schedule->fresh(['site', 'mixDesign', 'vehicle', 'driver', 'pumpVehicle']),
        ]);
    }

    /**
     * Fast 1-click status transition with automated timestamp milestone capture.
     */
    public function updateStatus(Request $request, ConcreteBatchingSchedule $schedule): JsonResponse
    {
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
                    $updates['eta_site'] = (clone $now)->addMinutes(35); // Default estimated transit travel
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

        $schedule->update($updates);

        return response()->json([
            'success'  => true,
            'message'  => "Status changed to " . ucfirst(str_replace('_', ' ', $newStatus)),
            'schedule' => $schedule->fresh(['site', 'mixDesign', 'vehicle', 'driver', 'pumpVehicle']),
        ]);
    }

    /**
     * Convert an active scheduled batch into an official Dispatch ticket (mm_dispatches).
     */
    public function createDispatchTicket(Request $request, ConcreteBatchingSchedule $schedule): JsonResponse
    {
        if ($schedule->dispatch_id) {
            return response()->json([
                'success' => false,
                'message' => 'Dispatch ticket already exists for this trip slot.'
            ], 422);
        }

        return DB::transaction(function () use ($schedule) {
            $plantId = $schedule->plant_id;

            // Generate Dispatch Number
            $prefix = 'DISP';
            $maxNo = Dispatch::where('plant_id', $plantId)->max('id') ?? 0;
            $dispatchNo = sprintf('%s/%s/%04d', $prefix, date('ym'), $maxNo + 1);

            $dispatch = Dispatch::create([
                'plant_id'            => $plantId,
                'sales_order_id'      => $schedule->sales_order_id,
                'truck_id'            => $schedule->vehicle_id,
                'driver_id'           => $schedule->driver_id,
                'unload_site_id'      => $schedule->site_id,
                'mixdesign_id'        => $schedule->mix_design_id,
                'delivered_qty'       => $schedule->qty_m3,
                'dispatch_no'         => $dispatchNo,
                'dispatch_reference'  => $schedule->pour_reference,
                'dispatch_time'       => $schedule->dispatch_time ?? now(),
                'dispatch_status'     => 'In Transit',
                'payment_mode'        => 'credit',
                'concrete_pump'       => $schedule->pump_vehicle_id,
            ]);

            $schedule->update([
                'dispatch_id'   => $dispatch->id,
                'status'        => 'in_transit',
                'dispatch_time' => $schedule->dispatch_time ?? now(),
            ]);

            return response()->json([
                'success'     => true,
                'message'     => "Dispatch Ticket #{$dispatchNo} generated successfully.",
                'dispatch_id' => $dispatch->id,
                'dispatch_no' => $dispatchNo,
                'schedule'    => $schedule->fresh(['dispatch', 'vehicle', 'driver']),
            ]);
        });
    }

    /**
     * Delete schedule slot.
     */
    public function destroy(ConcreteBatchingSchedule $schedule): JsonResponse
    {
        $pourRef = $schedule->pour_reference;
        $plantId = $schedule->plant_id;

        $schedule->delete();

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
}
