<?php

namespace App\Http\Controllers\QC;

use App\Http\Controllers\Controller;
use App\Models\QC\QcTestType;
use App\Models\QC\QcTestSchedule;
use App\Models\Product;
use App\Services\PlantContextService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class QCTestScheduleController extends Controller
{
    public function index(Request $request)
    {
        $ctx = app(PlantContextService::class);
        $plantId = $ctx->plantId();

        $schedulesQuery = QcTestSchedule::with(['material', 'testType']);
        if ($plantId) {
            $schedulesQuery->where(function ($q) use ($plantId) {
                $q->where('plant_id', $plantId)
                  ->orWhereNull('plant_id');
            });
        }
        $schedules = $schedulesQuery->orderBy('id', 'desc')->paginate(15);

        $testTypesQuery = QcTestType::where('is_active', true);
        if ($plantId) {
            $testTypesQuery->where(function ($q) use ($plantId) {
                $q->where('plant_id', $plantId)
                  ->orWhereNull('plant_id');
            });
        }
        $testTypes = $testTypesQuery->get(['id', 'code', 'name']);

        $materials = Product::query();
        if ($plantId) {
            $materials->where('plant_id', $plantId);
        }
        $materials = $materials->where('status', true)->get(['id', 'title', 'code', 'material_code']);

        return Inertia::render('Quality/Configuration/Schedules/Index', [
            'schedules' => $schedules,
            'testTypes' => $testTypes,
            'materials' => $materials,
            'frequencyTypes' => [
                'PER_BATCH',
                'PER_LOT',
                'PER_DELIVERY',
                'PER_SHIFT',
                'HOURLY',
                'EVERY_N_HOURS',
                'DAILY',
                'WEEKLY',
                'MONTHLY',
                'MANUAL'
            ],
        ]);
    }

    public function create()
    {
        $ctx = app(PlantContextService::class);
        $plantId = $ctx->plantId();

        $testTypesQuery = QcTestType::where('is_active', true);
        if ($plantId) {
            $testTypesQuery->where(function ($q) use ($plantId) {
                $q->where('plant_id', $plantId)
                  ->orWhereNull('plant_id');
            });
        }
        $testTypes = $testTypesQuery->get(['id', 'code', 'name']);

        $materials = Product::query();
        if ($plantId) {
            $materials->where('plant_id', $plantId);
        }
        $materials = $materials->where('status', true)->get(['id', 'title', 'code', 'material_code']);

        return Inertia::render('Quality/Configuration/Schedules/Create', [
            'testTypes' => $testTypes,
            'materials' => $materials,
            'frequencyTypes' => [
                'PER_BATCH',
                'PER_LOT',
                'PER_DELIVERY',
                'PER_SHIFT',
                'HOURLY',
                'EVERY_N_HOURS',
                'DAILY',
                'WEEKLY',
                'MONTHLY',
                'MANUAL'
            ],
        ]);
    }

    public function edit(QcTestSchedule $schedule)
    {
        $ctx = app(PlantContextService::class);
        $plantId = $ctx->plantId();

        $testTypesQuery = QcTestType::where('is_active', true);
        if ($plantId) {
            $testTypesQuery->where(function ($q) use ($plantId) {
                $q->where('plant_id', $plantId)
                  ->orWhereNull('plant_id');
            });
        }
        $testTypes = $testTypesQuery->get(['id', 'code', 'name']);

        $materials = Product::query();
        if ($plantId) {
            $materials->where('plant_id', $plantId);
        }
        $materials = $materials->where('status', true)->get(['id', 'title', 'code', 'material_code']);

        return Inertia::render('Quality/Configuration/Schedules/Edit', [
            'schedule' => $schedule,
            'testTypes' => $testTypes,
            'materials' => $materials,
            'frequencyTypes' => [
                'PER_BATCH',
                'PER_LOT',
                'PER_DELIVERY',
                'PER_SHIFT',
                'HOURLY',
                'EVERY_N_HOURS',
                'DAILY',
                'WEEKLY',
                'MONTHLY',
                'MANUAL'
            ],
        ]);
    }

    public function store(Request $request)
    {
        $ctx = app(PlantContextService::class);
        $plantId = $ctx->plantId() ?: 1;

        if ($request->has('frequency_type')) {
            $request->merge([
                'frequency_type' => strtoupper(trim($request->frequency_type))
            ]);
        }

        $allowedTypes = ['PER_BATCH', 'PER_LOT', 'PER_DELIVERY', 'PER_SHIFT', 'HOURLY', 'EVERY_N_HOURS', 'DAILY', 'WEEKLY', 'MONTHLY', 'MANUAL'];

        $validated = $request->validate([
            'material_id' => 'required|exists:mm_products,id',
            'test_type_id' => 'required|exists:qc_test_types,id',
            'frequency_type' => 'required|string|in:' . implode(',', $allowedTypes),
            'frequency_value' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
        ]);

        $validated['plant_id'] = $plantId;
        $validated['created_by'] = auth()->id() ?: 1;

        QcTestSchedule::create($validated);

        return redirect()->route('quality.config.test-schedules.index')->with('success', 'Testing schedule created.');
    }

    public function update(Request $request, QcTestSchedule $schedule)
    {
        if ($request->has('frequency_type')) {
            $request->merge([
                'frequency_type' => strtoupper(trim($request->frequency_type))
            ]);
        }

        $allowedTypes = ['PER_BATCH', 'PER_LOT', 'PER_DELIVERY', 'PER_SHIFT', 'HOURLY', 'EVERY_N_HOURS', 'DAILY', 'WEEKLY', 'MONTHLY', 'MANUAL'];

        $validated = $request->validate([
            'material_id' => 'required|exists:mm_products,id',
            'test_type_id' => 'required|exists:qc_test_types,id',
            'frequency_type' => 'required|string|in:' . implode(',', $allowedTypes),
            'frequency_value' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
        ]);

        $validated['updated_by'] = auth()->id() ?: 1;
        $schedule->update($validated);

        return redirect()->route('quality.config.test-schedules.index')->with('success', 'Testing schedule updated.');
    }

    public function destroy(QcTestSchedule $schedule)
    {
        $schedule->deleted_by = auth()->id();
        $schedule->save();
        $schedule->delete();

        return redirect()->route('quality.config.test-schedules.index')->with('success', 'Testing schedule removed.');
    }
}
