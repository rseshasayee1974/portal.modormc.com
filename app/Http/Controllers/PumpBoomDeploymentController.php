<?php

namespace App\Http\Controllers;

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
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Http\Controllers\Concerns\AuthorizesModule;
use Inertia\Inertia;
use Inertia\Response;

class PumpBoomDeploymentController extends Controller
{
    use AuthorizesModule;

    protected string $module = 'pump_deployments';

    public const PUMP_TYPE_OPTIONS = [
        ['value' => 'boom_pump', 'label' => 'Boom Pump (Mobile Articulated Boom)'],
        ['value' => 'line_pump', 'label' => 'Line Pump (Ground / Pipeline)'],
        ['value' => 'stationary_pump', 'label' => 'Stationary High-Rise Pump'],
        ['value' => 'crane_bucket', 'label' => 'Crane & Concrete Bucket'],
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
     * Default eager-loaded relations for deployment queries.
     */
    protected function getDeploymentRelations(): array
    {
        return [
            'site:id,name,site_address_1',
            'mixDesign:id,design_name,design_code',
            'pumpMachine:id,registration,vehicle_model,capacity',
            'operator:id,first_name,last_name,employee_code,mobile',
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
     * Common validation rules for pump deployments.
     */
    protected function getValidationRules(): array
    {
        return [
            'schedule_date'      => 'required|date',
            'pour_reference'     => 'required',
            'site_id'            => 'nullable|exists:mm_sites,id',
            'site_name'          => 'nullable|string|max:200',
            'pour_location'      => 'nullable|string|max:255',
            'mix_design_id'      => 'nullable|exists:mm_mix_designs,id',
            'grade'              => 'nullable|string|max:100',
            'planned_qty_m3'     => 'required|numeric|min:0.1',
            'pump_type'          => ['required', Rule::in(ConcreteBatchingSchedule::PUMP_TYPES)],
            'pump_vehicle_id'    => 'required_without:pump_no|nullable|exists:mm_machines,id',
            'pump_no'            => 'required_without:pump_vehicle_id|nullable|string|max:100',
            'boom_length_m'      => 'required_if:pump_type,boom_pump|nullable|numeric|min:1',
            'operator_id'        => 'nullable|exists:mm_personnels,id',
            'operator_name'      => 'nullable|string|max:150',
            'pump_arrival_time'  => 'nullable|date',
            'setup_start_time'   => 'nullable|date',
            'setup_end_time'     => 'nullable|date',
            'pour_start_time'    => 'nullable|date',
            'planned_end_time'   => 'nullable|date',
            'actual_start_time'  => 'nullable|date',
            'actual_end_time'    => 'nullable|date',
            'status'             => 'nullable|in:scheduled,in_progress,en_route,setup,ready,pumping,washout,completed,delayed,breakdown,cancelled',
            'notes'              => 'nullable|string',
        ];
    }

    /**
     * Custom validation error messages.
     */
    protected function getValidationMessages(): array
    {
        return [
            'schedule_date.required'           => 'Every pour must have a schedule date.',
            'pour_reference.required'          => 'Every pour must have a pour reference.',
            'pump_type.required'               => 'Every scheduled pour must have a pump type.',
            'pump_vehicle_id.required_without' => 'Every scheduled pour must have an assigned pump.',
            'pump_no.required_without'         => 'Every scheduled pour must have an assigned pump.',
            'boom_length_m.required_if'        => 'A boom pump must have a boom length specified.',
        ];
    }

    /**
     * Validate chronological time order and normalize timestamps to Y-m-d H:i:s.
     */
    protected function validateAndNormalizeTimes(array &$validated): void
    {
        if (!empty($validated['setup_start_time']) && !empty($validated['setup_end_time'])) {
            if (Carbon::parse($validated['setup_start_time'])->gt(Carbon::parse($validated['setup_end_time']))) {
                throw ValidationException::withMessages([
                    'setup_start_time' => ['setup_start_time cannot be later than setup_end_time.']
                ]);
            }
        }

        if (!empty($validated['actual_start_time']) && !empty($validated['actual_end_time'])) {
            if (Carbon::parse($validated['actual_start_time'])->gt(Carbon::parse($validated['actual_end_time']))) {
                throw ValidationException::withMessages([
                    'actual_start_time' => ['actual_start_time cannot be later than actual_end_time.']
                ]);
            }
        }

        $timeFields = ['pump_arrival_time', 'setup_start_time', 'setup_end_time', 'pour_start_time', 'planned_end_time', 'actual_start_time', 'actual_end_time'];
        foreach ($timeFields as $dtField) {
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
     * Resolve deployment status based on provided inputs or lifecycle timestamps.
     */
    protected function resolveDeploymentStatus(array &$validated, string $default = 'scheduled'): void
    {
        if (!empty($validated['status'])) {
            $validated['status'] = $validated['status'];
        } elseif (!empty($validated['actual_end_time'])) {
            $validated['status'] = 'completed';
        } elseif (!empty($validated['actual_start_time'])) {
            $validated['status'] = 'in_progress';
        } else {
            $validated['status'] = $default;
        }

        if (!empty($validated['pump_vehicle_id']) && empty($validated['pump_no'])) {
            $validated['pump_no'] = Machine::find($validated['pump_vehicle_id'])?->registration;
        }
    }

    /**
     * Render the Pump & Boom Deployment Scheduling Dashboard.
     */
    public function index(Request $request): Response
    {
        $this->authorizeModule('view');

        $plantId = $this->getActivePlantId();
        $scheduleDate = $request->input('schedule_date', now()->toDateString());

        return Inertia::render('Production/PumpBoomDeployment/Index', [
            'plants'         => Plant::all(['id', 'name']),
            'activePlantId'  => $plantId,
            'initialDate'    => $scheduleDate,
            'initialFilters' => [
                'schedule_date'  => $scheduleDate,
                'status'         => $request->input('status', 'all'),
                'pump_type'      => $request->input('pump_type', 'all'),
                'pour_reference' => $request->input('pour_reference', ''),
            ]
        ]);
    }

    /**
     * JSON data API for live deployment monitoring.
     */
    public function getData(Request $request): JsonResponse
    {
        $this->authorizeModule('view');

        $plantId = $this->getActivePlantId();
        $scheduleDate  = $request->input('schedule_date');
        $siteId        = $request->input('site_id');
        $pourLocation  = $request->input('pour_location');
        $pumpType      = $request->input('pump_type');
        $pumpNo        = $request->input('pump_no');
        $operatorId    = $request->input('operator_id');
        $status        = $request->input('status', 'all');
        $pourReference = $request->input('pour_reference');

        $query = PumpBoomDeploymentSchedule::where('plant_id', $plantId)
            ->with($this->getDeploymentRelations());

        if (!empty($scheduleDate) && $scheduleDate !== 'all') {
            $query->whereDate('schedule_date', $scheduleDate);
        }

        if (!empty($siteId) && $siteId !== 'all') {
            $query->where('site_id', $siteId);
        }

        if (!empty($pourLocation)) {
            $query->where('pour_location', 'like', "%{$pourLocation}%");
        }

        if (!empty($pumpType) && $pumpType !== 'all') {
            $query->where('pump_type', $pumpType);
        }

        if (!empty($pumpNo) && $pumpNo !== 'all') {
            $query->where(function ($q) use ($pumpNo) {
                $q->where('pump_no', 'like', "%{$pumpNo}%")
                  ->orWhere('pump_vehicle_id', $pumpNo);
            });
        }

        if (!empty($operatorId) && $operatorId !== 'all') {
            $query->where('operator_id', $operatorId);
        }

        if (!empty($status) && $status !== 'all') {
            if ($status === 'in_progress') {
                $query->whereIn('status', ['in_progress', 'pumping']);
            } else {
                $query->where('status', $status);
            }
        }

        if (!empty($pourReference)) {
            $query->where(function ($q) use ($pourReference) {
                $q->where('pour_reference', 'like', "%{$pourReference}%")
                  ->orWhere('pour_location', 'like', "%{$pourReference}%");
            });
        }

        $deployments = $query->orderBy('schedule_date', 'asc')
            ->orderBy('pour_start_time', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $metrics = [
            'total_deployments'      => $deployments->count(),
            'total_planned_m3'       => round((float) $deployments->sum('planned_qty_m3'), 2),
            'active_pumping_count'   => $deployments->whereIn('status', ['in_progress', 'pumping'])->count(),
            'setup_in_progress'      => $deployments->where('status', 'setup')->count(),
            'completed_deployments'  => $deployments->where('status', 'completed')->count(),
            'delayed_count'          => $deployments->where('status', 'delayed')->count(),
        ];

        return response()->json([
            'deployments' => $deployments,
            'metrics'     => $metrics,
        ]);
    }

    /**
     * Master dropdowns for modal form.
     */
    public function dropdowns(Request $request): JsonResponse
    {
        $this->authorizeModule('view');

        $plantId = $this->getActivePlantId();

        $sites = Site::where('plant_id', $plantId)->whereNull('deleted_at')->get(['id', 'name', 'site_address_1']);
        $mixDesigns = MixDesign::where('plant_id', $plantId)->whereNull('deleted_at')->get(['id', 'design_name', 'design_code']);
        $machines = MachinesDropdown('Pump');
        $operators = Personnel::where('plant_id', $plantId)->whereNull('deleted_at')->whereRelation('designation', 'name', 'like', '%Operator%')->get(['id', 'first_name', 'last_name', 'employee_code', 'mobile']);

        $salesOrders = SalesOrder::where('plant_id', $plantId)
            ->whereNull('deleted_at')
            ->where('status', SalesOrder::STATUS_IN_PROGRESS)
            ->with([
                'site:id,name,site_address_1',
                'mixDesign:id,design_name,design_code',
                'customer:id,legal_name'
            ])
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($so) {
                $dispatched = (float) Dispatch::where('sales_order_id', $so->id)
                    ->whereNotIn('dispatch_status', ['Cancelled'])
                    ->sum('delivered_qty');
                $totalQty = (float) $so->total_qty;

                return [
                    'id'             => $so->id,
                    'order_no'       => $so->order_no,
                    'prefix'         => $so->prefix,
                    'order_number'   => ($so->prefix ?? '') . $so->order_no,
                    'total_qty'      => $totalQty,
                    'dispatched_qty' => $dispatched,
                    'remaining_qty'  => max(0, $totalQty - $dispatched),
                    'site_id'        => $so->site_id,
                    'site_name'      => $so->site?->name,
                    'site_address'   => $so->site?->site_address_1,
                    'mix_design_id'  => $so->mix_design_id,
                    'mix_name'       => $so->mixDesign?->design_name,
                    'mix_code'       => $so->mixDesign?->design_code,
                    'customer_id'    => $so->customer_id,
                    'customer_name'  => $so->customer?->legal_name,
                ];
            });

        return response()->json([
            'sites'       => $sites,
            'mixDesigns'  => $mixDesigns,
            'machines'    => $machines,
            'operators'   => $operators,
            'pumpTypes'   => self::PUMP_TYPE_OPTIONS,
            'salesOrders' => $salesOrders,
        ]);
    }

    /**
     * Create a new pump deployment schedule.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorizeModule('create');

        $plantId = $this->getActivePlantId();
        $this->normalizePumpTypeInput($request);

        $validated = $request->validate($this->getValidationRules(), $this->getValidationMessages());
        $this->validateAndNormalizeTimes($validated);

        $validated['plant_id'] = $plantId;
        $this->resolveDeploymentStatus($validated, 'scheduled');

        $this->validatePumpOverlap($plantId, $validated);
        $this->validateOperatorOverlap($plantId, $validated);

        $deployment = PumpBoomDeploymentSchedule::create($validated);

        return response()->json([
            'success'    => true,
            'message'    => "Pump deployment #{$deployment->id} scheduled successfully.",
            'deployment' => $deployment->fresh($this->getDeploymentRelations()),
        ]);
    }

    /**
     * Update an existing pump deployment schedule.
     */
    public function update(Request $request, PumpBoomDeploymentSchedule $deployment): JsonResponse
    {
        $this->authorizeModule('update');

        $plantId = $deployment->plant_id ?? $this->getActivePlantId();
        $this->normalizePumpTypeInput($request);

        $validated = $request->validate($this->getValidationRules(), $this->getValidationMessages());
        $this->validateAndNormalizeTimes($validated);

        $this->resolveDeploymentStatus($validated, $deployment->status ?: 'scheduled');

        $this->validatePumpOverlap($plantId, $validated, $deployment->id);
        $this->validateOperatorOverlap($plantId, $validated, $deployment->id);

        $deployment->update($validated);

        return response()->json([
            'success'    => true,
            'message'    => "Pump deployment #{$deployment->id} updated successfully.",
            'deployment' => $deployment->fresh($this->getDeploymentRelations()),
        ]);
    }

    /**
     * Resolve effective start and end timestamps for a deployment window.
     */
    private function resolveDeploymentWindow(array $data): array
    {
        $scheduleDate = $data['schedule_date'] ?? now()->toDateString();

        $start = !empty($data['setup_start_time'])
            ? Carbon::parse($data['setup_start_time'])
            : (!empty($data['pump_arrival_time'])
                ? Carbon::parse($data['pump_arrival_time'])
                : (!empty($data['actual_start_time'])
                    ? Carbon::parse($data['actual_start_time'])
                    : (!empty($data['pour_start_time'])
                        ? Carbon::parse($data['pour_start_time'])
                        : Carbon::parse($scheduleDate . ' 07:00:00'))));

        if (!empty($data['actual_end_time'])) {
            $end = Carbon::parse($data['actual_end_time']);
        } elseif (!empty($data['planned_end_time'])) {
            $end = Carbon::parse($data['planned_end_time']);
        } else {
            $qty = (float)($data['planned_qty_m3'] ?? 40);
            $durationHours = max(2.5, ceil($qty / 30.0) + 1.0);
            $end = (clone $start)->addMinutes((int)($durationHours * 60));
        }

        return [$start, $end];
    }

    /**
     * Rule: A pump cannot be allocated to two overlapping pours.
     */
    private function validatePumpOverlap(int $plantId, array $data, ?int $ignoreId = null): void
    {
        $pumpVehicleId = $data['pump_vehicle_id'] ?? null;
        $pumpNo        = $data['pump_no'] ?? null;

        if (!$pumpVehicleId && !$pumpNo) {
            throw ValidationException::withMessages([
                'pump_vehicle_id' => ['Every scheduled pour must have an assigned pump.']
            ]);
        }

        [$newStart, $newEnd] = $this->resolveDeploymentWindow($data);

        $query = PumpBoomDeploymentSchedule::where('plant_id', $plantId)
            ->whereNotIn('status', ['completed', 'cancelled']);

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        $query->where(function ($q) use ($pumpVehicleId, $pumpNo) {
            if ($pumpVehicleId) {
                $q->where('pump_vehicle_id', $pumpVehicleId);
            }
            if ($pumpNo) {
                $q->orWhere('pump_no', $pumpNo);
            }
        });

        $candidates = $query->get();

        foreach ($candidates as $existing) {
            [$exStart, $exEnd] = $this->resolveDeploymentWindow($existing->toArray());

            if ($newStart->lt($exEnd) && $newEnd->gt($exStart)) {
                $pumpName = $existing->pump_no ?? ($existing->pumpMachine?->registration ?? 'Selected Pump');
                $timeSpan = $exStart->format('d-m-Y H:i') . ' to ' . $exEnd->format('H:i');
                throw ValidationException::withMessages([
                    'pump_vehicle_id' => [
                        "Pump {$pumpName} is already allocated to pour '{$existing->pour_reference}' at site '{$existing->site_name}' ({$timeSpan}). A pump cannot be allocated to two overlapping pours."
                    ]
                ]);
            }
        }
    }

    /**
     * Rule: An operator should not be assigned to overlapping pump operations.
     */
    private function validateOperatorOverlap(int $plantId, array $data, ?int $ignoreId = null): void
    {
        $operatorId   = $data['operator_id'] ?? null;
        $operatorName = $data['operator_name'] ?? null;

        if (!$operatorId && !$operatorName) {
            return;
        }

        [$newStart, $newEnd] = $this->resolveDeploymentWindow($data);

        $query = PumpBoomDeploymentSchedule::where('plant_id', $plantId)
            ->whereNotIn('status', ['completed', 'cancelled']);

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        $query->where(function ($q) use ($operatorId, $operatorName) {
            if ($operatorId) {
                $q->where('operator_id', $operatorId);
            }
            if ($operatorName) {
                $q->orWhere('operator_name', $operatorName);
            }
        });

        $candidates = $query->get();

        foreach ($candidates as $existing) {
            [$exStart, $exEnd] = $this->resolveDeploymentWindow($existing->toArray());

            if ($newStart->lt($exEnd) && $newEnd->gt($exStart)) {
                $opLabel  = $existing->operator_name ?? ($existing->operator ? $existing->operator->first_name . ' ' . ($existing->operator->last_name ?? '') : 'Selected Operator');
                $timeSpan = $exStart->format('d-m-Y H:i') . ' to ' . $exEnd->format('H:i');
                throw ValidationException::withMessages([
                    'operator_id' => [
                        "Operator {$opLabel} is already assigned to pour '{$existing->pour_reference}' at site '{$existing->site_name}' ({$timeSpan}). An operator cannot be assigned to overlapping pump operations."
                    ]
                ]);
            }
        }
    }

    /**
     * 1-Click Status Advancement with automated milestone timestamps.
     */
    public function updateStatus(Request $request, PumpBoomDeploymentSchedule $deployment): JsonResponse
    {
        $this->authorizeModule('update');

        $validated = $request->validate([
            'status' => 'required|in:scheduled,in_progress,en_route,setup,ready,pumping,washout,completed,delayed,breakdown,cancelled',
            'notes'  => 'nullable|string',
        ]);

        $newStatus = $validated['status'];
        $updates   = ['status' => $newStatus];
        if ($request->has('notes')) {
            $updates['notes'] = $validated['notes'];
        }
        $now       = now();

        switch ($newStatus) {
            case 'setup':
                if (!$deployment->pump_arrival_time) $updates['pump_arrival_time'] = $now;
                if (!$deployment->setup_start_time) $updates['setup_start_time'] = $now;
                break;
            case 'ready':
                if (!$deployment->setup_end_time) $updates['setup_end_time'] = $now;
                break;
            case 'in_progress':
            case 'pumping':
                if (!$deployment->actual_start_time) $updates['actual_start_time'] = $now;
                break;
            case 'washout':
            case 'completed':
                if (!$deployment->actual_end_time) $updates['actual_end_time'] = $now;
                break;
            case 'delayed':
            case 'cancelled':
                break;
        }

        $deployment->update($updates);

        return response()->json([
            'success'    => true,
            'message'    => "Pump status updated to " . ucfirst(str_replace('_', ' ', $newStatus)),
            'deployment' => $deployment->fresh($this->getDeploymentRelations()),
        ]);
    }

    /**
     * Soft delete deployment schedule.
     */
    public function destroy(PumpBoomDeploymentSchedule $deployment): JsonResponse
    {
        $this->authorizeModule('delete');

        $deployment->delete();

        return response()->json([
            'success' => true,
            'message' => "Pump deployment schedule deleted successfully.",
        ]);
    }
}