<?php

namespace App\Http\Controllers\QC;

use App\Http\Controllers\Controller;
use App\Models\QC\QcSample;
use App\Models\QC\QcTest;
use App\Models\QC\QcTestType;
use App\Models\ConcreteGrade;
use App\Models\Product;
use App\Models\Patron;
use App\Models\PurchaseOrderHistory;
use App\Models\Batch;
use App\Models\Dispatch;
use App\Services\PlantContextService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class QCSampleController extends Controller
{
    public function index(Request $request)
    {
        $ctx = app(PlantContextService::class);
        $plantId = $ctx->plantId();

        $query = QcSample::with([
            'material',
            'concreteGrade',
            'customer',
            'batch',
            'dispatch.truck',
            'dispatch.mixDesign.concreteGrade',
            'sampler',
            'tests.testType'
        ]);

        if ($plantId) {
            $query->where('plant_id', $plantId);
        }

        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('sample_no', 'like', "%{$search}%")
                  ->orWhere('source_location', 'like', "%{$search}%")
                  ->orWhere('truck_no', 'like', "%{$search}%")
                  ->orWhere('site_name', 'like', "%{$search}%")
                  ->orWhereHas('concreteGrade', fn($cg) => $cg->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('material', fn($m) => $m->where('title', 'like', "%{$search}%"))
                  ->orWhereHas('customer', fn($c) => $c->where('legal_name', 'like', "%{$search}%"));
            });
        }

        if ($request->status && $request->status !== 'All') {
            $query->where('status', $request->status);
        }

        $samples = $query->orderBy('id', 'desc')->paginate(20)->withQueryString();

        $concreteGrades = ConcreteGrade::where('status', true)->get(['id', 'name']);

        $materials = Product::query();
        if ($plantId) {
            $materials->where('plant_id', $plantId);
        }
        $materials = $materials->where('status', true)->get(['id', 'title', 'code', 'material_code']);

        $customers = Patron::where('status', true)->get(['id', 'legal_name', 'code']);

        $dispatches = Dispatch::with(['customer:id,legal_name', 'unloadSite:id,name', 'truck:id,registration', 'mixDesign.concreteGrade:id,name'])
            ->whereNotNull('dispatch_no')
            ->orderBy('id', 'desc')
            ->limit(60)
            ->get(['id', 'dispatch_no', 'customer_id', 'unload_site_id', 'truck_id', 'batch_id', 'mixdesign_id', 'delivered_qty', 'dispatch_time']);

        $testTypesQuery = QcTestType::where('is_active', true);
        if ($plantId) {
            $testTypesQuery->where(function ($q) use ($plantId) {
                $q->where('plant_id', $plantId)
                  ->orWhereNull('plant_id');
            });
        }
        $testTypes = $testTypesQuery->get(['id', 'name', 'code', 'category', 'material_type']);

        return Inertia::render('Quality/Samples/Index', [
            'samples' => $samples,
            'concreteGrades' => $concreteGrades,
            'materials' => $materials,
            'customers' => $customers,
            'dispatches' => $dispatches,
            'testTypes' => $testTypes,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create()
    {
        $ctx = app(PlantContextService::class);
        $plantId = $ctx->plantId();

        $concreteGrades = ConcreteGrade::where('status', true)->get(['id', 'name']);

        $materials = Product::query();
        if ($plantId) {
            $materials->where('plant_id', $plantId);
        }
        $materials = $materials->where('status', true)->get(['id', 'title', 'code', 'material_code']);

        $customers = Patron::where('status', true)->get(['id', 'legal_name', 'code']);

        $dispatches = Dispatch::with(['customer:id,legal_name', 'unloadSite:id,name', 'truck:id,registration', 'mixDesign.concreteGrade:id,name'])
            ->whereNotNull('dispatch_no')
            ->orderBy('id', 'desc')
            ->limit(60)
            ->get(['id', 'dispatch_no', 'customer_id', 'unload_site_id', 'truck_id', 'batch_id', 'mixdesign_id', 'delivered_qty', 'dispatch_time']);

        $batches = Batch::orderBy('id', 'desc')->limit(40)->get(['id', 'batch_no']);

        $testTypesQuery = QcTestType::with('parameters')->where('is_active', true);
        if ($plantId) {
            $testTypesQuery->where(function ($q) use ($plantId) {
                $q->where('plant_id', $plantId)
                  ->orWhereNull('plant_id');
            });
        }
        $testTypes = $testTypesQuery->get(['id', 'name', 'code', 'category', 'material_type']);

        return Inertia::render('Quality/Samples/Create', [
            'concreteGrades' => $concreteGrades,
            'materials' => $materials,
            'customers' => $customers,
            'dispatches' => $dispatches,
            'batches' => $batches,
            'testTypes' => $testTypes,
        ]);
    }

    public function edit(QcSample $sample)
    {
        $ctx = app(PlantContextService::class);
        $plantId = $ctx->plantId();

        $sample->load(['tests.testType', 'concreteGrade', 'material', 'customer', 'batch', 'dispatch']);

        $concreteGrades = ConcreteGrade::where('status', true)->get(['id', 'name']);

        $materials = Product::query();
        if ($plantId) {
            $materials->where('plant_id', $plantId);
        }
        $materials = $materials->where('status', true)->get(['id', 'title', 'code', 'material_code']);

        $customers = Patron::where('status', true)->get(['id', 'legal_name', 'code']);

        $dispatches = Dispatch::with(['customer:id,legal_name', 'unloadSite:id,name', 'truck:id,registration', 'mixDesign.concreteGrade:id,name'])
            ->whereNotNull('dispatch_no')
            ->orderBy('id', 'desc')
            ->limit(60)
            ->get(['id', 'dispatch_no', 'customer_id', 'unload_site_id', 'truck_id', 'batch_id', 'mixdesign_id', 'delivered_qty', 'dispatch_time']);

        $batches = Batch::orderBy('id', 'desc')->limit(40)->get(['id', 'batch_no']);

        $testTypesQuery = QcTestType::with('parameters')->where('is_active', true);
        if ($plantId) {
            $testTypesQuery->where(function ($q) use ($plantId) {
                $q->where('plant_id', $plantId)
                  ->orWhereNull('plant_id');
            });
        }
        $testTypes = $testTypesQuery->get(['id', 'name', 'code', 'category', 'material_type']);

        return Inertia::render('Quality/Samples/Edit', [
            'sample' => $sample,
            'concreteGrades' => $concreteGrades,
            'materials' => $materials,
            'customers' => $customers,
            'dispatches' => $dispatches,
            'batches' => $batches,
            'testTypes' => $testTypes,
        ]);
    }

    public function store(Request $request)
    {
        $ctx = app(PlantContextService::class);
        $plantId = $ctx->plantId() ?: session('active_plant_id', 1);

        $validated = $request->validate([
            'sample_date' => 'required|date',
            'concrete_grade_id' => 'nullable|exists:mm_concrete_grades,id',
            'material_id' => 'nullable|exists:mm_products,id',
            'dispatch_id' => 'nullable|exists:mm_dispatches,id',
            'batch_id' => 'nullable|exists:mm_batches,id',
            'customer_id' => 'nullable|exists:mm_patrons,id',
            'truck_no' => 'nullable|string|max:100',
            'site_name' => 'nullable|string|max:150',
            'source_location' => 'nullable|string|max:150',
            'slump_mm' => 'nullable|numeric|min:0|max:500',
            'concrete_temp_c' => 'nullable|numeric|min:0|max:100',
            'ambient_temp_c' => 'nullable|numeric|min:0|max:100',
            'specimen_size' => 'nullable|string|max:50',
            'specimen_count' => 'nullable|integer|min:1|max:30',
            'curing_tank_id' => 'nullable|string|max:100',
            'sample_quantity' => 'nullable|string|max:50',
            'test_type_ids' => 'nullable|array',
            'test_type_ids.*' => 'exists:mm_qc_test_types,id',
            'remarks' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($validated, $plantId) {
            $datePrefix = date('Ymd');
            $count = QcSample::whereDate('created_at', now())->count() + 1;
            $sampleNo = 'SMP-' . $datePrefix . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

            $sample = QcSample::create([
                'plant_id' => $plantId ?? 1,
                'sample_no' => $sampleNo,
                'sample_date' => $validated['sample_date'],
                'concrete_grade_id' => $validated['concrete_grade_id'] ?? null,
                'material_id' => $validated['material_id'] ?? null,
                'dispatch_id' => $validated['dispatch_id'] ?? null,
                'batch_id' => $validated['batch_id'] ?? null,
                'customer_id' => $validated['customer_id'] ?? null,
                'truck_no' => $validated['truck_no'] ?? null,
                'site_name' => $validated['site_name'] ?? null,
                'source_location' => $validated['source_location'] ?? null,
                'slump_mm' => $validated['slump_mm'] ?? null,
                'concrete_temp_c' => $validated['concrete_temp_c'] ?? null,
                'ambient_temp_c' => $validated['ambient_temp_c'] ?? null,
                'specimen_size' => $validated['specimen_size'] ?: '150x150x150 mm',
                'specimen_count' => $validated['specimen_count'] ?: 6,
                'curing_tank_id' => $validated['curing_tank_id'] ?? null,
                'sample_quantity' => $validated['sample_quantity'] ?? null,
                'sampled_by' => auth()->id() ?: 1,
                'status' => 'pending_test',
                'remarks' => $validated['remarks'] ?? null,
                'created_by' => auth()->id() ?: 1,
            ]);

            // Resolve target test types
            $targetTestTypeIds = [];
            if (!empty($validated['test_type_ids'])) {
                $targetTestTypeIds = $validated['test_type_ids'];
            } elseif (!empty($validated['concrete_grade_id'])) {
                $grade = ConcreteGrade::find($validated['concrete_grade_id']);
                if ($grade) {
                    $targetTestTypeIds = QcTestType::where('is_active', true)
                        ->where(function ($q) use ($grade) {
                            $q->where('material_type', $grade->name)
                              ->orWhere('material_type', (string)$grade->id);
                        })
                        ->pluck('id')
                        ->toArray();
                }
            }

            if (empty($targetTestTypeIds)) {
                // Fallback to any active Concrete Compressive strength test type
                $targetTestTypeIds = QcTestType::where('is_active', true)
                    ->where(function ($q) {
                        $q->where('category', 'Concrete')
                          ->orWhere('name', 'like', '%Compressive%');
                    })
                    ->pluck('id')
                    ->take(1)
                    ->toArray();
            }

            // Create scheduled test events based on age parameters
            $testSeq = 1;
            foreach ($targetTestTypeIds as $testTypeId) {
                $testType = QcTestType::with('parameters')->find($testTypeId);
                if (!$testType) continue;

                $ageParams = $testType->parameters->filter(function ($p) {
                    return !empty($p->default_value) || preg_match('/(\d+)\s*(day|d)/i', $p->name);
                });

                if ($ageParams->isNotEmpty()) {
                    foreach ($ageParams as $param) {
                        preg_match('/(\d+)/', (string)($param->default_value ?: $param->name), $matches);
                        $age = !empty($matches[1]) ? (int)$matches[1] : 28;
                        $scheduledDate = date('Y-m-d', strtotime($validated['sample_date'] . " + {$age} days"));
                        $testNo = 'TST-' . $datePrefix . '-' . str_pad($count, 3, '0', STR_PAD_LEFT) . "-{$age}D";

                        QcTest::create([
                            'plant_id' => $plantId ?? 1,
                            'sample_id' => $sample->id,
                            'test_type_id' => $testType->id,
                            'test_no' => $testNo,
                            'scheduled_date' => $scheduledDate,
                            'age_days' => $age,
                            'target_strength' => $param->target_value,
                            'min_strength' => $param->min_value,
                            'unit' => $param->unit ?: 'MPa',
                            'overall_status' => 'pending',
                            'approval_status' => 'draft',
                            'created_by' => auth()->id() ?: 1,
                        ]);
                    }
                } else {
                    // Default single 28-day test
                    $testNo = 'TST-' . $datePrefix . '-' . str_pad($count, 3, '0', STR_PAD_LEFT) . '-28D';
                    QcTest::create([
                        'plant_id' => $plantId ?? 1,
                        'sample_id' => $sample->id,
                        'test_type_id' => $testType->id,
                        'test_no' => $testNo,
                        'scheduled_date' => date('Y-m-d', strtotime($validated['sample_date'] . ' + 28 days')),
                        'age_days' => 28,
                        'unit' => 'MPa',
                        'overall_status' => 'pending',
                        'approval_status' => 'draft',
                        'created_by' => auth()->id() ?: 1,
                    ]);
                }
            }

            return redirect()->route('quality.samples.index')->with('success', "Concrete Cube Sample {$sampleNo} logged and testing schedule created.");
        });
    }

    public function update(Request $request, QcSample $sample)
    {
        $validated = $request->validate([
            'sample_date' => 'required|date',
            'concrete_grade_id' => 'nullable|exists:mm_concrete_grades,id',
            'material_id' => 'nullable|exists:mm_products,id',
            'dispatch_id' => 'nullable|exists:mm_dispatches,id',
            'batch_id' => 'nullable|exists:mm_batches,id',
            'customer_id' => 'nullable|exists:mm_patrons,id',
            'truck_no' => 'nullable|string|max:100',
            'site_name' => 'nullable|string|max:150',
            'source_location' => 'nullable|string|max:150',
            'slump_mm' => 'nullable|numeric|min:0|max:500',
            'concrete_temp_c' => 'nullable|numeric|min:0|max:100',
            'ambient_temp_c' => 'nullable|numeric|min:0|max:100',
            'specimen_size' => 'nullable|string|max:50',
            'specimen_count' => 'nullable|integer|min:1|max:30',
            'curing_tank_id' => 'nullable|string|max:100',
            'sample_quantity' => 'nullable|string|max:50',
            'status' => 'nullable|string',
            'remarks' => 'nullable|string',
        ]);

        $validated['updated_by'] = auth()->id();
        $sample->update($validated);

        return redirect()->route('quality.samples.index')->with('success', 'Concrete Sample details updated.');
    }

    public function destroy(QcSample $sample)
    {
        $sample->deleted_by = auth()->id();
        $sample->save();
        $sample->delete();

        return redirect()->route('quality.samples.index')->with('success', 'QC Sample removed.');
    }
}