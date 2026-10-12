<?php

namespace App\Http\Controllers\QC;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\QC\QcSample;
use App\Models\QC\QcTest;
use App\Models\QC\QcTestType;
use App\Models\ConcreteGrade;
use App\Models\Product;
use App\Models\Patron;
use App\Models\PurchaseOrderHistory;
use App\Models\Batch;
use App\Models\Dispatch;
use App\Models\Personnel;
use App\Models\SalesOrder;
use Illuminate\Validation\ValidationException;
use App\Services\PlantContextService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class QCSampleController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'q_c_sample';

    public function index(Request $request)
    {
        $this->authorizeModule('menu');

        $ctx = app(PlantContextService::class);
        $plantId = $ctx->plantId();

        // Cleanup any accidental non-compressive duplicate pending tests (e.g. split tensile created alongside compressive)
        try {
            $duplicateIds = QcTest::whereHas('testType', function ($q) {
                    $q->where('name', 'like', '%Split Tensile%')
                      ->orWhere('name', 'like', '%Flexural%');
                })
                ->whereHas('sample', function ($q) {
                    $q->whereNotNull('concrete_grade_id');
                })
                ->where('overall_status', 'pending')
                ->whereIn('sample_id', function ($q) {
                    $q->select('sample_id')
                        ->from('mm_qc_tests')
                        ->groupBy('sample_id', 'age_days')
                        ->havingRaw('COUNT(*) > 1');
                })
                ->pluck('id');

            if ($duplicateIds->isNotEmpty()) {
                QcTest::whereIn('id', $duplicateIds)->delete();
            }
        } catch (\Throwable $e) {}

        $query = QcSample::with([
            'material',
            'concreteGrade',
            'customer',
            'batch',
            'dispatch.truck',
            'dispatch.mixDesign.concreteGrade',
            'sampler',
            'tester',
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
                  ->orWhereHas('batch', fn($b) => $b->where('batch_no', 'like', "%{$search}%"))
                  ->orWhereHas('dispatch', fn($d) => $d->where('dispatch_no', 'like', "%{$search}%"))
                  ->orWhereHas('concreteGrade', fn($cg) => $cg->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('material', fn($m) => $m->where('title', 'like', "%{$search}%"))
                  ->orWhereHas('customer', fn($c) => $c->where('legal_name', 'like', "%{$search}%"));
            });
        }

        if ($request->status && $request->status !== 'All') {
            $query->where('status', $request->status);
        }

        $allowedSorts = ['sample_no', 'sample_date', 'status', 'id'];
        $sort = in_array($request->input('sort'), $allowedSorts, true) ? $request->input('sort') : 'id';
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';
        $perPage = in_array($request->integer('per_page'), [10, 20, 30, 50, 100], true) ? $request->integer('per_page') : 20;
        $query->orderBy($sort, $direction);
        if ($sort !== 'id') $query->orderBy('id', $direction);
        $samples = $query->paginate($perPage)->withQueryString();

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
            ...$this->samplingOptions($plantId),
            'samples' => $samples,
            'concreteGrades' => $concreteGrades,
            'materials' => $materials,
            'customers' => $customers,
            'dispatches' => $dispatches,
            'testTypes' => $testTypes,
            'filters' => $request->only(['search', 'status', 'sort', 'direction']),
        ]);
    }

    public function create()
    {
        $this->authorizeModule('create');
        return redirect()->route('quality.samples.index');
    }

    public function edit(QcSample $sample)
    {
        $this->authorizeModule('edit');
        $ctx = app(PlantContextService::class);
        $plantId = $ctx->plantId();

        abort_if($plantId && (int) $sample->plant_id !== (int) $plantId, 404);
        $sample->load(['tests.testType', 'concreteGrade', 'material', 'customer', 'batch', 'dispatch', 'tester']);

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

        ['salesOrders' => $salesOrders, 'batches' => $batches,
            'usedBatchIds' => $usedBatchIds, 'usedDispatchIds' => $usedDispatchIds,
            'personnels' => $personnels] = $this->samplingOptions($plantId);

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
            'salesOrders' => $salesOrders,
            'usedBatchIds' => $usedBatchIds,
            'usedDispatchIds' => $usedDispatchIds,
            'testTypes' => $testTypes,
            'personnels' => $personnels,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeModule('create');

        $ctx = app(PlantContextService::class);
        $plantId = $ctx->plantId() ?: session('active_plant_id', 1);

        $validated = $request->validate([
            'sales_order_id' => 'nullable|integer',
            'source_keys' => 'required_with:sales_order_id|array|min:1',
            'source_keys.*' => ['required', 'string', 'distinct', 'regex:/^(batch|dispatch):[0-9]+$/'],
            'sample_date' => 'required|date',
            'tested_by' => 'nullable|integer',
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
            'schedule_milestones' => 'nullable|array',
            'schedule_milestones.*' => 'integer|min:1|max:365',
            'test_type_ids' => 'nullable|array',
            'test_type_ids.*' => 'exists:mm_qc_test_types,id',
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
                    'sampled_by' => $validated['tested_by'] ?? (auth()->id() ?: 1),
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
                        ->where(function ($q) {
                            $q->where('category', 'Concrete')
                              ->orWhere('name', 'like', '%Compressive%');
                        })
                        ->take(1)
                        ->pluck('id')
                        ->toArray();
                }

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

                // Determine test date from sample submit form or current date
                $testDate = !empty($validated['sample_date'])
                    ? date('Y-m-d H:i:s', strtotime($validated['sample_date']))
                    : now();

                // Create scheduled test events
                foreach ($targetTestTypeIds as $testTypeId) {
                    $testType = QcTestType::with('parameters')->find($testTypeId);
                    if (!$testType) continue;

                    if (!empty($chosenMilestones)) {
                        // Create scheduled test for each chosen milestone (7d, 15d, 28d)
                        foreach ($chosenMilestones as $age) {
                            $scheduledDate = date('Y-m-d', strtotime($validated['sample_date'] . " + {$age} days"));
                            $testNo = 'TST-' . $datePrefix . '-' . str_pad($count, 3, '0', STR_PAD_LEFT) . "-{$age}D";

                            // Standard IS 456 strength targets
                            if ($age <= 7) {
                                $targetVal = round($fck * 0.70, 2);
                                $minVal = round($fck * 0.65, 2);
                            } elseif ($age <= 15) {
                                $targetVal = round($fck * 0.90, 2);
                                $minVal = round($fck * 0.85, 2);
                            } else {
                                $targetVal = round($fck * 1.00, 2);
                                $minVal = round($fck * 0.95, 2);
                            }

                            // Check if a matching parameter exists in test type config
                            $matchingParam = $testType->parameters->first(function ($p) use ($age) {
                                return preg_match('/' . $age . '\s*(day|d)/i', (string)($p->name . ' ' . $p->default_value));
                            });

                            QcTest::create([
                                'plant_id' => $plantId ?? 1,
                                'sample_id' => $sample->id,
                                'test_type_id' => $testType->id,
                                'test_no' => $testNo,
                                'test_date' => $testDate,
                                'tested_by' => $validated['tested_by'] ?? (auth()->id() ?: 1),
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
                                    'test_date' => $testDate,
                                    'tested_by' => $validated['tested_by'] ?? (auth()->id() ?: 1),
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
                                'test_date' => $testDate,
                                'tested_by' => $validated['tested_by'] ?? (auth()->id() ?: 1),
                                'scheduled_date' => date('Y-m-d', strtotime($validated['sample_date'] . ' + 28 days')),
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
            'sample_date' => 'required|date',
            'tested_by' => 'nullable|integer',
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

        $plantId = app(PlantContextService::class)->plantId();
        abort_if($plantId && (int) $sample->plant_id !== (int) $plantId, 404);
        DB::transaction(function () use ($validated, $sample) {
            $sources = $this->resolveSources($validated, $sample->plant_id, $sample);
            if (count($sources) !== 1) {
                throw ValidationException::withMessages(['source_keys' => 'Select one production row when editing a sample.']);
            }
            unset($validated['sales_order_id'], $validated['source_keys']);
            $sample->update(array_merge($validated, $sources[0], ['updated_by' => auth()->id()]));
        });

        return redirect()->route('quality.samples.index')->with('success', 'Concrete Sample details updated.');
    }

    private function samplingOptions(?int $plantId): array
    {
        $salesOrders = SalesOrder::with([
            'customer:id,legal_name', 'site:id,name', 'mixDesign.concreteGrade',
            'batches.dispatches.customer', 'batches.dispatches.unloadSite',
            'batches.dispatches.truck', 'batches.dispatches.mixDesign.concreteGrade',
            'batches.dispatches.invoiceStatus.invoice', 'dispatches.batch',
            'dispatches.customer', 'dispatches.unloadSite', 'dispatches.truck',
            'dispatches.mixDesign.concreteGrade', 'dispatches.invoiceStatus.invoice',
        ])->when($plantId, fn($q) => $q->where('plant_id', $plantId))
        ->where('status', SalesOrder::STATUS_IN_PROGRESS)
        ->has('batches')
        ->latest()->get();
        $batches = $salesOrders->flatMap->batches;
        $usedBatchIds = QcSample::where('plant_id', $plantId)->whereNotNull('batch_id')
            ->pluck('batch_id')->all();
        $usedDispatchIds = QcSample::where('plant_id', $plantId)->whereNotNull('dispatch_id')
            ->pluck('dispatch_id')->all();

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

        return compact('salesOrders', 'batches', 'usedBatchIds', 'usedDispatchIds', 'personnels');
    }

    private function resolveSources(array $input, int $plantId, ?QcSample $sample = null): array
    {
        // Keep material samples and existing API clients compatible.
        if (empty($input['sales_order_id'])) {
            if (array_key_exists('sales_order_id', $input)) {
                throw ValidationException::withMessages(['sales_order_id' => 'Select a sales order.']);
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
