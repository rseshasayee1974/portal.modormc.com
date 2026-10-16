<?php

namespace App\Http\Controllers\QC;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\QC\QcTest;
use App\Models\QC\QcSample;
use App\Models\QC\QcTestType;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
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
    use AuthorizesModule;
    protected string $module = 'QC_TEST';

    protected FormulaEngine $formulaEngine;

    public function __construct(FormulaEngine $formulaEngine)
    {
        $this->formulaEngine = $formulaEngine;
    }

    public function index(Request $request)
    {
        $this->authorizeModule('menu');

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
        $this->authorizeModule('menu');
        return redirect()->route('quality.tests.index', ['status' => 'pending']);
    }

    public function completed(Request $request)
    {
        $this->authorizeModule('menu');
        return redirect()->route('quality.tests.index', ['status' => 'pass']);
    }

    public function failed(Request $request)
    {
        $this->authorizeModule('menu');
        return redirect()->route('quality.tests.index', ['status' => 'fail']);
    }

    public function store(Request $request)
    {
        $this->authorizeModule('create');
        $plantId = app(PlantContextService::class)->requirePlantId();
        $data = $request->validate([
            'sample_id' => ['required', 'integer', Rule::exists('mm_qc_samples', 'id')->where('plant_id', $plantId)->whereNull('deleted_at')],
            'test_type_id' => ['required', 'integer', Rule::exists('mm_qc_test_types', 'id')->where('is_active', true)->whereNull('deleted_at')
                ->where(fn ($q) => $q->where('plant_id', $plantId)->orWhereNull('plant_id'))],
            'age_days' => ['required_without:schedule_milestones', 'prohibits:schedule_milestones', 'integer', Rule::in([7, 15, 28])],
            'schedule_milestones' => 'required_without:age_days|array|min:1|max:3',
            'schedule_milestones.*' => ['required', 'integer', 'distinct', Rule::in([7, 15, 28])],
            'remarks' => 'nullable|string|max:10000',
        ]);

        return DB::transaction(function () use ($data, $plantId) {
            $sample = QcSample::where('plant_id', $plantId)->lockForUpdate()->findOrFail($data['sample_id']);
            if ((int) $sample->batch?->status === 5 || $sample->dispatch?->dispatch_status === 'Cancelled') {
                throw ValidationException::withMessages(['sample_id' => 'Cancelled production cannot be scheduled for testing.']);
            }
            $ages = $data['schedule_milestones'] ?? [(int) $data['age_days']];
            sort($ages);
            if ($sample->tests()->where('test_type_id', $data['test_type_id'])->whereIn('age_days', $ages)->exists()) {
                throw ValidationException::withMessages([isset($data['schedule_milestones']) ? 'schedule_milestones' : 'age_days' => 'A test already exists at one of the selected ages. Select only new ages or open the existing test.']);
            }
            $type = QcTestType::with('parameters')->findOrFail($data['test_type_id']);
            $firstTest = null;
            foreach ($ages as $age) {
                $parameter = $type->parameters->first(fn ($p) => (is_numeric($p->default_value) && (int) $p->default_value === $age)
                    || preg_match('/\b'.$age.'[\s-]*(days?|d)\b/i', $p->name.' '.$p->default_value));
                $fck = preg_match('/(\d+)/', $sample->concreteGrade?->name ?? '', $matches) ? (float) $matches[1] : 25.0;
                $target = $age <= 7 ? 0.70 : ($age <= 15 ? 0.90 : 1.00);
                $minimum = $age <= 7 ? 0.65 : ($age <= 15 ? 0.85 : 0.95);
                $test = $sample->tests()->create([
                    'plant_id' => $plantId, 'test_type_id' => $type->id,
                    'test_no' => 'TST-'.Str::ulid().'-'.$age.'D',
                    'scheduled_date' => $sample->scheduledTestDate($age),
                    'age_days' => $age, 'test_date' => null, 'tested_by' => null,
                    'target_strength' => $parameter?->target_value ?? round($fck * $target, 2),
                    'min_strength' => $parameter?->min_value ?? round($fck * $minimum, 2),
                    'unit' => $parameter?->unit ?: 'MPa', 'overall_status' => 'pending',
                    'approval_status' => 'draft', 'remarks' => $data['remarks'] ?? null, 'created_by' => auth()->id(),
                ]);
                $firstTest ??= $test;
            }
            $sample->update(['status' => 'pending_test']);
            return redirect()->route('quality.tests.execute', $firstTest);
        });
    }

    private function authorizeTest(QcTest $test, string $action): void
    {
        $this->authorizeModule($action);
        $plantId = app(PlantContextService::class)->requirePlantId();
        abort_unless((int) $test->plant_id === $plantId && $test->sample()->where('plant_id', $plantId)->exists(), 404);
    }

    public function executeForm(Request $request, QcTest $test)
    {
        $this->authorizeTest($test, 'menu');
        $test->load([
            'plant',
            'sample.material',
            'sample.supplier',
            'sample.customer',
            'sample.concreteGrade',
            'sample.dispatch.truck',
            'sample.dispatch.salesOrder.customer',
            'config.standard',
            'config.milestones',
            'testType.parameters.unitRef',
            'sets.specimens',
            'measurements',
            'results.parameter',
            'photos',
            'tester',
            'reviewer'
        ]);

        $cubeCalculator = app(\App\Services\QC\ConcreteCubeCalculator::class);
        $isConcrete = $cubeCalculator->supports($test);

        if ($isConcrete && $test->sets->isEmpty()) {
            $test->ensureSetsAndSpecimensInitialized();
            $test->load('sets.specimens');
        }

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
            'readOnly' => $request->boolean('view') || $test->approval_status === 'approved',
            'test' => $test,
            'rules' => $mergedRules,
            'isConcreteCubeTest' => $isConcrete,
            'cubeDimensions' => $isConcrete ? $cubeCalculator->dimensions($test) : null,
        ]);
    }

    public function destroy(QcTest $test)
    {
        $this->authorizeTest($test, 'delete');
        abort_if($test->approval_status === 'approved', 422, 'Approved tests cannot be deleted.');

        DB::transaction(function () use ($test) {
            $sample = $test->sample;
            $test->delete();
            $sample->update(['status' => $sample->tests()->whereIn('overall_status', ['pending', 'retest'])->exists()
                ? 'pending_test' : ($sample->tests()->exists() ? 'completed' : 'pending_test')]);
        });

        return redirect()->route('quality.samples.index')->with('success', 'Test deleted. The sample has been retained.');
    }

    public function submitExecution(Request $request, QcTest $test)
    {
        $this->authorizeTest($test, 'edit');
        abort_if($test->approval_status === 'approved', 422, 'Approved tests cannot be changed.');
        if ($request->filled('concrete_specimens') || $request->filled('sets')) {
            abort_unless(app(\App\Services\QC\ConcreteCubeCalculator::class)->supports($test), 422, 'Use the configured execution form for this test type.');
        }
        if ($request->has('sets') && !empty($request->sets)) {
            $validated = $request->validate([
                'test_date' => 'nullable|date',
                'remarks' => 'nullable|string',
                'sets' => 'required|array',
                'sets.*.id' => ['required', 'distinct', Rule::exists('mm_qc_test_sets', 'id')->where('qc_test_id', $test->id)->whereNull('deleted_at')],
                'sets.*.casting_date' => 'nullable|date',
                'sets.*.place_of_casting' => 'nullable|string|max:150',
                'sets.*.testing_date' => 'nullable|date',
                'sets.*.age_days' => 'nullable|integer',
                'sets.*.client_sign_name' => 'nullable|string|max:150',
                'sets.*.qc_sign_name' => 'nullable|string|max:150',
                'sets.*.specimens' => 'nullable|array',
                'sets.*.specimens.*.id' => 'required|exists:mm_qc_test_specimens,id',
                'sets.*.specimens.*.identification_mark' => 'nullable|string|max:100',
                'sets.*.specimens.*.weight_kg' => 'nullable|numeric|gt:0',
                'sets.*.specimens.*.load_kn' => 'nullable|numeric|gt:0',
                'sets.*.specimens.*.failure_type' => 'nullable|string|max:50',
                'photos.*' => 'nullable|image|max:10240',
            ]);

            app(\App\Services\QC\QcSetExecutionService::class)->processExecution($test, $validated);

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

            if ($test->sample) {
                $test->sample->status = $pendingRemaining === 0 && $test->overall_status !== 'pending' ? 'completed' : 'in_progress';
                $test->sample->save();
            }

            return redirect()->route('quality.tests.index', ['status' => 'all'])->with('success', "Test {$test->test_no} executed and evaluated successfully.");
        }

        if ($request->has('overall_status')) {
            $rawStatus = strtolower(trim((string) $request->input('overall_status')));
            $normalizedStatus = match ($rawStatus) {
                'pass', 'passed', 'pass/compliant' => 'pass',
                'fail', 'failed', 'non-compliant' => 'fail',
                'hold' => 'hold',
                'retest' => 'retest',
                default => 'pending',
            };
            $request->merge(['overall_status' => $normalizedStatus]);
        }

        $validated = $request->validate([
            'test_date' => 'required|date',
            'measurements' => 'nullable|array',
            'measurements.*.parameter_id' => 'nullable|exists:mm_qc_test_parameters,id',
            'measurements.*.value_numeric' => 'nullable|numeric',
            'measurements.*.row_index' => 'nullable|integer',
            'measurements.*.value_text' => 'nullable|string|max:255',
            'remarks' => 'nullable|string',
            'photos.*' => 'nullable|image|max:10240',
            'concrete_specimens' => 'nullable|array|min:3|max:30',
            'concrete_specimens.*.ident_mark' => 'nullable|string|max:100',
            'concrete_specimens.*.weight_kg' => 'required_with:concrete_specimens|numeric|gt:0',
            'concrete_specimens.*.load_kn' => 'required_with:concrete_specimens|numeric|gt:0',
            'concrete_specimens.*.failure_type' => 'nullable|string|max:50',
            'avg_strength' => 'nullable|numeric',
            'overall_status' => 'nullable|string|in:pass,fail,pending,hold,retest,Pass,Fail,Pending,Hold,Retest,PASS,FAIL,PENDING,HOLD,RETEST',
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
                $calculator = app(\App\Services\QC\ConcreteCubeCalculator::class);
                $dimensions = $calculator->dimensions($test);
                $calculator->snapshot($test, $dimensions);
                $castingDate = $test->sample->sample_date->copy()->startOfDay();
                $testingDate = \Carbon\Carbon::parse($validated['test_date'])->startOfDay();
                if ($testingDate->lt($castingDate)) {
                    throw ValidationException::withMessages(['test_date' => 'Testing date cannot be before casting date.']);
                }
                
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
                    $calculated = $calculator->observation($spec, $dimensions);
                    $strength = $calculated['strength_mpa'];
                    if ($strength !== null) {
                        $strengthValues[] = $strength;
                    }
                    
                    $meta = [
                        'ident_mark' => $spec['ident_mark'] ?? ('Specimen #' . ($idx + 1)),
                        'weight_kg' => isset($spec['weight_kg']) && $spec['weight_kg'] !== '' ? (float)$spec['weight_kg'] : null,
                        'load_kn' => isset($spec['load_kn']) && $spec['load_kn'] !== '' ? (float)$spec['load_kn'] : null,
                        'density' => $calculated['density_kg_m3'],
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
                    $isPass = ($avgStrength >= (float)$test->target_strength);
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

                $overallStatus = $hasOutlier ? 'retest' : ($isPass ? 'pass' : 'fail');
                $status = $hasOutlier ? 'NONE' : ($isPass ? 'PASS' : 'FAIL');

                $set = $test->sets()->firstOrCreate(['set_number' => 1], ['status' => 'pending']);
                $set->fill([
                    'casting_date' => $castingDate, 'testing_date' => $testingDate,
                    'place_of_casting' => $test->sample->source_location,
                    'age_days' => (int) $castingDate->diffInDays($testingDate),
                    'average_strength' => round($avgStrength, 2), 'status' => $overallStatus,
                    'target_strength' => $test->target_strength, 'min_strength' => $test->min_strength,
                ])->save();
                foreach ($specimens as $idx => $spec) {
                    $set->specimens()->updateOrCreate(['set_specimen_index' => $idx + 1], [
                        ...$calculator->observation($spec, $dimensions),
                        'qc_test_id' => $test->id, 'specimen_index' => $idx + 1,
                        'identification_mark' => $spec['ident_mark'] ?? (string) ($idx + 1),
                        'failure_type' => $spec['failure_type'] ?? 'Normal', 'status' => 'completed',
                    ]);
                }
                $set->specimens()->where('set_specimen_index', '>', count($specimens))->delete();

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

            // Pre-populate shared test context variables (e.g. standard specimen area, dimensions)
            $sharedContext = [];
            if ($test->testType) {
                if ($test->testType->specimen_dimensions) {
                    $sharedContext['SPECIMEN_DIMENSIONS'] = $test->testType->specimen_dimensions;
                }
            }

            // Extract test-level variables from inputs
            foreach ($validated['measurements'] as $m) {
                $p = $parameters->get($m['parameter_id'] ?? null);
                if ($p && $p->code && ($p->scope === 'test' || ($m['row_index'] ?? 0) === 0)) {
                    if (isset($m['value_numeric']) && $m['value_numeric'] !== null && $m['value_numeric'] !== '') {
                        $sharedContext[$p->code] = (float)$m['value_numeric'];
                    } elseif (!empty($m['value_text'])) {
                        $sharedContext[$p->code] = $m['value_text'];
                    }
                }
            }

            // Process each specimen trial (row_index)
            foreach ($rowsGrouped as $rowIndex => $rowMeasurements) {
                $trialVariables = $sharedContext;

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

                    if ($param->code) {
                        if ($numVal !== null) {
                            $trialVariables[$param->code] = $numVal;
                            $parameterValuesMap[$param->id][] = $numVal;
                        } elseif ($txtVal !== null && $txtVal !== '') {
                            $trialVariables[$param->code] = $txtVal;
                        }
                    }
                }

                // Calculate formulas for this trial (specimen-level & test-level)
                foreach ($parameters as $param) {
                    if ($param->is_calculated && !empty($param->formula) && $param->scope !== 'summary') {
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

            // 3. Evaluate Summary Parameters (e.g. Average Strength across specimens)
            $summaryVariables = $sharedContext;
            foreach ($parameters as $param) {
                if ($param->code && !empty($parameterValuesMap[$param->id])) {
                    $vals = $parameterValuesMap[$param->id];
                    $summaryVariables[$param->code] = array_sum($vals) / count($vals);
                    // Also expose as array for AVG/SUM functions if needed
                    $summaryVariables['ALL_' . $param->code] = implode(',', $vals);
                }
            }

            foreach ($parameters as $param) {
                if ($param->is_calculated && !empty($param->formula) && $param->scope === 'summary') {
                    // Replace AVG(TOKEN) with mean of tokens if present
                    $calcFormula = preg_replace_callback('/AVG\(([A-Za-z0-9_]+)\)/i', function($m) use ($parameterValuesMap, $parameters) {
                        $targetCode = strtoupper($m[1]);
                        $foundParam = $parameters->firstWhere('code', $targetCode);
                        if ($foundParam && !empty($parameterValuesMap[$foundParam->id])) {
                            return (string)(array_sum($parameterValuesMap[$foundParam->id]) / count($parameterValuesMap[$foundParam->id]));
                        }
                        return '0';
                    }, $param->formula);

                    $calcVal = $this->formulaEngine->evaluateFormula($calcFormula, $summaryVariables);
                    if ($calcVal !== null) {
                        $parameterValuesMap[$param->id] = [$calcVal];
                        QcTestMeasurement::create([
                            'qc_test_id' => $test->id,
                            'parameter_id' => $param->id,
                            'value_numeric' => $calcVal,
                            'value_text' => (string) round($calcVal, 4),
                            'is_calculated' => true,
                            'row_index' => 0,
                        ]);
                    }
                }
            }

            // 4. Evaluate Pass / Fail against Acceptance Criteria
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
        $this->authorizeTest($test, 'approve');
        abort_unless($test->evaluated_at && in_array($test->overall_status, ['pass', 'fail'], true), 422, 'Only evaluated tests can be approved.');
        $test->approval_status = 'approved';
        $test->reviewed_by = auth()->id();
        $test->reviewed_at = now();
        $test->save();

        return redirect()->back()->with('success', "Test {$test->test_no} approved.");
    }

    public function markRetest(Request $request, QcTest $test)
    {
        $this->authorizeTest($test, 'edit');
        abort_if($test->approval_status === 'approved', 422, 'Approved tests cannot be marked for retest.');
        $validated = $request->validate([
            'retest_reason' => 'required|string|max:500',
        ]);

        $test->overall_status = 'retest';
        $test->retest_reason = $validated['retest_reason'];
        $test->save();

        return redirect()->back()->with('success', "Test {$test->test_no} flagged for retest.");
    }
}
