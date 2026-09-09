<?php

namespace App\Http\Controllers\QC;

use App\Http\Controllers\Controller;
use App\Models\QC\QcTestType;
use App\Models\QC\QcTestParameter;
use App\Services\PlantContextService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class QCTestParameterController extends Controller
{
    public function index(Request $request)
    {
        $ctx = app(PlantContextService::class);
        $plantId = $ctx->plantId();

        $testTypesQuery = QcTestType::with('parameters');
        if ($plantId) {
            $testTypesQuery->where(function ($q) use ($plantId) {
                $q->where('plant_id', $plantId)
                  ->orWhereNull('plant_id');
            });
        }
        $testTypes = $testTypesQuery->get(['id', 'code', 'name', 'category']);

        $selectedTestTypeId = $request->test_type_id ?? $testTypes->first()?->id;

        $parameters = [];
        if ($selectedTestTypeId) {
            $parameters = QcTestParameter::where('test_type_id', $selectedTestTypeId)
                ->orderBy('display_order')
                ->get();
        }

        $units = \App\Models\QC\QcUnit::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'symbol', 'dimension']);

        return Inertia::render('Quality/Configuration/Parameters/Index', [
            'testTypes' => $testTypes,
            'selectedTestTypeId' => (int) $selectedTestTypeId,
            'parameters' => $parameters,
            'units' => $units,
            'ruleTypes' => QcTestParameter::ruleTypes(),
            'ruleTypeLabels' => QcTestParameter::ruleTypeLabels(),
        ]);
    }

    public function create(Request $request)
    {
        $ctx = app(PlantContextService::class);
        $plantId = $ctx->plantId();

        $testTypesQuery = QcTestType::query();
        if ($plantId) {
            $testTypesQuery->where(function ($q) use ($plantId) {
                $q->where('plant_id', $plantId)
                  ->orWhereNull('plant_id');
            });
        }
        $testTypes = $testTypesQuery->get(['id', 'code', 'name', 'category']);
        $selectedTestTypeId = (int) ($request->test_type_id ?? $testTypes->first()?->id);
        $units = \App\Models\QC\QcUnit::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'symbol', 'dimension']);

        return Inertia::render('Quality/Configuration/Parameters/Create', [
            'testTypes' => $testTypes,
            'selectedTestTypeId' => $selectedTestTypeId,
            'units' => $units,
            'ruleTypes' => QcTestParameter::ruleTypes(),
            'ruleTypeLabels' => QcTestParameter::ruleTypeLabels(),
        ]);
    }

    public function store(Request $request)
    {
        if ($request->has('data_type')) {
            $request->merge([
                'data_type' => trim($request->data_type)
            ]);
        }

        if ($request->filled('rule_type')) {
            $request->merge([
                'rule_type' => strtoupper(trim((string) $request->rule_type))
            ]);
        } else {
            $request->merge([
                'rule_type' => null
            ]);
        }

        $validated = $request->validate([
            'test_type_id' => 'required|exists:mm_qc_test_types,id',
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:150',
            'data_type' => 'nullable|string|max:50',
            'unit' => 'nullable|max:30',
            'is_required' => 'boolean',
            'is_calculated' => 'boolean',
            'formula' => 'nullable|string|max:255',
            'default_value' => 'nullable|string|max:255',
            'options' => 'nullable|array',
            'display_order' => 'integer',
            'rule_type' => ['nullable', 'string', Rule::in(QcTestParameter::RULE_TYPES)],
            'min_value' => 'nullable|numeric',
            'max_value' => 'nullable|numeric',
            'target_value' => 'nullable|numeric',
            'tolerance' => 'nullable|numeric',
            'standard_reference' => 'nullable|string|max:150',
        ]);

        $validated['data_type'] = !empty($validated['data_type']) ? $validated['data_type'] : 'text';
        $validated['code'] = strtoupper(trim($validated['code']));
        $validated['default_value'] = isset($validated['default_value']) && trim($validated['default_value']) !== '' ? trim($validated['default_value']) : null;
        $validated['rule_type'] = !empty($validated['rule_type']) ? strtoupper(trim($validated['rule_type'])) : null;
        $validated['min_value'] = isset($validated['min_value']) && $validated['min_value'] !== '' ? $validated['min_value'] : null;
        $validated['max_value'] = isset($validated['max_value']) && $validated['max_value'] !== '' ? $validated['max_value'] : null;
        $validated['target_value'] = isset($validated['target_value']) && $validated['target_value'] !== '' ? $validated['target_value'] : null;
        $validated['tolerance'] = isset($validated['tolerance']) && $validated['tolerance'] !== '' ? $validated['tolerance'] : null;
        $validated['standard_reference'] = isset($validated['standard_reference']) && trim($validated['standard_reference']) !== '' ? trim($validated['standard_reference']) : null;
        $validated['created_by'] = auth()->id() ?: 1;

        QcTestParameter::create($validated);

        return redirect()->route('quality.config.test-parameters.index', ['test_type_id' => $validated['test_type_id']])->with('success', 'Parameter and acceptance rule saved successfully.');
    }

    public function edit(QcTestParameter $test_parameter)
    {
        $ctx = app(PlantContextService::class);
        $plantId = $ctx->plantId();

        $testTypesQuery = QcTestType::query();
        if ($plantId) {
            $testTypesQuery->where(function ($q) use ($plantId) {
                $q->where('plant_id', $plantId)
                  ->orWhereNull('plant_id');
            });
        }
        $testTypes = $testTypesQuery->get(['id', 'code', 'name', 'category']);
        $units = \App\Models\QC\QcUnit::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'symbol', 'dimension']);

        return Inertia::render('Quality/Configuration/Parameters/Edit', [
            'parameter' => $test_parameter,
            'testTypes' => $testTypes,
            'units' => $units,
            'ruleTypes' => QcTestParameter::ruleTypes(),
            'ruleTypeLabels' => QcTestParameter::ruleTypeLabels(),
        ]);
    }

    public function update(Request $request, QcTestParameter $test_parameter)
    {
        if ($request->has('data_type')) {
            $request->merge([
                'data_type' => trim($request->data_type)
            ]);
        }

        if ($request->filled('rule_type')) {
            $request->merge([
                'rule_type' => strtoupper(trim((string) $request->rule_type))
            ]);
        } else {
            $request->merge([
                'rule_type' => null
            ]);
        }
        
        $validated = $request->validate([
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:150',
            'data_type' => 'nullable|string|max:50',
            'unit' => 'nullable|max:30',
            'is_required' => 'boolean',
            'is_calculated' => 'boolean',
            'formula' => 'nullable|string|max:255',
            'default_value' => 'nullable|string|max:255',
            'options' => 'nullable|array',
            'display_order' => 'integer',
            'rule_type' => ['nullable', 'string', Rule::in(QcTestParameter::RULE_TYPES)],
            'min_value' => 'nullable|numeric',
            'max_value' => 'nullable|numeric',
            'target_value' => 'nullable|numeric',
            'tolerance' => 'nullable|numeric',
            'standard_reference' => 'nullable|string|max:150',
        ]);

        $validated['data_type'] = !empty($validated['data_type']) ? $validated['data_type'] : ($test_parameter->data_type ?: 'text');
        $validated['code'] = strtoupper(trim($validated['code']));
        $validated['default_value'] = isset($validated['default_value']) && trim($validated['default_value']) !== '' ? trim($validated['default_value']) : null;
        $validated['rule_type'] = !empty($validated['rule_type']) ? strtoupper(trim($validated['rule_type'])) : null;
        $validated['min_value'] = isset($validated['min_value']) && $validated['min_value'] !== '' ? $validated['min_value'] : null;
        $validated['max_value'] = isset($validated['max_value']) && $validated['max_value'] !== '' ? $validated['max_value'] : null;
        $validated['target_value'] = isset($validated['target_value']) && $validated['target_value'] !== '' ? $validated['target_value'] : null;
        $validated['tolerance'] = isset($validated['tolerance']) && $validated['tolerance'] !== '' ? $validated['tolerance'] : null;
        $validated['standard_reference'] = isset($validated['standard_reference']) && trim($validated['standard_reference']) !== '' ? trim($validated['standard_reference']) : null;
        $validated['updated_by'] = auth()->id() ?: 1;

        $test_parameter->update($validated);

        return redirect()->route('quality.config.test-parameters.index', ['test_type_id' => $test_parameter->test_type_id])->with('success', 'Parameter and acceptance rule updated successfully.');
    }

    public function destroy(QcTestParameter $test_parameter)
    {
        $test_parameter->updateQuietly([
            'deleted_by' => auth()->id() ?: 1,
        ]);
        $test_parameter->delete();

        return redirect()->back()->with('success', 'Parameter removed.');
    }
}
