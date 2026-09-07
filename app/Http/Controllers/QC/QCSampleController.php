<?php

namespace App\Http\Controllers\QC;

use App\Http\Controllers\Controller;
use App\Models\QC\QcSample;
use App\Models\QC\QcTest;
use App\Models\QC\QcMaterialTest;
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

        $query = QcSample::with(['material', 'supplier', 'customer', 'inward', 'batch', 'dispatch', 'sampler', 'tests.testType']);

        if ($plantId) {
            $query->where('plant_id', $plantId);
        }

        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('sample_no', 'like', "%{$search}%")
                  ->orWhere('source_location', 'like', "%{$search}%")
                  ->orWhereHas('material', fn($m) => $m->where('title', 'like', "%{$search}%"));
            });
        }

        if ($request->status && $request->status !== 'All') {
            $query->where('status', $request->status);
        }

        $samples = $query->orderBy('id', 'desc')->paginate(15)->withQueryString();

        $materials = Product::query();
        if ($plantId) {
            $materials->where('plant_id', $plantId);
        }
        $materials = $materials->where('status', true)->get(['id', 'title', 'code', 'material_code']);

        $suppliers = Patron::where('status', true)->get(['id', 'legal_name', 'code']);
        $customers = Patron::where('status', true)->get(['id', 'legal_name', 'code']);

        $inwards = PurchaseOrderHistory::orderBy('id', 'desc')->limit(50)->get(['id', 'inward_no']);
        $batches = Batch::orderBy('id', 'desc')->limit(50)->get(['id', 'batch_no']);
        $dispatches = Dispatch::orderBy('id', 'desc')->limit(50)->get(['id', 'dispatch_no']);

        $testTypesQuery = \App\Models\QC\QcTestType::where('is_active', true);
        if ($plantId) {
            $testTypesQuery->where(function ($q) use ($plantId) {
                $q->where('plant_id', $plantId)
                  ->orWhereNull('plant_id');
            });
        }
        $testTypes = $testTypesQuery->get(['id', 'name', 'code', 'category']);

        return Inertia::render('Quality/Samples/Index', [
            'samples' => $samples,
            'materials' => $materials,
            'suppliers' => $suppliers,
            'customers' => $customers,
            'inwards' => $inwards,
            'batches' => $batches,
            'dispatches' => $dispatches,
            'testTypes' => $testTypes,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create()
    {
        $ctx = app(PlantContextService::class);
        $plantId = $ctx->plantId();

        $materials = Product::query();
        if ($plantId) {
            $materials->where('plant_id', $plantId);
        }
        $materials = $materials->where('status', true)->get(['id', 'title', 'code', 'material_code']);

        $suppliers = Patron::where('status', true)->get(['id', 'legal_name', 'code']);
        $customers = Patron::where('status', true)->get(['id', 'legal_name', 'code']);

        $inwards = PurchaseOrderHistory::orderBy('id', 'desc')->limit(50)->get(['id', 'inward_no']);
        $batches = Batch::orderBy('id', 'desc')->limit(50)->get(['id', 'batch_no']);
        $dispatches = Dispatch::orderBy('id', 'desc')->limit(50)->get(['id', 'dispatch_no']);

        $testTypesQuery = \App\Models\QC\QcTestType::where('is_active', true);
        if ($plantId) {
            $testTypesQuery->where(function ($q) use ($plantId) {
                $q->where('plant_id', $plantId)
                  ->orWhereNull('plant_id');
            });
        }
        $testTypes = $testTypesQuery->get(['id', 'name', 'code', 'category']);

        return Inertia::render('Quality/Samples/Create', [
            'materials' => $materials,
            'suppliers' => $suppliers,
            'customers' => $customers,
            'inwards' => $inwards,
            'batches' => $batches,
            'dispatches' => $dispatches,
            'testTypes' => $testTypes,
        ]);
    }

    public function edit(QcSample $sample)
    {
        $ctx = app(PlantContextService::class);
        $plantId = $ctx->plantId();

        $sample->load(['tests.testType', 'material', 'supplier', 'customer', 'inward', 'batch', 'dispatch']);

        $materials = Product::query();
        if ($plantId) {
            $materials->where('plant_id', $plantId);
        }
        $materials = $materials->where('status', true)->get(['id', 'title', 'code', 'material_code']);

        $suppliers = Patron::where('status', true)->get(['id', 'legal_name', 'code']);
        $customers = Patron::where('status', true)->get(['id', 'legal_name', 'code']);

        $inwards = PurchaseOrderHistory::orderBy('id', 'desc')->limit(50)->get(['id', 'inward_no']);
        $batches = Batch::orderBy('id', 'desc')->limit(50)->get(['id', 'batch_no']);
        $dispatches = Dispatch::orderBy('id', 'desc')->limit(50)->get(['id', 'dispatch_no']);

        $testTypesQuery = \App\Models\QC\QcTestType::where('is_active', true);
        if ($plantId) {
            $testTypesQuery->where(function ($q) use ($plantId) {
                $q->where('plant_id', $plantId)
                  ->orWhereNull('plant_id');
            });
        }
        $testTypes = $testTypesQuery->get(['id', 'name', 'code', 'category']);

        return Inertia::render('Quality/Samples/Edit', [
            'sample' => $sample,
            'materials' => $materials,
            'suppliers' => $suppliers,
            'customers' => $customers,
            'inwards' => $inwards,
            'batches' => $batches,
            'dispatches' => $dispatches,
            'testTypes' => $testTypes,
        ]);
    }

    public function store(Request $request)
    {
        $ctx = app(PlantContextService::class);
        $plantId = $ctx->plantId() ?: 1;

        $validated = $request->validate([
            'sample_date' => 'required|date',
            'material_id' => 'required|exists:mm_products,id',
            'supplier_id' => 'nullable|exists:mm_patrons,id',
            'customer_id' => 'nullable|exists:mm_patrons,id',
            'inward_id' => 'nullable|exists:mm_purchase_order_history,id',
            'batch_id' => 'nullable|exists:mm_batches,id',
            'dispatch_id' => 'nullable|exists:mm_dispatches,id',
            'source_location' => 'nullable|string|max:150',
            'sample_quantity' => 'nullable|string|max:50',
            'test_type_ids' => 'nullable|array',
            'test_type_ids.*' => 'exists:qc_test_types,id',
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
                'material_id' => $validated['material_id'],
                'supplier_id' => $validated['supplier_id'] ?? null,
                'customer_id' => $validated['customer_id'] ?? null,
                'inward_id' => $validated['inward_id'] ?? null,
                'batch_id' => $validated['batch_id'] ?? null,
                'dispatch_id' => $validated['dispatch_id'] ?? null,
                'source_location' => $validated['source_location'] ?? null,
                'sample_quantity' => $validated['sample_quantity'] ?? null,
                'sampled_by' => auth()->id() ?: 1,
                'status' => 'pending_test',
                'remarks' => $validated['remarks'] ?? null,
                'created_by' => auth()->id() ?: 1,
            ]);

            // Determine test type IDs: use explicitly checked test_type_ids, or fallback to auto-configured Material-Test Mappings
            $targetTestTypeIds = !empty($validated['test_type_ids'])
                ? $validated['test_type_ids']
                : QcMaterialTest::where('material_id', $validated['material_id'])
                    ->where('is_active', true)
                    ->pluck('test_type_id')
                    ->toArray();

            $testCount = 1;
            foreach ($targetTestTypeIds as $testTypeId) {
                $testNo = 'TST-' . $datePrefix . '-' . str_pad($count, 3, '0', STR_PAD_LEFT) . '-' . $testCount++;
                QcTest::create([
                    'plant_id' => $plantId ?? 1,
                    'sample_id' => $sample->id,
                    'test_type_id' => $testTypeId,
                    'test_no' => $testNo,
                    'test_date' => $validated['sample_date'],
                    'tested_by' => auth()->id(),
                    'overall_status' => 'pending',
                    'approval_status' => 'draft',
                    'created_by' => auth()->id(),
                ]);
            }

            return redirect()->route('quality.samples.index')->with('success', "Sample {$sampleNo} logged and test assignments created.");
        });
    }

    public function update(Request $request, QcSample $sample)
    {
        $validated = $request->validate([
            'sample_date' => 'required|date',
            'material_id' => 'required|exists:mm_products,id',
            'supplier_id' => 'nullable|exists:mm_patrons,id',
            'customer_id' => 'nullable|exists:mm_patrons,id',
            'inward_id' => 'nullable|exists:mm_purchase_order_history,id',
            'batch_id' => 'nullable|exists:mm_batches,id',
            'dispatch_id' => 'nullable|exists:mm_dispatches,id',
            'source_location' => 'nullable|string|max:150',
            'sample_quantity' => 'nullable|string|max:50',
            'status' => 'nullable|string',
            'remarks' => 'nullable|string',
        ]);

        $validated['updated_by'] = auth()->id();
        $sample->update($validated);

        return redirect()->route('quality.samples.index')->with('success', 'QC Sample updated.');
    }

    public function destroy(QcSample $sample)
    {
        $sample->deleted_by = auth()->id();
        $sample->save();
        $sample->delete();

        return redirect()->route('quality.samples.index')->with('success', 'QC Sample removed.');
    }
}
