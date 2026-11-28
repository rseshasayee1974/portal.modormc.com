<?php

namespace App\Http\Controllers\QC;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\QC\QcSample;
use App\Models\QC\QcTest;
use App\Models\QC\QcTestType;
use App\Models\ConcreteGrade;
use App\Models\Batch;
use App\Models\Dispatch;
use App\Models\Personnel;
use App\Models\SalesOrder;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use App\Services\PlantContextService;
use App\Services\QualitySampleDirectory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class QCSampleController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'QC_SAMPLE';

    public function index(Request $request)
    {
        $this->authorizeModule('menu');

        $ctx = app(PlantContextService::class);
        $plantId = $ctx->requirePlantId();
        $filters = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'search' => 'nullable|string|max:200',
            'age' => 'nullable|integer|between:1,36500',
            'status' => ['nullable', Rule::in(['All', 'not_sampled', 'pending', 'pass', 'fail', 'hold', 'retest'])],
        ]);
        return Inertia::render('Quality/Samples/Index', $this->samplingOptions($plantId, $filters));
    }

    public function create()
    {
        $this->authorizeModule('create');
        return redirect()->route('quality.samples.index');
    }

    public function sources(Request $request)
    {
        $this->authorizeModule('menu');
        $plantId = app(PlantContextService::class)->requirePlantId();
        $filters = $request->validate(['page' => 'sometimes|integer|min:1', 'search' => 'nullable|string|max:200']);
        $options = $this->samplingOptions($plantId, $filters, true);
        return response()->json(\Illuminate\Support\Arr::only($options, ['directory', 'salesOrders', 'additionalSamples']));
    }

    public function testSummary(QcSample $sample)
    {
        $this->authorizeModule('menu');
        abort_unless((int) $sample->plant_id === app(PlantContextService::class)->requirePlantId(), 404);
        return response()->json($sample->tests()->orderBy('id')->get([
            'id', 'test_type_id', 'test_no', 'age_days', 'overall_status', 'scheduled_date', 'test_date',
        ]));
    }

    public function edit(QcSample $sample)
    {
        $this->authorizeModule('edit');
        $ctx = app(PlantContextService::class);
        $plantId = $ctx->plantId();

        abort_if($plantId && (int) $sample->plant_id !== (int) $plantId, 404);
        $sample->load(['tests.testType', 'concreteGrade', 'material', 'customer', 'batch', 'dispatch', 'tester']);

        return Inertia::render('Quality/Samples/Edit', [
            ...$this->samplingOptions($plantId),
            'sample' => $sample,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeModule('create');

        $ctx = app(PlantContextService::class);
        $plantId = $ctx->requirePlantId();

        $validated = $request->validate([
            'execute_after_create' => 'sometimes|boolean',
            'sales_order_id' => 'nullable|integer',
            'source_keys' => 'required_with:sales_order_id|array|min:1',
            'source_keys.*' => ['required', 'string', 'distinct', 'regex:/^(batch|dispatch):[0-9]+$/'],
            'sample_date' => 'required|date',
            'tested_by' => ['nullable', 'integer', Rule::exists('mm_personnels', 'id')->whereNull('deleted_at')->where(fn ($q) => $q->where('plant_id', app(PlantContextService::class)->requirePlantId())->orWhereNull('plant_id'))],
            'concrete_grade_id' => 'nullable|exists:mm_concrete_grades,id',
            'material_id' => 'nullable|exists:mm_products,id',
            'dispatch_id' => ['nullable', 'integer', Rule::exists('mm_dispatches', 'id')->where('plant_id', app(PlantContextService::class)->requirePlantId())->whereNull('deleted_at')],
            'batch_id' => ['nullable', 'integer', Rule::exists('mm_batches', 'id')->where('plant_id', app(PlantContextService::class)->requirePlantId())->whereNull('deleted_at')],
            'customer_id' => ['nullable', 'integer', Rule::exists('mm_patrons', 'id')->where('plant_id', app(PlantContextService::class)->requirePlantId())->whereNull('deleted_at')],
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
            'schedule_milestones' => 'sometimes|required|array|min:1|max:100',
            'schedule_milestones.*' => ['required', 'integer', 'distinct', 'between:1,36500'],
            'test_type_ids' => 'nullable|array|max:100',
            'test_type_ids.*' => ['integer', 'distinct', Rule::exists('mm_qc_test_types', 'id')->where('is_active', true)->whereNull('deleted_at')
                ->where(fn ($q) => $q->where('plant_id', $plantId)->orWhereNull('plant_id'))],
            'remarks' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($validated, $plantId) {
            $sources = $this->resolveSources($validated, $plantId);
            $input = $validated;
            foreach ($sources as $source) {
                $validated = array_merge($input, $source);
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
                    'specimen_size' => $validated['specimen_size'] ?? '150x150x150 mm',
                    'specimen_count' => $validated['specimen_count'] ?? 6,
                    'curing_tank_id' => $validated['curing_tank_id'] ?? null,
                    'sample_quantity' => $validated['sample_quantity'] ?? null,
                    'sampled_by' => auth()->id() ?: 1,
                    'tested_by' => $validated['tested_by'] ?? null,
                    'status' => 'pending_test',
                    'remarks' => $validated['remarks'] ?? null,
                    'created_by' => auth()->id() ?: 1,
                ]);

                // Resolve target test types (prioritize Compressive Strength test type for cube crushing schedule)
                $targetTestTypeIds = [];
                if (!empty($validated['test_type_ids'])) {
                    $targetTestTypeIds = $validated['test_type_ids'];
                } elseif (!empty($validated['concrete_grade_id'])) {
                    $grade = ConcreteGrade::find($validated['concrete_grade_id']);
                    if ($grade) {
                        // Look for Compressive strength test type for this grade first
                        $targetTestTypeIds = QcTestType::where('is_active', true)
                            ->where(fn ($q) => $q->where('plant_id', $plantId)->orWhereNull('plant_id'))
                            ->where(function ($q) use ($grade) {
                                $q->where('material_type', $grade->name)
                                  ->orWhere('material_type', (string)$grade->id);
                            })
                            ->where(function ($q) {
                                $q->where('name', 'like', '%Compressive%')
                                  ->orWhere('code', 'like', '%COMPRESS%');
                            })
                            ->take(1)
                            ->pluck('id')
                            ->toArray();

                        // If no specific compressive test type exists for this grade, take the first active matching test type
                        if (empty($targetTestTypeIds)) {
                            $targetTestTypeIds = QcTestType::where('is_active', true)
                            ->where(fn ($q) => $q->where('plant_id', $plantId)->orWhereNull('plant_id'))
                                ->where(function ($q) use ($grade) {
                                    $q->where('material_type', $grade->name)
                                      ->orWhere('material_type', (string)$grade->id);
                                })
                                ->take(1)
                                ->pluck('id')
                                ->toArray();
                        }
                    }
                }

                if (empty($targetTestTypeIds)) {
                    // Fallback to any active Concrete Compressive strength test type
                    $targetTestTypeIds = QcTestType::where('is_active', true)
                            ->where(fn ($q) => $q->where('plant_id', $plantId)->orWhereNull('plant_id'))
                        ->where(function ($q) {
                            $q->where('category', 'Concrete')
                              ->orWhere('name', 'like', '%Compressive%');
                        })
                        ->take(1)
                        ->pluck('id')
                        ->toArray();
                }

                if (empty($targetTestTypeIds)) {
                    throw ValidationException::withMessages(['test_type_ids' => 'Select an active test type for the current plant.']);
                }
                $this->authorizeModule('create', 'QC_TEST');

                // Determine characteristic grade strength (fck) if available
                $fck = 25.0;
                if (!empty($validated['concrete_grade_id'])) {
                    $grade = ConcreteGrade::find($validated['concrete_grade_id']);
                    if ($grade && preg_match('/(\d+)/', $grade->name, $gm)) {
                        $fck = (float)$gm[1];
                    }
                }

                // Determine crushing milestones (2-test: [7, 28], 3-test: [7, 15, 28], or custom)
                $chosenMilestones = !empty($validated['schedule_milestones'])
                    ? array_values(array_unique(array_map('intval', $validated['schedule_milestones'])))
                    : null;

                if ($chosenMilestones) {
                    sort($chosenMilestones);
                }

                // Create scheduled test events
                foreach ($targetTestTypeIds as $testTypeId) {
                    $testType = QcTestType::with('parameters')->find($testTypeId);
                    if (!$testType) continue;
                    $configuredAges = $testType->parameters->map(fn ($parameter) => $parameter->testAgeDays())->filter()->unique()->values()->all();
                    if (!empty($chosenMilestones) && !empty($configuredAges) && array_diff($chosenMilestones, $configuredAges)) {
                        throw ValidationException::withMessages(['schedule_milestones' => 'Select ages configured in the selected test type parameters.']);
                    }

                    if (!empty($chosenMilestones)) {
                        // Create scheduled test for each chosen milestone (7d, 15d, 28d)
                        foreach ($chosenMilestones as $age) {
                            $scheduledDate = $sample->scheduledTestDate($age);
                            $testNo = 'TST-' . $sample->id . '-' . $testType->id . "-{$age}D";

                            // Standard IS 456 strength targets
                            if ($age <= 7) {
                                $targetVal = round($fck * 0.70, 2);
                                $minVal = round($fck * 0.65, 2);
                            } elseif ($age <= 14) {
                                $targetVal = round($fck * 0.90, 2);
                                $minVal = round($fck * 0.85, 2);
                            } else {
                                $targetVal = round($fck * 1.00, 2);
                                $minVal = round($fck * 0.95, 2);
                            }

                            // Check if a matching parameter exists in test type config
                            $matchingParam = $testType->parameters->first(function ($p) use ($age) {
                                return $p->testAgeDays() === $age;
                            });

                            QcTest::create([
                                'plant_id' => $plantId ?? 1,
                                'sample_id' => $sample->id,
                                'test_type_id' => $testType->id,
                                'test_no' => $testNo,
                                'test_date' => null,
                                'tested_by' => null,
                                'scheduled_date' => $scheduledDate,
                                'age_days' => $age,
                                'target_strength' => $matchingParam?->target_value ?? $targetVal,
                                'min_strength' => $matchingParam?->min_value ?? $minVal,
                                'unit' => $matchingParam?->unit ?: 'MPa',
                                'overall_status' => 'pending',
                                'approval_status' => 'draft',
                                'created_by' => auth()->id() ?: 1,
                            ]);
                        }
                    } else {
                        $ageParams = $testType->parameters->mapWithKeys(function ($parameter) {
                            $age = $parameter->testAgeDays();
                            return $age !== null ? [$age => $parameter] : [];
                        });

                        if ($ageParams->isNotEmpty()) {
                            foreach ($ageParams as $age => $param) {
                                $scheduledDate = $sample->scheduledTestDate($age);
                                $testNo = 'TST-' . $sample->id . '-' . $testType->id . "-{$age}D";

                                QcTest::create([
                                    'plant_id' => $plantId ?? 1,
                                    'sample_id' => $sample->id,
                                    'test_type_id' => $testType->id,
                                    'test_no' => $testNo,
                                    'test_date' => null,
                                    'tested_by' => null,
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
                            $testNo = 'TST-' . $sample->id . '-' . $testType->id . '-28D';
                            QcTest::create([
                                'plant_id' => $plantId ?? 1,
                                'sample_id' => $sample->id,
                                'test_type_id' => $testType->id,
                                'test_no' => $testNo,
                                'test_date' => null,
                                'tested_by' => null,
                                'scheduled_date' => $sample->scheduledTestDate(28),
                                'age_days' => 28,
                                'unit' => 'MPa',
                                'overall_status' => 'pending',
                                'approval_status' => 'draft',
                                'created_by' => auth()->id() ?: 1,
                            ]);
                        }
                    }
                }

            }
            if ($input['execute_after_create'] ?? false) {
                $test = $sample->tests()->orderBy('age_days')->first();
                if (!$test) {
                    throw ValidationException::withMessages(['test_type_ids' => 'Select an active test type to create and execute a test.']);
                }
                return redirect()->route('quality.tests.execute', $test);
            }
            return redirect()->route('quality.samples.index')->with('success', count($sources) . ' concrete sample(s) logged and testing schedules created.');
        });
    }

    public function update(Request $request, QcSample $sample)
    {
        $this->authorizeModule('edit');

        $validated = $request->validate([
            'sales_order_id' => 'nullable|integer',
            'source_keys' => 'required_with:sales_order_id|array|min:1',
            'source_keys.*' => ['required', 'string', 'distinct', 'regex:/^(batch|dispatch):[0-9]+$/'],
            'schedule_milestones' => 'sometimes|required|array|min:1|max:100',
            'schedule_milestones.*' => ['required', 'integer', 'distinct', 'between:1,36500'],
            'sample_date' => 'required|date',
            'tested_by' => ['nullable', 'integer', Rule::exists('mm_personnels', 'id')->whereNull('deleted_at')->where(fn ($q) => $q->where('plant_id', app(PlantContextService::class)->requirePlantId())->orWhereNull('plant_id'))],
            'concrete_grade_id' => 'nullable|exists:mm_concrete_grades,id',
            'material_id' => 'nullable|exists:mm_products,id',
            'dispatch_id' => ['nullable', 'integer', Rule::exists('mm_dispatches', 'id')->where('plant_id', app(PlantContextService::class)->requirePlantId())->whereNull('deleted_at')],
            'batch_id' => ['nullable', 'integer', Rule::exists('mm_batches', 'id')->where('plant_id', app(PlantContextService::class)->requirePlantId())->whereNull('deleted_at')],
            'customer_id' => ['nullable', 'integer', Rule::exists('mm_patrons', 'id')->where('plant_id', app(PlantContextService::class)->requirePlantId())->whereNull('deleted_at')],
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

        $plantId = app(PlantContextService::class)->plantId();
        abort_if($plantId && (int) $sample->plant_id !== (int) $plantId, 404);
        DB::transaction(function () use ($validated, $sample) {
            if (isset($validated['schedule_milestones'])) {
                $requested = collect($validated['schedule_milestones'])->map(fn ($age) => (int) $age)->sort()->values()->all();
                $existing = $sample->tests()->pluck('age_days')->unique()->sort()->values()->all();
                if ($requested !== $existing) {
                    throw ValidationException::withMessages(['schedule_milestones' => 'Schedule additional ages using New test for this sample. Existing milestones cannot be replaced by a metadata edit.']);
                }
                unset($validated['schedule_milestones']);
            }
            $sources = $this->resolveSources($validated, $sample->plant_id, $sample);
            if (count($sources) !== 1) {
                throw ValidationException::withMessages(['source_keys' => 'Select one production row when editing a sample.']);
            }
            $castingChanged = \Carbon\Carbon::parse($validated['sample_date'])->toDateString() !== $sample->sample_date->toDateString();
            unset($validated['sales_order_id'], $validated['source_keys']);
            $sample->update(array_merge($validated, $sources[0], ['updated_by' => auth()->id()]));
            if ($castingChanged) {
                foreach ($sample->tests()->whereNotNull('age_days')->get() as $test) {
                    $date = $sample->scheduledTestDate((int) $test->age_days);
                    $test->update(['scheduled_date' => $date]);
                    $test->sets()->update(['casting_date' => $sample->sample_date->toDateString(), 'scheduled_testing_date' => $date]);
                }
            }

        });

        return redirect()->route('quality.samples.index')->with('success', 'Concrete Sample details updated.');
    }

    private function samplingOptions(?int $plantId, ?array $filters = null, bool $sourcePicker = false): array
    {
        $testTypes = QcTestType::with('parameters')->where('is_active', true)
            ->when($plantId, fn ($q) => $q->where(fn ($scope) => $scope->where('plant_id', $plantId)->orWhereNull('plant_id')))
            ->get(['id', 'name', 'code', 'category', 'material_type']);
        $supportedTestAges = [7, 15, 28];
        foreach ($testTypes->flatMap->parameters as $parameter) {
            if ((string) $parameter->is_active === '0' || $parameter->is_active === false) continue;
            $age = null;
            foreach ([$parameter->name, $parameter->code, $parameter->default_value] as $value) {
                if (preg_match('/(?:^|\D)(\d+)\s*[-_]?\s*(?:days?|d)\b/i', (string) $value, $match)) {
                    $age = (int) $match[1];
                    break;
                }
            }
            if ($age === null && preg_match('/\bage\b|days?|^d$/i', "$parameter->name $parameter->code $parameter->unit")) {
                $age = filter_var($parameter->default_value, FILTER_VALIDATE_INT);
            }
            if ($age > 0 && $age <= 36500) $supportedTestAges[] = $age;
        }
        $supportedTestAges = array_values(array_unique($supportedTestAges));
        $directory = $filters === null ? null : app(QualitySampleDirectory::class)->page(
            $plantId, $filters, $supportedTestAges, request()->attributes->get('entity_timezone', 'Asia/Kolkata'), $sourcePicker
        );
        $entries = $directory['entries'] ?? null;
        $dispatched = fn ($q) => $q->when($plantId, fn ($q) => $q->where('plant_id', $plantId))
            ->whereNotNull('dispatch_time')->whereNotNull('dispatch_no')
            ->whereNotIn('dispatch_status', ['Draft', 'Loading', 'Cancelled']);
        // Avoid computing pricing, usage and tax attributes when only display labels are needed.
        $labels = static fn (string $columns) => static fn ($query) => $query
            ->select(explode(',', $columns))->afterQuery(fn ($models) => $models->withoutAppends());

        $salesOrders = SalesOrder::with([
            'customer' => $labels('id,legal_name'),
            'site' => $labels('id,name'),
            'mixDesign' => $labels('id,concrete_grade_id'),
            'mixDesign.concreteGrade:id,name',
            'dispatches' => function ($query) use ($entries, $labels) {
                $labels('id,sales_order_id,batch_id,dispatch_no,dispatch_time,dispatch_status,truck_id,unload_site_id,mixdesign_id')($query);
                if ($entries !== null) $query->whereKey($entries->pluck('dispatch_id')->filter()->unique()->all());
            },
            'dispatches.batch' => $labels('id,batch_no,status'),
            'dispatches.unloadSite' => $labels('id,name'),
            'dispatches.truck' => $labels('id,registration'),
            'dispatches.mixDesign' => $labels('id,concrete_grade_id'),
            'dispatches.mixDesign.concreteGrade:id,name',
            'dispatches.invoiceStatus:id,dispatch_id,invoice_id,invoice_number',
            'dispatches.invoiceStatus.invoice' => $labels('id,invoice_number'),
        ])->when($plantId, fn ($q) => $q->where('plant_id', $plantId))
            ->when($entries !== null, fn ($q) => $q->whereKey($entries->pluck('order_id')->filter()->unique()->all()),
                fn ($q) => $q->whereHas('dispatches', $dispatched))
            ->latest()->get(['id', 'order_no', 'customer_id', 'site_id', 'mix_design_id'])->withoutAppends();
        $sourceSamples = QcSample::with([
            'tests' => fn ($q) => $q->select(['id', 'sample_id', 'test_type_id', 'test_no', 'age_days',
                'overall_status', 'scheduled_date', 'test_date', 'evaluated_at', 'tested_by'])
                ->when($entries !== null, fn ($q) => $q->whereKey($entries->pluck('test_id')->filter()->unique()->all())),
            'tests.tester:id,username', 'tester:id,first_name,last_name,employee_code',
            'concreteGrade:id,name', 'material' => $labels('id,title'),
            'customer' => $labels('id,legal_name'), 'batch' => $labels('id,batch_no,status'),
            'dispatch' => $labels('id,dispatch_no,dispatch_time,dispatch_status'),
        ])->when($plantId, fn ($q) => $q->where('plant_id', $plantId))
            ->when($entries !== null, fn ($q) => $q->whereKey($entries->pluck('sample_id')->filter()->unique()->all()))->get();
        $samplesByBatch = $sourceSamples->whereNotNull('batch_id')->keyBy('batch_id');
        $samplesByDispatch = $sourceSamples->whereNotNull('dispatch_id')->keyBy('dispatch_id');
        $pageSamples = $entries?->pluck('sample_id', 'dispatch_id');
        $samplesById = $sourceSamples->keyBy('id');
        $representedSampleIds = [];
        $timezone = request()->attributes->get('entity_timezone', 'Asia/Kolkata');
        $today = now($timezone)->startOfDay();
        foreach ($salesOrders as $order) {
            foreach ($order->dispatches as $dispatch) {
                $dispatchAge = $dispatch->dispatch_time
                    ? (int) \Carbon\Carbon::parse($dispatch->dispatch_time)->timezone($timezone)->startOfDay()
                        ->diffInDays($today, false)
                    : null;
                $dispatch->setAttribute('qc_due_age', in_array($dispatchAge, [7, 15, 28], true) ? $dispatchAge : null);
                $sample = $entries === null
                    ? ($samplesByDispatch->get($dispatch->id) ?? $samplesByBatch->get($dispatch->batch_id))
                    : $samplesById->get($pageSamples->get($dispatch->id));
                $dispatch->setRelation('qualitySample', $sample);
                if ($sample && $dispatch->dispatch_time && $dispatch->dispatch_no
                    && !in_array($dispatch->dispatch_status, ['Draft', 'Loading', 'Cancelled'], true)) {
                    $representedSampleIds[$sample->id] = true;
                }
            }
        }
        $additionalSamples = $sourceSamples->reject(fn ($sample) => isset($representedSampleIds[$sample->id]))->values();
        if ($sourcePicker) return compact('directory', 'salesOrders', 'additionalSamples');
        $personnels = Personnel::whereNull('deleted_at')
            ->when($plantId, fn($q) => $q->where(fn($sq) => $sq->where('plant_id', $plantId)->orWhereNull('plant_id')))
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'employee_code'])
            ->map(fn($p) => [
                'id' => $p->id,
                'first_name' => $p->first_name,
                'last_name' => $p->last_name,
                'employee_code' => $p->employee_code,
                'label' => trim($p->first_name . ' ' . $p->last_name) . ($p->employee_code ? " ({$p->employee_code})" : ''),
                'value' => $p->id,
            ]);

        $concreteGrades = ConcreteGrade::where('status', true)->get(['id', 'name']);
        return compact('supportedTestAges', 'salesOrders', 'personnels', 'additionalSamples', 'concreteGrades', 'testTypes', 'directory', 'filters');
    }

    private function resolveSources(array $input, int $plantId, ?QcSample $sample = null): array
    {
        // Keep material samples and existing API clients compatible.
        if (empty($input['sales_order_id'])) {
            if (array_key_exists('sales_order_id', $input)) {
                throw ValidationException::withMessages(['sales_order_id' => 'Select a sales order.']);
            }
            if (!empty($input['source_keys'])) {
                throw ValidationException::withMessages(['sales_order_id' => 'A sales order is required when selecting production rows.']);
            }
            if (!empty($input['dispatch_id'])) {
                $dispatch = Dispatch::where('plant_id', $plantId)->findOrFail($input['dispatch_id']);
                if (!empty($input['batch_id']) && (int) $input['batch_id'] !== (int) $dispatch->batch_id) {
                    throw ValidationException::withMessages(['batch_id' => 'The batch does not belong to the selected dispatch.']);
                }
                if (!empty($input['customer_id']) && (int) $input['customer_id'] !== (int) $dispatch->customer_id) {
                    throw ValidationException::withMessages(['customer_id' => 'The customer does not belong to the selected dispatch.']);
                }
                if (in_array($dispatch->dispatch_status, ['Draft', 'Loading', 'Cancelled'], true) || !$dispatch->dispatch_time) {
                    throw ValidationException::withMessages(['dispatch_id' => 'Select a completed dispatch.']);
                }
            }
            return [[]];
        }
        $order = SalesOrder::where('plant_id', $plantId)->lockForUpdate()->find($input['sales_order_id']);
        if (!$order) {
            throw ValidationException::withMessages(['sales_order_id' => 'Select a sales order from the active plant.']);
        }
        $sources = [];
        $seen = [];
        foreach ($input['source_keys'] as $key) {
            [$kind, $id] = explode(':', $key);
            $batch = $kind === 'batch'
                ? Batch::where('plant_id', $plantId)->where('sales_order_id', $order->id)->where('status', '!=', Batch::STATUS_CANCELLED)->find($id)
                : null;
            $dispatch = $kind === 'dispatch'
                ? Dispatch::where('plant_id', $plantId)->where('sales_order_id', $order->id)->with(['batch', 'invoiceStatus', 'mixDesign', 'truck', 'unloadSite'])->find($id)
                : $batch?->dispatches()->where('plant_id', $plantId)->with(['invoiceStatus', 'mixDesign', 'truck', 'unloadSite'])->latest()->first();
            if (($kind === 'batch' && !$batch) || ($kind === 'dispatch' && !$dispatch)) {
                throw ValidationException::withMessages(['source_keys' => 'A selected production row does not belong to this sales order.']);
            }
            $batch = $batch ?: $dispatch?->batch;
            if ($batch && ((int) $batch->plant_id !== (int) $plantId || (int) $batch->sales_order_id !== (int) $order->id)) {
                throw ValidationException::withMessages(['source_keys' => 'The selected batch does not belong to this sales order.']);
            }
            if (($batch && (int) $batch->status === Batch::STATUS_CANCELLED) || $dispatch?->dispatch_status === 'Cancelled') {
                throw ValidationException::withMessages(['source_keys' => 'Cancelled production cannot be sent for testing.']);
            }
            $identity = $batch ? 'batch:' . $batch->id : 'dispatch:' . $dispatch->id;
            $used = QcSample::where('plant_id', $plantId)
                ->when($sample, fn($q) => $q->where('id', '!=', $sample->id))
                ->where(fn($q) => $q->when($batch, fn($q) => $q->where('batch_id', $batch->id))
                    ->when($dispatch, fn($q) => $q->orWhere('dispatch_id', $dispatch->id)))->exists();
            if ($used || isset($seen[$identity])) {
                throw ValidationException::withMessages(['source_keys' => 'A selected production row has already been sent for testing.']);
            }
            $seen[$identity] = true;
            $sources[] = [
                'batch_id' => $batch?->id, 'dispatch_id' => $dispatch?->id,
                'customer_id' => $dispatch?->customer_id ?: $order->customer_id,
                'concrete_grade_id' => $dispatch?->mixDesign?->concrete_grade_id ?: $order->mixDesign?->concrete_grade_id,
                'material_id' => null, 'truck_no' => $dispatch?->truck?->registration,
                'site_name' => $dispatch?->unloadSite?->name ?: $order->site?->name,
            ];
        }
        return $sources;
    }

    public function destroy(QcSample $sample)
    {
        $this->authorizeModule('delete');

        $plantId = app(PlantContextService::class)->plantId();
        abort_if($plantId && (int) $sample->plant_id !== (int) $plantId, 404);
        $sample->deleted_by = auth()->id();
        $sample->save();
        $sample->delete();

        return redirect()->route('quality.samples.index')->with('success', 'QC Sample removed.');
    }
}
