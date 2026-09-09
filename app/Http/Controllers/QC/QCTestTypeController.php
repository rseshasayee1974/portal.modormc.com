<?php

namespace App\Http\Controllers\QC;

use App\Http\Controllers\Controller;
use App\Models\ConcreteGrade;
use App\Models\QC\QcUnit;
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
        $product = Product::query()
            ->whereNull('deleted_at')
            ->where('plant_id', session('active_plant_id'))
            ->where('is_service', 0)
            ->with('category:id,name,code')
            ->get();
            
        $concrete_grade = ConcreteGrade::whereNull('deleted_at')
            ->where('plant_id', session('active_plant_id'))
            ->get();  
                  
        $testType = QcTestType::with('parameters')
            ->whereNull('deleted_at')
            ->where('plant_id', session('active_plant_id'))
            ->orderBy('id', 'desc')
            ->get();

        $units = QcUnit::query()
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('plant_id')
                  ->orWhere('plant_id', session('active_plant_id'));
            })
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'symbol', 'dimension']);

        return Inertia::render('Quality/Configuration/TestTypes/Index', [
            'products' => $product,
            'concrete_grade' => $concrete_grade,
            'testTypes' => $testType,
            'units' => $units,
        ]);
    }

    public function create()
    {
        return redirect()->route('quality.config.test-types.index');
    }

    public function store(Request $request)
    {
        
        $validated = $request->validate([
            'name' => 'nullable|string|max:150',
            'code' => 'nullable|string|max:50',
            'category' => 'nullable|string|max:50',
            'material_type' => 'nullable|max:150',
            'standard_reference' => 'nullable|string|max:150',
            'calculation_type' => 'nullable|string',
            'layout_type' => 'nullable|string|max:50',
            'grid_config' => 'nullable|array',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'parameters' => 'nullable|array',
            'parameters.*.name' => 'required|string|max:150',
            'parameters.*.age' => 'nullable|string|max:50',
            'parameters.*.target' => 'nullable|numeric',
            'parameters.*.min' => 'nullable|numeric',
            'parameters.*.unit' => 'nullable|string|max:30',
        ]);

        $materialType = $request->material_type ?: ($request->input('grid_config.concrete_grade') ?: 'Concrete');
        $qcTestType = $request->input('grid_config.qc_test_type') ?: 'Compressive';
        $category = $request->category ?: ($request->input('grid_config.category') ?: 'Concrete');
        $plantId = session('active_plant_id');
        $name = $request->name ?: "{$materialType} {$qcTestType}";
        $isActive = $request->has('is_active') ? (bool)$request->is_active : true;

        if ($isActive) {
            $existingActive = QcTestType::query()
                ->where('plant_id', $plantId)
                ->where('category', $category)
                ->where('material_type', $materialType)
                ->where('name', $name)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->exists();

            if ($existingActive) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'name' => ["An active QC test type already exists for '{$name}' with material '{$materialType}' in category '{$category}' for this plant."],
                ]);
            }
        }

        $code = $request->code ?: strtoupper(\Illuminate\Support\Str::slug("{$materialType}_{$qcTestType}", '_'));
        if (strlen($code) > 50) {
            $code = substr($code, 0, 50);
        }

        $validated['name'] = $name;
        $validated['code'] = $code;
        $validated['category'] = $category;
        $validated['material_type'] = $materialType;
        $validated['standard_reference'] = $request->standard_reference ?: ($request->input('grid_config.standard') ?: 'IS 516');
        $validated['calculation_type'] = $request->calculation_type ?: 'formula';
        $validated['layout_type'] = $request->layout_type ?: 'SINGLE_TRIAL';
        $validated['plant_id'] = $plantId;
        $validated['created_by'] = \Auth::id();
        $validated['is_active'] = $isActive;

        \DB::transaction(function () use ($validated, $request) {
            $testType = QcTestType::create($validated);

            if (!empty($request->parameters) && is_array($request->parameters)) {
                foreach ($request->parameters as $idx => $param) {
                    $cleanAge = preg_replace('/[^0-9.]/', '', (string)($param['age'] ?? ''));
                    $pName = $param['name'] ?? ('Parameter ' . ($idx + 1));
                    $pCode = strtoupper(\Illuminate\Support\Str::slug($testType->code . '_' . $pName, '_'));
                    if (strlen($pCode) > 50) {
                        $pCode = substr($pCode, 0, 50);
                    }

                    $testType->parameters()->create([
                        'test_type_id' => $testType->id,
                        'name' => $pName,
                        'code' => $pCode,
                        'data_type' => 'numeric',
                        'unit' => $param['unit'] ?? ($request->input('grid_config.unit') ?: 'MPa'),
                        'default_value' => $cleanAge ?: null,
                        'target_value' => isset($param['target']) && $param['target'] !== '' ? $param['target'] : null,
                        'min_value' => isset($param['min']) && $param['min'] !== '' ? $param['min'] : null,
                        'rule_type' => QcTestParameter::RULE_TYPE_GREATER_THAN_OR_EQUAL,
                        'display_order' => $idx + 1,
                        'is_required' => true,
                        'is_active' => true,
                        'created_by' => \Auth::id(),
                    ]);
                }
            }
        });

        return redirect()->route('quality.config.test-types.index')->with('success', 'QC Master defined successfully.');
    }

    public function edit(QcTestType $test_type)
    {
        return redirect()->route('quality.config.test-types.index', ['edit' => $test_type->id]);
    }

    public function update(Request $request, QcTestType $test_type)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:150',
            'code' => 'nullable|string|max:50',
            'category' => 'nullable|string|max:50',
            'material_type' => 'nullable|max:150',
            'standard_reference' => 'nullable|string|max:150',
            'calculation_type' => 'nullable|string',
            'layout_type' => 'nullable|string|max:50',
            'grid_config' => 'nullable|array',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'parameters' => 'nullable|array',
            'parameters.*.name' => 'required|string|max:150',
            'parameters.*.age' => 'nullable|string|max:50',
            'parameters.*.target' => 'nullable|numeric',
            'parameters.*.min' => 'nullable|numeric',
            'parameters.*.unit' => 'nullable|string|max:30',
        ]);

        $materialType = $request->material_type ?: ($request->input('grid_config.concrete_grade') ?: ($test_type->material_type ?: 'Concrete'));
        $qcTestType = $request->input('grid_config.qc_test_type') ?: 'Compressive';
        $category = $request->category ?: ($test_type->category ?: 'Concrete');
        $plantId = $test_type->plant_id ?: session('active_plant_id');
        $name = $request->name ?: ($test_type->name ?: "{$materialType} {$qcTestType}");
        $isActive = $request->has('is_active') ? (bool)$request->is_active : (bool)$test_type->is_active;

        if ($isActive) {
            $existingActive = QcTestType::query()
                ->where('id', '!=', $test_type->id)
                ->where('plant_id', $plantId)
                ->where('category', $category)
                ->where('material_type', $materialType)
                ->where('name', $name)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->exists();

            if ($existingActive) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'name' => ["An active QC test type already exists for '{$name}' with material '{$materialType}' in category '{$category}' for this plant."],
                ]);
            }
        }

        $code = $request->code ?: ($test_type->code ?: strtoupper(\Illuminate\Support\Str::slug("{$materialType}_{$qcTestType}", '_')));
        if (strlen($code) > 50) {
            $code = substr($code, 0, 50);
        }

        $validated['name'] = $name;
        $validated['code'] = $code;
        $validated['category'] = $category;
        $validated['material_type'] = $materialType;
        $validated['standard_reference'] = $request->standard_reference ?: ($request->input('grid_config.standard') ?: ($test_type->standard_reference ?: 'IS 516'));
        $validated['calculation_type'] = $request->calculation_type ?: ($test_type->calculation_type ?: 'formula');
        $validated['layout_type'] = $request->layout_type ?: ($test_type->layout_type ?: 'SINGLE_TRIAL');
        $validated['updated_by'] = \Auth::id();
        $validated['is_active'] = $isActive;

        \DB::transaction(function () use ($test_type, $validated, $request) {
            $test_type->update($validated);

            if ($request->has('parameters') && is_array($request->parameters)) {
                // Remove existing parameters and recreate fresh list
                $test_type->parameters()->forceDelete();

                foreach ($request->parameters as $idx => $param) {
                    $cleanAge = preg_replace('/[^0-9.]/', '', (string)($param['age'] ?? ''));
                    $pName = $param['name'] ?? ('Parameter ' . ($idx + 1));
                    $pCode = strtoupper(\Illuminate\Support\Str::slug($test_type->code . '_' . $pName, '_'));
                    if (strlen($pCode) > 50) {
                        $pCode = substr($pCode, 0, 50);
                    }

                    $test_type->parameters()->create([
                        'test_type_id' => $test_type->id,
                        'name' => $pName,
                        'code' => $pCode,
                        'data_type' => 'numeric',
                        'unit' => $param['unit'] ?? ($request->input('grid_config.unit') ?: 'MPa'),
                        'default_value' => $cleanAge ?: null,
                        'target_value' => isset($param['target']) && $param['target'] !== '' ? $param['target'] : null,
                        'min_value' => isset($param['min']) && $param['min'] !== '' ? $param['min'] : null,
                        'rule_type' => QcTestParameter::RULE_TYPE_GREATER_THAN_OR_EQUAL,
                        'display_order' => $idx + 1,
                        'is_required' => true,
                        'is_active' => true,
                        'created_by' => \Auth::id(),
                    ]);
                }
            }
        });

        return redirect()->route('quality.config.test-types.index')->with('success', 'QC Master updated successfully.');
    }

    public function toggleActive(QcTestType $test_type)
    {
        $newStatus = !$test_type->is_active;

        if ($newStatus) {
            $existingActive = QcTestType::query()
                ->where('id', '!=', $test_type->id)
                ->where('plant_id', $test_type->plant_id)
                ->where('category', $test_type->category)
                ->where('material_type', $test_type->material_type)
                ->where('name', $test_type->name)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->exists();

            if ($existingActive) {
                return redirect()->back()->with('error', "Cannot activate: Another active test type already exists for '{$test_type->name}' ({$test_type->material_type}) in category '{$test_type->category}'.");
            }
        }

        $test_type->is_active = $newStatus;
        $test_type->updated_by = auth()->id();
        $test_type->save();

        return redirect()->back()->with('success', 'Test type status updated successfully.');
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