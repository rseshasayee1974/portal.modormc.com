<?php

namespace App\Http\Controllers\QC;

use App\Http\Controllers\Controller;
use App\Models\QC\QcTestType;
use App\Models\QC\QcTestParameter;
use App\Models\QC\QcMaterialTest;
use App\Models\QC\QcTestSchedule;
use App\Models\Product;
use App\Services\PlantContextService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\Rule;

class QCTestTypeController extends Controller
{
    public function index(Request $request)
    {
        $ctx = app(PlantContextService::class);
        $plantId = $ctx->plantId();

        $query = QcTestType::with(['parameters', 'materialMappings.material', 'schedules.material']);
        if ($plantId) {
            $query->where(function ($q) use ($plantId) {
                $q->where('plant_id', $plantId)
                  ->orWhereNull('plant_id');
            });
        }

        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%")
                  ->orWhere('standard_reference', 'like', "%{$search}%");
            });
        }

        if ($request->category && $request->category !== 'All') {
            $query->where('category', $request->category);
        }

        if ($request->filled('status') && $request->status !== 'All') {
            if ($request->status === 'Active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'Inactive') {
                $query->where('is_active', false);
            }
        }

        if ($request->filled('layout_type') && $request->layout_type !== 'All') {
            $query->where('layout_type', $request->layout_type);
        }

        $perPage = (int) $request->input('per_page', 15);
        if (!in_array($perPage, [10, 15, 25, 30, 50, 100])) {
            $perPage = 15;
        }

        $testTypes = $query->orderBy('name')->paginate($perPage)->withQueryString();

        $materials = Product::query();
        if ($plantId) {
            $materials->where('plant_id', $plantId);
        }
        $materials = $materials->where('status', true)->get(['id', 'title', 'code', 'material_code']);

        $editingTestType = null;
        if ($request->filled('edit')) {
            $editingTestType = QcTestType::with(['parameters'])->find($request->edit);
        }

        return Inertia::render('Quality/Configuration/TestTypes/Index', [
            'testTypes' => $testTypes,
            'materials' => $materials,
            'filters' => $request->only(['search', 'category', 'status', 'layout_type', 'per_page', 'edit']),
            'categories' => ['Aggregate', 'Cement', 'Concrete', 'Admixture', 'Water', 'General'],
            'editingTestType' => $editingTestType,
        ]);
    }

    public function create()
    {
        return redirect()->route('quality.config.test-types.index');
    }

    public function store(Request $request)
    {
        $ctx = app(PlantContextService::class);
        $plantId = $ctx->plantId();

        $category = ['Aggregate', 'Cement', 'Concrete', 'Admixture', 'Water', 'General'];
        $calculation_type = ['formula', 'manual', 'custom_class'];

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'code' => 'required|string|max:50',
            'category' => 'required|string|in:' . implode(',', $category),
            'material_type' => 'nullable|string|max:50',
            'standard_reference' => 'nullable|string|max:150',
            'calculation_type' => 'required|string|in:' . implode(',', $calculation_type),
            'layout_type' => 'nullable|string|max:50',
            'grid_config' => 'nullable|array',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $validated['layout_type'] = !empty($validated['layout_type']) ? $validated['layout_type'] : 'SINGLE_TRIAL';
        $validated['plant_id'] = $plantId;
        $validated['created_by'] = auth()->id();

        $testType = QcTestType::create($validated);

        return redirect()->route('quality.config.test-types.index')->with('success', 'QC Test Type created successfully.');
    }

    public function edit(QcTestType $test_type)
    {
        return redirect()->route('quality.config.test-types.index', ['edit' => $test_type->id]);
    }

    public function update(Request $request, QcTestType $test_type)
    {
        $category = ['Aggregate', 'Cement', 'Concrete', 'Admixture', 'Water', 'General'];
        $calculation_type = ['formula', 'manual', 'custom_class'];

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'code' => 'required|string|max:50',
            'category' => 'required|string|in:' . implode(',', $category),
            'material_type' => 'nullable|string|max:50',
            'standard_reference' => 'nullable|string|max:150',
            'calculation_type' => 'required|string|in:' . implode(',', $calculation_type),
            'layout_type' => 'nullable|string|max:50',
            'grid_config' => 'nullable|array',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $validated['layout_type'] = !empty($validated['layout_type']) ? $validated['layout_type'] : 'SINGLE_TRIAL';
        $validated['updated_by'] = auth()->id();
        $test_type->update($validated);

        return redirect()->route('quality.config.test-types.index')->with('success', 'QC Test Type updated successfully.');
    }

    public function toggleActive(QcTestType $test_type)
    {
        $test_type->is_active = !$test_type->is_active;
        $test_type->updated_by = auth()->id();
        $test_type->save();

        return redirect()->back()->with('success', 'Test type status toggled.');
    }

    public function destroy(QcTestType $test_type)
    {
        $test_type->updateQuietly([
            'deleted_by' => auth()->id() ?: 1,
        ]);
        $test_type->delete();

        return redirect()->back()->with('success', 'QC Test Type deactivated/archived.');
    }
}
