<?php

namespace App\Http\Controllers\QC;

use App\Http\Controllers\Controller;
use App\Models\QC\QcTest;
use App\Models\QC\QcTestMeasurement;
use App\Models\QC\QcTestResult;
use App\Models\QC\QcTestParameter;
use App\Models\Image;
use App\Services\QC\FormulaEngine;
use App\Services\PlantContextService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class QCTestExecutionController extends Controller
{
    protected FormulaEngine $formulaEngine;

    public function __construct(FormulaEngine $formulaEngine)
    {
        $this->formulaEngine = $formulaEngine;
    }

    public function index(Request $request)
    {
        $ctx = app(PlantContextService::class);
        $plantId = $ctx->plantId();

        $query = QcTest::with([
            'plant',
            'sample.material',
            'sample.supplier',
            'sample.customer',
            'sample.concreteGrade',
            'sample.dispatch.truck',
            'testType.parameters',
            'measurements',
            'tester',
            'reviewer',
            'results.parameter',
            'photos'
        ]);

        if ($plantId) {
            $query->where('plant_id', $plantId);
        }

        $activeStatus = $request->status ?? 'all';
        if ($activeStatus !== 'all') {
            if ($activeStatus === 'pending') {
                $query->where('overall_status', 'pending');
            } elseif ($activeStatus === 'pass') {
                $query->whereIn('overall_status', ['pass', 'hold']);
            } elseif ($activeStatus === 'fail') {
                $query->whereIn('overall_status', ['fail', 'retest']);
            } else {
                $query->where('overall_status', $activeStatus);
            }
        }

        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('test_no', 'like', "%{$search}%")
                  ->orWhereHas('sample', fn($s) => $s->where('sample_no', 'like', "%{$search}%"))
                  ->orWhereHas('sample.concreteGrade', fn($g) => $g->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('testType', fn($t) => $t->where('name', 'like', "%{$search}%"));
            });
        }

        $tests = $query->orderBy('id', 'desc')->paginate(15)->withQueryString();
        
        $testTypesQuery = \App\Models\QC\QcTestType::query();
        if ($plantId) {
            $testTypesQuery->where(function ($q) use ($plantId) {
                $q->where('plant_id', $plantId)
                  ->orWhereNull('plant_id');
            });
        }
        $testTypes = $testTypesQuery->get(['id', 'code', 'name']);

        $statusCountsQuery = QcTest::query();
        if ($plantId) {
            $statusCountsQuery->where('plant_id', $plantId);
        }

        return Inertia::render('Quality/Tests/Index', [
            'tests' => $tests,
            'testTypes' => $testTypes,
            'filters' => [
                'search' => $request->search ?? '',
                'status' => $activeStatus,
            ],
            'statusCounts' => [
                'all' => (clone $statusCountsQuery)->count(),
                'pending' => (clone $statusCountsQuery)->where('overall_status', 'pending')->count(),
                'pass' => (clone $statusCountsQuery)->whereIn('overall_status', ['pass', 'hold'])->count(),
                'fail' => (clone $statusCountsQuery)->whereIn('overall_status', ['fail', 'retest'])->count(),
            ]
        ]);
    }

    public function pending(Request $request)
    {
        return redirect()->route('quality.tests.index', ['status' => 'pending']);
    }

    public function completed(Request $request)
    {
        return redirect()->route('quality.tests.index', ['status' => 'pass']);
    }

    public function failed(Request $request)
    {
        return redirect()->route('quality.tests.index', ['status' => 'fail']);
    }

    public function executeForm(QcTest $test)
    {
        $test->load([
            'plant',
            'sample.material',
            'sample.supplier',
            'sample.customer',
            'sample.concreteGrade',
            'sample.dispatch.truck',
            'sample.dispatch.salesOrder.customer',
            'testType.parameters',
            'measurements',
            'results.parameter',
            'photos',
            'tester',
            'reviewer'
        ]);

        $mergedRules = [];
        if ($test->testType && $test->testType->parameters) {
            foreach ($test->testType->parameters as $param) {
                if ($param->hasAcceptanceRule()) {
                    $mergedRules[] = [
                        'id' => $param->id,
                        'test_type_id' => $test->test_type_id,
                        'parameter_id' => $param->id,
                        'material_id' => null,
                        'rule_type' => $param->rule_type,
                        'min_value' => $param->min_value,
                        'max_value' => $param->max_value,
                        'target_value' => $param->target_value,
                        'tolerance' => $param->tolerance,
                        'unit' => $param->unit,
                        'standard_reference' => $param->standard_reference,
                        'is_active' => true,
                    ];
                }
            }
        }

        return Inertia::render('Quality/Tests/Execute', [
            'test' => $test,
            'rules' => $mergedRules,
        ]);
    }

    public function submitExecution(Request $request, QcTest $test)
    {
        $validated = $request->validate([
            'test_date' => 'required|date',
            'measurements' => 'nullable|array',
            'measurements.*.parameter_id' => 'nullable|exists:mm_qc_test_parameters,id',
            'measurements.*.value_numeric' => 'nullable|numeric',
            'measurements.*.row_index' => 'nullable|integer',
            'measurements.*.value_text' => 'nullable|string|max:255',
            'remarks' => 'nullable|string',
            'photos.*' => 'nullable|image|max:10240',
            'concrete_specimens' => 'nullable|array',
            'avg_strength' => 'nullable|numeric',
            'overall_status' => 'nullable|in:pass,fail,pending,hold,retest',
        ]);

        return DB::transaction(function () use ($validated, $test, $request) {
            $test->test_date = $validated['test_date'];
            $test->remarks = $validated['remarks'] ?? null;
            $test->tested_by = auth()->id() ?: 1;
            $test->overall_status = 'pending';

            // Delete old measurements and results for recalculation
            QcTestMeasurement::where('qc_test_id', $test->id)->delete();
            QcTestResult::where('qc_test_id', $test->id)->delete();

            // Check if this is a concrete specimen test
            if (!empty($validated['concrete_specimens'])) {
                $specimens = $validated['concrete_specimens'];
                
                // Locate or create the concrete test parameter
                $ageLabel = $test->age_days ? "{$test->age_days} Days Compressive Strength" : "Compressive Strength";
                $param = null;
                if ($test->testType) {
                    $param = $test->testType->parameters()
                        ->where(function ($q) use ($test) {
                            if ($test->age_days) {
                                $q->where('default_value', $test->age_days)
                                  ->orWhere('name', 'like', "%{$test->age_days}%");
                            }
                        })
                        ->first();

                    if (!$param) {
                        $param = $test->testType->parameters()->first();
                    }

                    if (!$param) {
                        $param = $test->testType->parameters()->create([
                            'name' => $ageLabel,
                            'code' => strtoupper(\Illuminate\Support\Str::slug($test->testType->code . '_' . $ageLabel, '_')),
                            'data_type' => 'numeric',
                            'unit' => $test->unit ?: 'MPa',
                            'default_value' => $test->age_days,
                            'target_value' => $test->target_strength,
                            'min_value' => $test->min_strength,
                            'rule_type' => QcTestParameter::RULE_TYPE_GREATER_THAN_OR_EQUAL,
                            'display_order' => 1,
                            'is_required' => true,
                            'is_active' => true,
                            'created_by' => auth()->id(),
                        ]);
                    }
                }

                $strengthValues = [];
                foreach ($specimens as $idx => $spec) {
                    $strength = isset($spec['strength_mpa']) && $spec['strength_mpa'] !== '' ? (float)$spec['strength_mpa'] : null;
                    if ($strength !== null) {
                        $strengthValues[] = $strength;
                    }
                    
                    $meta = [
                        'ident_mark' => $spec['ident_mark'] ?? ('Specimen #' . ($idx + 1)),
                        'weight_kg' => isset($spec['weight_kg']) && $spec['weight_kg'] !== '' ? (float)$spec['weight_kg'] : null,
                        'load_kn' => isset($spec['load_kn']) && $spec['load_kn'] !== '' ? (float)$spec['load_kn'] : null,
                        'density' => isset($spec['density']) && $spec['density'] !== '' ? (float)$spec['density'] : null,
                        'failure_type' => $spec['failure_type'] ?? 'Normal',
                        'strength_mpa' => $strength,
                    ];

                    QcTestMeasurement::create([
                        'qc_test_id' => $test->id,
                        'parameter_id' => $param ? $param->id : null,
                        'value_numeric' => $strength,
                        'value_text' => json_encode($meta),
                        'is_calculated' => false,
                        'row_index' => $idx,
                    ]);
                }

                $avgStrength = !empty($strengthValues) ? (array_sum($strengthValues) / count($strengthValues)) : (float)($validated['avg_strength'] ?? 0);

                // Evaluation against target / minimum strength
                $isPass = true;
                if ($test->min_strength !== null && $test->min_strength > 0) {
                    $isPass = ($avgStrength >= (float)$test->min_strength);
                } elseif ($test->target_strength !== null && $test->target_strength > 0) {
                    $isPass = ($avgStrength >= ((float)$test->target_strength * 0.85));
                }

                // Check IS 516 variation limit (+/- 15%)
                $hasOutlier = false;
                if (count($strengthValues) >= 3 && $avgStrength > 0) {
                    foreach ($strengthValues as $sv) {
                        $diffPct = abs(($sv - $avgStrength) / $avgStrength) * 100;
                        if ($diffPct > 15.0) {
                            $hasOutlier = true;
                            break;
                        }
                    }
                }

                $status = $isPass ? 'PASS' : 'FAIL';
                $overallStatus = $isPass ? 'pass' : 'fail';
                if ($request->filled('overall_status')) {
                    $overallStatus = $request->overall_status;
                }

                if ($param) {
                    QcTestResult::create([
                        'qc_test_id' => $test->id,
                        'parameter_id' => $param->id,
                        'final_value' => round($avgStrength, 2),
                        'final_text' => round($avgStrength, 2) . ' ' . ($test->unit ?: 'MPa'),
                        'status' => $status,
                        'criteria_snapshot' => [
                            'age_days' => $test->age_days,
                            'target_strength' => $test->target_strength,
                            'min_strength' => $test->min_strength,
                            'unit' => $test->unit ?: 'MPa',
                            'specimens_count' => count($specimens),
                            'avg_strength' => round($avgStrength, 2),
                            'has_outlier' => $hasOutlier,
                            'standard_reference' => $test->testType?->standard_reference ?: 'IS 516 / IS 456',
                        ],
                    ]);
                }

                $test->overall_status = $overallStatus;
                $test->evaluated_at = now();
                $test->save();

                // Save photos
                if ($request->hasFile('photos')) {
                    foreach ($request->file('photos') as $photo) {
                        $path = $photo->store('qc_tests', 'public');
                        Image::create([
                            'category' => 'QC_TEST',
                            'ref_no' => $test->id,
                            'image_path' => $path,
                            'image_name' => $photo->getClientOriginalName(),
                            'plant_id' => $test->plant_id,
                            'created_by' => auth()->id(),
                        ]);
                    }
                }

                // Update sample status
                $pendingRemaining = QcTest::where('sample_id', $test->sample_id)
                    ->where('id', '!=', $test->id)
                    ->where('overall_status', 'pending')
                    ->count();

                $test->sample->status = $pendingRemaining === 0 ? 'completed' : 'in_progress';
                $test->sample->save();

                return redirect()->route('quality.tests.index', ['status' => 'all'])->with('success', "Concrete Test {$test->test_no} executed: Avg {$avgStrength} MPa (" . strtoupper($overallStatus) . ")");
            }
            $test->remarks = $validated['remarks'] ?? null;
            $test->tested_by = auth()->id() ?: 1;
            $test->overall_status = 'pending';

            // Delete old measurements and results for recalculation
            QcTestMeasurement::where('qc_test_id', $test->id)->delete();
            QcTestResult::where('qc_test_id', $test->id)->delete();

            $parameters = QcTestParameter::where('test_type_id', $test->test_type_id)
                ->orderBy('display_order')
                ->get()
                ->keyBy('id');

            // Group measurements by row_index (trials)
            $rowsGrouped = [];
            foreach ($validated['measurements'] as $m) {
                $rowIndex = isset($m['row_index']) ? (int)$m['row_index'] : 0;
                $rowsGrouped[$rowIndex][] = $m;
            }
            if (empty($rowsGrouped)) {
                $rowsGrouped[0] = $validated['measurements'];
            }

            $parameterValuesMap = []; // Stores array of numeric values per parameter across trials

            // Process each specimen trial (row_index)
            foreach ($rowsGrouped as $rowIndex => $rowMeasurements) {
                $trialVariables = [];

                // Save raw input measurements for this trial
                foreach ($rowMeasurements as $m) {
                    $paramId = $m['parameter_id'];
                    $param = $parameters->get($paramId);
                    if (!$param) continue;

                    $numVal = $m['value_numeric'] !== null && $m['value_numeric'] !== '' ? (float) $m['value_numeric'] : null;
                    $txtVal = $m['value_text'] ?? null;

                    QcTestMeasurement::create([
                        'qc_test_id' => $test->id,
                        'parameter_id' => $paramId,
                        'value_numeric' => $numVal,
                        'value_text' => $txtVal,
                        'is_calculated' => false,
                        'row_index' => $rowIndex,
                    ]);

                    if ($param->code && $numVal !== null) {
                        $trialVariables[$param->code] = $numVal;
                        $parameterValuesMap[$param->id][] = $numVal;
                    }
                }

                // Calculate formulas for this trial
                foreach ($parameters as $param) {
                    if ($param->is_calculated && !empty($param->formula)) {
                        $calcVal = $this->formulaEngine->evaluateFormula($param->formula, $trialVariables);

                        QcTestMeasurement::create([
                            'qc_test_id' => $test->id,
                            'parameter_id' => $param->id,
                            'value_numeric' => $calcVal,
                            'value_text' => $calcVal !== null ? (string) round($calcVal, 4) : null,
                            'is_calculated' => true,
                            'row_index' => $rowIndex,
                        ]);

                        if ($param->code && $calcVal !== null) {
                            $trialVariables[$param->code] = $calcVal;
                            $parameterValuesMap[$param->id][] = $calcVal;
                        }
                    }
                }
            }

            // 3. Evaluate Pass / Fail against Acceptance Criteria
            $hasFailure = false;
            $hasEvaluated = false;

            foreach ($parameters as $param) {
                $vals = $parameterValuesMap[$param->id] ?? [];
                $numVal = !empty($vals) ? array_sum($vals) / count($vals) : null;

                $status = 'NONE';
                $snapshot = null;

                if ($param->hasAcceptanceRule()) {
                    $status = $this->formulaEngine->evaluateRule($param, $numVal);
                    $snapshot = [
                        'rule_type' => $param->rule_type,
                        'min_value' => $param->min_value,
                        'max_value' => $param->max_value,
                        'target_value' => $param->target_value,
                        'tolerance' => $param->tolerance,
                        'unit' => $param->unit,
                        'standard_reference' => $param->standard_reference,
                    ];
                    $hasEvaluated = true;
                    if ($status === 'FAIL') {
                        $hasFailure = true;
                    }
                }

                QcTestResult::create([
                    'qc_test_id' => $test->id,
                    'parameter_id' => $param->id,
                    'final_value' => $numVal,
                    'final_text' => $numVal !== null ? (string) round($numVal, 4) : null,
                    'status' => $status,
                    'criteria_snapshot' => $snapshot,
                ]);
            }

            $test->overall_status = $hasFailure ? 'fail' : ($hasEvaluated ? 'pass' : 'pass');
            $test->evaluated_at = now();
            $test->save();

            // Save uploaded photos
            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $photo) {
                    $path = $photo->store('qc_tests', 'public');
                    Image::create([
                        'category' => 'QC_TEST',
                        'ref_no' => $test->id,
                        'image_path' => $path,
                        'image_name' => $photo->getClientOriginalName(),
                        'plant_id' => $test->plant_id,
                        'created_by' => auth()->id(),
                    ]);
                }
            }

            // Update sample status
            $test->sample->status = 'completed';
            $test->sample->save();

            return redirect()->route('quality.tests.completed')->with('success', "Test {$test->test_no} executed and evaluated as " . strtoupper($test->overall_status));
        });
    }

    public function approve(Request $request, QcTest $test)
    {
        $test->approval_status = 'approved';
        $test->reviewed_by = auth()->id();
        $test->reviewed_at = now();
        $test->save();

        return redirect()->back()->with('success', "Test {$test->test_no} approved.");
    }

    public function markRetest(Request $request, QcTest $test)
    {
        $validated = $request->validate([
            'retest_reason' => 'required|string|max:500',
        ]);

        $test->overall_status = 'retest';
        $test->retest_reason = $validated['retest_reason'];
        $test->save();

        return redirect()->back()->with('success', "Test {$test->test_no} flagged for retest.");
    }
}
