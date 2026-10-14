<?php
/*
Author: ragul-onemodo
Created: 2026-10-09 18:02:55 Asia/Calcutta (UTC+05:30)
*/

namespace App\Http\Controllers\Api\SalesMobile;

use App\Http\Controllers\ConcreteBatchingScheduleController;
use App\Services\Mobile\MobileAccess as Access;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ScheduleController extends ConcreteBatchingScheduleController
{
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
        $rules['sales_order_id'] = ['required', 'integer', Rule::in(Access::orders()->whereIn('status', [1, 2])->where('site_id', request('site_id'))->where('mix_design_id', request('mix_design_id'))->pluck('id')->all())];
        $rules['status'] = 'nullable|in:scheduled';
        $rules['dispatch_id'] = 'prohibited';
        return $rules;
    }
    public function store(Request $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            \App\Models\Plant::whereKey(Access::plantId())->lockForUpdate()->firstOrFail();
            return parent::store($request);
        });
    }
    public function listing(Request $r)
    {
        $r->validate(['schedule_date' => 'nullable|date_format:Y-m-d', 'per_page' => 'nullable|integer|between:1,100']);
        $q = Access::schedules()->with(['site:id,name', 'mixDesign:id,design_name', 'batch', 'dispatch']);
        if ($r->filled('schedule_date')) $q->whereDate('schedule_date', $r->schedule_date);
        return response()->json(['success' => true, 'data' => $q->orderBy('schedule_date')->orderBy('id')->paginate($r->integer('per_page', 20))]);
    }
    public function detail(int $schedule)
    {
        $model = Access::schedules()->find($schedule);
        abort_unless($model, 404, 'Batch schedule not found.');
        return response()->json(['success' => true, 'data' => $model->load(['site', 'mixDesign', 'batch', 'dispatch'])]);
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
