<?php

namespace App\Http\Controllers\QC;

use App\Http\Controllers\Controller;
use App\Models\QC\QcProductGradeConfig;
use App\Models\QC\QcConfigAgeMilestone;
use App\Models\QC\QcTestType;
use App\Models\QC\QcStandard;
use App\Models\Product;
use App\Models\ConcreteGrade;
use App\Services\PlantContextService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class QcProductGradeConfigController extends Controller
{
    public function index(Request $request)
    {
        $ctx = app(PlantContextService::class);
        $plantId = $ctx->plantId();

        $query = QcProductGradeConfig::with(['material', 'concreteGrade', 'testType', 'standard', 'milestones']);

        if ($plantId) {
            $query->where(function ($q) use ($plantId) {
                $q->where('plant_id', $plantId)->orWhereNull('plant_id');
            });
        }

        if ($request->material_id) {
            $query->where('material_id', $request->material_id);
        }

        if ($request->concrete_grade_id) {
            $query->where('concrete_grade_id', $request->concrete_grade_id);
        }

        $configs = $query->orderBy('id', 'desc')->paginate(15)->withQueryString();

        $materials = Product::where('status', true)->get(['id', 'title', 'code']);
        $grades = ConcreteGrade::where('is_active', true)->get(['id', 'name', 'code']);
        $testTypes = QcTestType::where('is_active', true)->get(['id', 'name', 'code', 'category']);
        $standards = QcStandard::where('is_active', true)->get(['id', 'name', 'code', 'organization']);

        return Inertia::render('Quality/Configs/Index', [
            'configs' => $configs,
            'materials' => $materials,
            'grades' => $grades,
            'testTypes' => $testTypes,
            'standards' => $standards,
            'filters' => $request->only(['material_id', 'concrete_grade_id']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'material_id' => 'required|exists:mm_products,id',
            'concrete_grade_id' => 'nullable|exists:mm_concrete_grades,id',
            'test_type_id' => 'required|exists:mm_qc_test_types,id',
            'standard_id' => 'nullable|exists:mm_qc_standards,id',
            'specimen_shape' => 'nullable|string|max:50',
            'specimen_dimensions' => 'nullable|string|max:100',
            'specimens_per_set' => 'nullable|integer|min:1|max:10',
            'total_sets' => 'nullable|integer|min:1|max:10',
            'formula_bindings' => 'nullable|array',
            'milestones' => 'nullable|array',
            'milestones.*.set_number' => 'required|integer',
            'milestones.*.age_days' => 'required|integer',
            'milestones.*.age_label' => 'required|string|max:50',
            'milestones.*.target_percentage' => 'nullable|numeric',
            'milestones.*.target_value' => 'nullable|numeric',
            'milestones.*.min_value' => 'nullable|numeric',
            'milestones.*.max_value' => 'nullable|numeric',
            'milestones.*.rule_type' => 'nullable|string',
            'milestones.*.tolerance' => 'nullable|numeric',
        ]);

        $ctx = app(PlantContextService::class);
        $plantId = $ctx->plantId();

        DB::transaction(function () use ($validated, $plantId) {
            $config = QcProductGradeConfig::create([
                'plant_id' => $plantId,
                'material_id' => $validated['material_id'],
                'concrete_grade_id' => $validated['concrete_grade_id'] ?? null,
                'test_type_id' => $validated['test_type_id'],
                'standard_id' => $validated['standard_id'] ?? null,
                'specimen_shape' => $validated['specimen_shape'] ?? 'Cube',
                'specimen_dimensions' => $validated['specimen_dimensions'] ?? '150 MM X 150 MM X 150 MM',
                'specimens_per_set' => $validated['specimens_per_set'] ?? 3,
                'total_sets' => $validated['total_sets'] ?? 3,
                'formula_bindings' => $validated['formula_bindings'] ?? null,
                'version' => 1,
                'is_active' => true,
                'created_by' => auth()->id(),
            ]);

            if (!empty($validated['milestones'])) {
                foreach ($validated['milestones'] as $order => $m) {
                    $config->milestones()->create([
                        'set_number' => $m['set_number'],
                        'age_days' => $m['age_days'],
                        'age_label' => $m['age_label'],
                        'target_percentage' => $m['target_percentage'] ?? null,
                        'target_value' => $m['target_value'] ?? null,
                        'min_value' => $m['min_value'] ?? null,
                        'max_value' => $m['max_value'] ?? null,
                        'rule_type' => $m['rule_type'] ?? 'GREATER_THAN_OR_EQUAL',
                        'tolerance' => $m['tolerance'] ?? null,
                        'display_order' => $order + 1,
                        'created_by' => auth()->id(),
                    ]);
                }
            }
        });

        return redirect()->back()->with('success', 'Product Grade QC Configuration saved successfully.');
    }

    public function update(Request $request, QcProductGradeConfig $config)
    {
        $validated = $request->validate([
            'material_id' => 'required|exists:mm_products,id',
            'concrete_grade_id' => 'nullable|exists:mm_concrete_grades,id',
            'test_type_id' => 'required|exists:mm_qc_test_types,id',
            'standard_id' => 'nullable|exists:mm_qc_standards,id',
            'specimen_shape' => 'nullable|string|max:50',
            'specimen_dimensions' => 'nullable|string|max:100',
            'specimens_per_set' => 'nullable|integer|min:1|max:10',
            'total_sets' => 'nullable|integer|min:1|max:10',
            'formula_bindings' => 'nullable|array',
            'milestones' => 'nullable|array',
            'milestones.*.set_number' => 'required|integer',
            'milestones.*.age_days' => 'required|integer',
            'milestones.*.age_label' => 'required|string|max:50',
            'milestones.*.target_percentage' => 'nullable|numeric',
            'milestones.*.target_value' => 'nullable|numeric',
            'milestones.*.min_value' => 'nullable|numeric',
            'milestones.*.max_value' => 'nullable|numeric',
            'milestones.*.rule_type' => 'nullable|string',
            'milestones.*.tolerance' => 'nullable|numeric',
        ]);

        DB::transaction(function () use ($config, $validated) {
            $config->update([
                'material_id' => $validated['material_id'],
                'concrete_grade_id' => $validated['concrete_grade_id'] ?? null,
                'test_type_id' => $validated['test_type_id'],
                'standard_id' => $validated['standard_id'] ?? null,
                'specimen_shape' => $validated['specimen_shape'] ?? $config->specimen_shape,
                'specimen_dimensions' => $validated['specimen_dimensions'] ?? $config->specimen_dimensions,
                'specimens_per_set' => $validated['specimens_per_set'] ?? $config->specimens_per_set,
                'total_sets' => $validated['total_sets'] ?? $config->total_sets,
                'formula_bindings' => $validated['formula_bindings'] ?? $config->formula_bindings,
                'version' => $config->version + 1, // increment version on master update
                'updated_by' => auth()->id(),
            ]);

            // Replace milestones
            $config->milestones()->delete();
            if (!empty($validated['milestones'])) {
                foreach ($validated['milestones'] as $order => $m) {
                    $config->milestones()->create([
                        'set_number' => $m['set_number'],
                        'age_days' => $m['age_days'],
                        'age_label' => $m['age_label'],
                        'target_percentage' => $m['target_percentage'] ?? null,
                        'target_value' => $m['target_value'] ?? null,
                        'min_value' => $m['min_value'] ?? null,
                        'max_value' => $m['max_value'] ?? null,
                        'rule_type' => $m['rule_type'] ?? 'GREATER_THAN_OR_EQUAL',
                        'tolerance' => $m['tolerance'] ?? null,
                        'display_order' => $order + 1,
                        'created_by' => auth()->id(),
                    ]);
                }
            }
        });

        return redirect()->back()->with('success', 'Product Grade QC Configuration updated successfully.');
    }

    public function destroy(QcProductGradeConfig $config)
    {
        $config->delete();
        return redirect()->back()->with('success', 'Configuration deleted successfully.');
    }
}
