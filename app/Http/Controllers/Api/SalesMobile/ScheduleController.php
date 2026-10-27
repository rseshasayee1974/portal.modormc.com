<?php
/*
Author: ragul-onemodo
Created: 2026-10-09 18:02:55 Asia/Calcutta (UTC+05:30)
*/

namespace App\Http\Controllers\Api\SalesMobile;

use App\Http\Controllers\ConcreteBatchingScheduleController;
use App\Services\Mobile\MobileAccess as Access;
use App\Support\StoredDateSerializer;
use App\Models\ConcreteBatchingSchedule;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ScheduleController extends ConcreteBatchingScheduleController
{
    protected function serializeSchedule(ConcreteBatchingSchedule $schedule): array|ConcreteBatchingSchedule
    {
        return StoredDateSerializer::model($schedule);
    }

    protected function authorizeModule(string $action, ?string $module = null): void
    {
        // Mobile routes enforce authentication and plant access; module permissions are not required.
    }
    protected function getValidationRules(): array
    {
        $rules = parent::getValidationRules();
        foreach (['site_id' => 'mm_sites', 'mix_design_id' => 'mm_mix_designs', 'vehicle_id' => 'mm_machines', 'pump_vehicle_id' => 'mm_machines', 'driver_id' => 'mm_personnels'] as $field => $table) {
            $rules[$field] = [in_array($field, ['site_id', 'mix_design_id']) ? 'required' : 'nullable', 'integer', Rule::exists($table, 'id')->where('plant_id', Access::plantId())->whereNull('deleted_at')];
        }
        $rules['site_id'] = ['required', 'integer', Access::exists(Access::sites())];
        $rules['sales_order_id'] = ['required', 'integer', Access::exists(Access::orders()->whereIn('status', [1, 2])->where('site_id', request('site_id'))->where('mix_design_id', request('mix_design_id')))];
        $rules['status'] = ['nullable', Rule::in(['scheduled'])];
        $rules['dispatch_id'] = 'prohibited';
        return $rules;
    }
    protected function getValidationMessages(): array
    {
        return [
            'sales_order_id.exists' => 'Select an accessible scheduled or in-progress sales order in the active plant that matches the selected site and mix design.',
            'site_id.exists' => 'Select an accessible site in the active plant.',
            'mix_design_id.exists' => 'Select a mix design in the active plant.',
            'status.in' => 'Status must be "scheduled". Omit status to use the default when creating a schedule.',
        ];
    }

    public function store(Request $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            \App\Models\Plant::whereKey(Access::plantId())->lockForUpdate()->firstOrFail();
            return parent::store($request);
        });
    }
    public function options(): JsonResponse
    {
        $vehicles = MachinesDropdown(['Transit Mixer', 'Truck'], plantId: Access::plantId())->map(fn ($vehicle) => [
            'id' => (int) $vehicle->id,
            'value' => (int) $vehicle->id,
            'label' => $vehicle->registration,
            'registration' => $vehicle->registration,
        ])->values();
        $drivers = DriversOperatorsDropdown(null, Access::plantId())->map(fn ($driver) => [
            'id' => (int) $driver['id'],
            'value' => (int) $driver['value'],
            'label' => $driver['label'],
        ])->values();

        return response()->json(['success' => true, 'data' => [
            'vehicles' => $vehicles,
            'drivers' => $drivers,
        ]]);
    }

    public function listing(Request $r)
    {
        $r->validate(['schedule_date' => 'nullable|date_format:Y-m-d', 'per_page' => 'nullable|integer|between:1,100', 'page' => 'nullable|integer|min:1']);
        $q = Access::schedules()->with(['site' => fn ($q) => $q->select('id', 'name')->whereIn('id', Access::sites()->select('id')), 'mixDesign' => fn ($q) => $q->select('id', 'design_name')->where('plant_id', Access::plantId()), 'batch' => fn ($q) => $q->where('plant_id', Access::plantId())->whereIn('sales_order_id', Access::orders()->select('id')), 'dispatch' => fn ($q) => $q->where('plant_id', Access::plantId())->whereIn('sales_order_id', Access::orders()->select('id'))]);
        if ($r->filled('schedule_date')) $q->whereDate('schedule_date', $r->schedule_date);
        return response()->json(['success' => true, 'data' => $q->orderBy('schedule_date')->orderBy('id')->paginate($r->integer('per_page', 20))->through(fn ($schedule) => $this->serializeSchedule($schedule))]);
    }
    public function detail(int $schedule)
    {
        $model = Access::schedules()->find($schedule);
        abort_unless($model, 404, 'Batch schedule not found.');
        return response()->json(['success' => true, 'data' => $this->serializeSchedule($model->load(['site' => fn ($q) => $q->whereIn('id', Access::sites()->select('id')), 'mixDesign' => fn ($q) => $q->where('plant_id', Access::plantId()), 'batch' => fn ($q) => $q->where('plant_id', Access::plantId())->whereIn('sales_order_id', Access::orders()->select('id')), 'dispatch' => fn ($q) => $q->where('plant_id', Access::plantId())->whereIn('sales_order_id', Access::orders()->select('id'))]))]);
    }
    public function change(Request $r, int $schedule)
    {
        return DB::transaction(function () use ($r, $schedule) {
            \App\Models\Plant::whereKey(Access::plantId())->lockForUpdate()->firstOrFail();
            $model = Access::schedules()->lockForUpdate()->find($schedule);
            abort_unless($model, 404, 'Batch schedule not found.');
            abort_if($model->batch_id || $model->dispatch_id || $model->status !== 'scheduled', 422, 'Only unlinked planned slots can be edited.');
            return parent::update($r, $model);
        });
    }
    public function remove(int $schedule)
    {
        return DB::transaction(function () use ($schedule) {
            $model = Access::schedules()->lockForUpdate()->find($schedule);
            abort_unless($model, 404, 'Batch schedule not found.');
            abort_if($model->batch_id || $model->dispatch_id || $model->status !== 'scheduled', 422, 'Only unlinked planned slots can be deleted.');
            return parent::destroy($model);
        });
    }
}
