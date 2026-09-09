<?php

namespace App\Http\Controllers;

use App\Models\ConcreteQualityTest;
use App\Models\ConcreteQualityTestSpecimen;
use App\Models\Plant;
use App\Models\Batch;
use App\Models\Patron;
use App\Models\Invoice;
use App\Models\ConcreteGrade;
use App\Models\Image;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Concerns\AuthorizesModule;

class ConcreteQualityTestController extends Controller
{
    use AuthorizesModule;

    protected string $module = 'concrete_quality_tests';

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorizeModule('menu');
        $activePlantId = session('active_plant_id');

        $query = ConcreteQualityTest::with([
            'plant',
            'specimens',
            'patron',
            'batch.workOrder.mixDesign',
            'batch.workOrder.customer',
            'photos'
        ])
            ->when($activePlantId, function ($q) use ($activePlantId) {
                return $q->where('plant_id', $activePlantId);
            });

        // Search Filter
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('test_number', 'like', "%{$search}%")
                  ->orWhere('test_code', 'like', "%{$search}%")
                  ->orWhere('account_name', 'like', "%{$search}%")
                  ->orWhere('invoice_no', 'like', "%{$search}%")
                  ->orWhere('grade', 'like', "%{$search}%")
                  ->orWhere('tested_by', 'like', "%{$search}%")
                  ->orWhere('lab_technician', 'like', "%{$search}%")
                  ->orWhere('field_technician', 'like', "%{$search}%")
                  ->orWhere('project', 'like', "%{$search}%")
                  ->orWhere('status', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortField = $request->input('sort_field', 'id');
        $sortDirection = $request->input('sort_direction', 'desc');

        $allowedSorts = [
            'id', 'test_number', 'test_code', 'account_name', 'invoice_no', 'grade',
            'concrete_date', 'date_of_testing', 'age_of_test_days', 'slump_value',
            'fresh_temperature', 'avg_compressive_strength', 'status'
        ];

        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDirection);
        } else {
            $query->orderBy('id', 'desc');
        }

        $tests = $query->paginate(30)->withQueryString();

        $plants = Plant::select('id', 'name')->get();

        $batches = Batch::when($activePlantId, function ($q) use ($activePlantId) {
                return $q->where('plant_id', $activePlantId);
            })
            ->select('id', 'batch_no', 'start_time')
            ->orderBy('id', 'desc')
            ->limit(50)
            ->get();

        return Inertia::render('ConcreteQualityTests/Index', [
            'tests' => $tests,
            'filters' => $request->only(['search', 'sort_field', 'sort_direction']),
            'plants' => $plants,
            'batches' => $batches,
            'activePlantId' => $activePlantId,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorizeModule('create');
        $activePlantId = session('active_plant_id');

        $plants = Plant::select('id', 'name')->get();
        $grades = ConcreteGrade::select('id', 'name', 'concrete_code')->where('status', 1)->get();

        // Standard default grades if none exist
        if ($grades->isEmpty()) {
            $grades = collect([
                ['name' => 'M15', 'concrete_code' => 'M15'],
                ['name' => 'M20', 'concrete_code' => 'M20'],
                ['name' => 'M20 (Gst)', 'concrete_code' => 'M20 (Gst)'],
                ['name' => 'M25', 'concrete_code' => 'M25'],
                ['name' => 'M25 (Gst)', 'concrete_code' => 'M25 (Gst)'],
                ['name' => 'M30', 'concrete_code' => 'M30'],
                ['name' => 'M35', 'concrete_code' => 'M35'],
                ['name' => 'M40', 'concrete_code' => 'M40'],
            ]);
        }

        $patrons = Patron::withoutGlobalScope('active_operational_status')
            ->select('id', 'legal_name', 'plant_id')
            ->limit(100)
            ->get()
            ->map(function ($p) {
                return [
                    'id' => $p->id,
                    'name' => $p->name ?? $p->legal_name,
                ];
            });

        return Inertia::render('ConcreteQualityTests/Create', [
            'plants' => $plants,
            'activePlantId' => $activePlantId,
            'grades' => $grades,
            'patrons' => $patrons,
            'defaultTestNumber' => 'AUTO GEN ON SAVE',
            'today' => now()->format('Y-m-d'),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorizeModule('create');

        $validated = $request->validate([
            'plant_id' => 'required|exists:mm_plants,id',
            'account_name' => 'required|string|max:255',
            'patron_id' => 'nullable|exists:mm_patrons,id',
            'invoice_id' => 'nullable|exists:mm_invoices,id',
            'invoice_no' => 'nullable|string|max:100',
            'grade' => 'required|string|max:100',
            'concrete_date' => 'required|date',
            'age_of_test_days' => 'required|integer|min:1|max:365',
            'date_of_testing' => 'required|date',
            'project' => 'nullable|string|max:255',
            'billing_address' => 'nullable|string',
            'shipping_address' => 'nullable|string',
            'description' => 'nullable|string',

            // Dimensions & Fresh concrete properties
            'dimension_length' => 'required|numeric|min:1|max:100',
            'dimension_width' => 'required|numeric|min:1|max:100',
            'dimension_height' => 'required|numeric|min:1|max:100',
            'fresh_unit_weight' => 'nullable|numeric|min:0',
            'slump_value' => 'required|numeric|min:0|max:500',
            'air_content' => 'nullable|numeric|min:0|max:100',
            'fresh_temperature' => 'nullable|numeric|min:0|max:100',

            // Technicians
            'lab_technician' => 'nullable|string|max:100',
            'field_technician' => 'nullable|string|max:100',

            // Overall Ident Mark
            'ident_mark' => 'nullable|string|max:50',

            // Cube Specimens
            'specimens' => 'nullable|array',
            'specimens.*.ident_mark' => 'nullable|string|max:50',
            'specimens.*.weight_kg' => 'nullable|numeric|min:0',
            'specimens.*.load_kn' => 'nullable|numeric|min:0',
            'specimens.*.compressive_strength' => 'nullable|numeric|min:0',

            // Status & Remarks
            'status' => 'nullable|string|in:pending,passed,failed',
            'remarks' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            // Generate sequential Test Number (e.g. Test - 4025)
            $lastTest = ConcreteQualityTest::whereNotNull('test_number')
                ->where('test_number', 'like', 'Test - %')
                ->orderBy('id', 'desc')
                ->first();

            $nextNumber = 4001;
            if ($lastTest && preg_match('/Test\s*-\s*(\d+)/i', $lastTest->test_number, $matches)) {
                $nextNumber = max($nextNumber, intval($matches[1]) + 1);
            } else {
                $totalCount = ConcreteQualityTest::count();
                $nextNumber = 4001 + $totalCount;
            }

            $generatedTestNumber = 'Test - ' . $nextNumber;
            $validated['test_number'] = $generatedTestNumber;
            $validated['test_code'] = $generatedTestNumber;

            // Compute Specimen Compressive Strengths
            $dimL = (float)($validated['dimension_length'] ?? 15);
            $dimW = (float)($validated['dimension_width'] ?? 15);
            // Area in mm2 = (L cm * 10) * (W cm * 10)
            $areaMm2 = ($dimL * 10) * ($dimW * 10);
            if ($areaMm2 <= 0) {
                $areaMm2 = 22500; // standard 15x15 cm = 22500 mm2
            }

            $specimensData = $validated['specimens'] ?? [];
            $computedStrengths = [];

            foreach ($specimensData as $idx => &$spec) {
                $loadKn = isset($spec['load_kn']) && is_numeric($spec['load_kn']) ? (float)$spec['load_kn'] : null;
                if ($loadKn !== null && $loadKn > 0) {
                    // Strength (N/mm2) = (Load in kN * 1000) / Area in mm2
                    $strength = round(($loadKn * 1000) / $areaMm2, 2);
                    $spec['compressive_strength'] = $strength;
                    $computedStrengths[] = $strength;
                }
                if (empty($spec['ident_mark']) && !empty($validated['ident_mark'])) {
                    $spec['ident_mark'] = $validated['ident_mark'];
                }
                $spec['sort_order'] = $idx + 1;
            }
            unset($spec);

            // Compute Average Compressive Strength
            if (!empty($computedStrengths)) {
                $validated['avg_compressive_strength'] = round(array_sum($computedStrengths) / count($computedStrengths), 2);
            } else {
                $validated['avg_compressive_strength'] = null;
            }

            // Sync with 7-day or 28-day column
            if (($validated['age_of_test_days'] ?? 7) == 7 && $validated['avg_compressive_strength']) {
                $validated['cube_strength_7_days'] = $validated['avg_compressive_strength'];
                $validated['cube_strength_28_days'] = round($validated['avg_compressive_strength'] * 1.5, 2);
            } elseif (($validated['age_of_test_days'] ?? 28) == 28 && $validated['avg_compressive_strength']) {
                $validated['cube_strength_28_days'] = $validated['avg_compressive_strength'];
                $validated['cube_strength_7_days'] = round($validated['avg_compressive_strength'] * 0.67, 2);
            } else {
                $validated['cube_strength_7_days'] = $validated['avg_compressive_strength'] ?? 0;
                $validated['cube_strength_28_days'] = $validated['avg_compressive_strength'] ?? 0;
            }

            $validated['status'] = $validated['status'] ?? 'passed';
            $validated['created_by'] = auth()->id();
            $validated['fresh_density'] = $validated['fresh_unit_weight'] ?? 2400;

            // Remove specimens key before saving to tests table
            unset($validated['specimens']);

            $test = ConcreteQualityTest::create($validated);

            // Save specimens
            foreach ($specimensData as $spec) {
                if (!empty($spec['weight_kg']) || !empty($spec['load_kn']) || !empty($spec['compressive_strength'])) {
                    $test->specimens()->create([
                        'ident_mark' => $spec['ident_mark'] ?? $test->ident_mark ?? 'EB',
                        'weight_kg' => $spec['weight_kg'] ?? null,
                        'load_kn' => $spec['load_kn'] ?? null,
                        'compressive_strength' => $spec['compressive_strength'] ?? null,
                        'sort_order' => $spec['sort_order'] ?? 0,
                    ]);
                }
            }

            DB::commit();
            return redirect()->route('concrete-quality-tests.show', $test->id)
                ->with('success', 'Concrete Quality Test record ' . $test->test_number . ' created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Concrete Quality Test store error: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Failed to save test record: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(ConcreteQualityTest $concreteQualityTest)
    {
        $this->authorizeModule('view');

        $concreteQualityTest->load([
            'plant',
            'specimens',
            'patron',
            'invoice',
            'photos'
        ]);

        return Inertia::render('ConcreteQualityTests/Show', [
            'test' => $concreteQualityTest,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ConcreteQualityTest $concreteQualityTest)
    {
        $this->authorizeModule('edit');

        $concreteQualityTest->load(['plant', 'specimens', 'patron', 'invoice']);

        $plants = Plant::select('id', 'name')->get();
        $grades = ConcreteGrade::select('id', 'name', 'concrete_code')->where('status', 1)->get();

        if ($grades->isEmpty()) {
            $grades = collect([
                ['name' => 'M15', 'concrete_code' => 'M15'],
                ['name' => 'M20', 'concrete_code' => 'M20'],
                ['name' => 'M20 (Gst)', 'concrete_code' => 'M20 (Gst)'],
                ['name' => 'M25', 'concrete_code' => 'M25'],
                ['name' => 'M25 (Gst)', 'concrete_code' => 'M25 (Gst)'],
                ['name' => 'M30', 'concrete_code' => 'M30'],
                ['name' => 'M35', 'concrete_code' => 'M35'],
                ['name' => 'M40', 'concrete_code' => 'M40'],
            ]);
        }

        $patrons = Patron::withoutGlobalScope('active_operational_status')
            ->select('id', 'legal_name', 'plant_id')
            ->limit(100)
            ->get()
            ->map(function ($p) {
                return [
                    'id' => $p->id,
                    'name' => $p->name ?? $p->legal_name,
                ];
            });

        return Inertia::render('ConcreteQualityTests/Edit', [
            'test' => $concreteQualityTest,
            'plants' => $plants,
            'grades' => $grades,
            'patrons' => $patrons,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ConcreteQualityTest $concreteQualityTest)
    {
        $this->authorizeModule('edit');

        $validated = $request->validate([
            'plant_id' => 'required|exists:mm_plants,id',
            'account_name' => 'required|string|max:255',
            'patron_id' => 'nullable|exists:mm_patrons,id',
            'invoice_id' => 'nullable|exists:mm_invoices,id',
            'invoice_no' => 'nullable|string|max:100',
            'grade' => 'required|string|max:100',
            'concrete_date' => 'required|date',
            'age_of_test_days' => 'required|integer|min:1|max:365',
            'date_of_testing' => 'required|date',
            'project' => 'nullable|string|max:255',
            'billing_address' => 'nullable|string',
            'shipping_address' => 'nullable|string',
            'description' => 'nullable|string',

            // Dimensions & Fresh properties
            'dimension_length' => 'required|numeric|min:1|max:100',
            'dimension_width' => 'required|numeric|min:1|max:100',
            'dimension_height' => 'required|numeric|min:1|max:100',
            'fresh_unit_weight' => 'nullable|numeric|min:0',
            'slump_value' => 'required|numeric|min:0|max:500',
            'air_content' => 'nullable|numeric|min:0|max:100',
            'fresh_temperature' => 'nullable|numeric|min:0|max:100',

            // Technicians
            'lab_technician' => 'nullable|string|max:100',
            'field_technician' => 'nullable|string|max:100',

            // Overall Ident Mark
            'ident_mark' => 'nullable|string|max:50',

            // Cube Specimens
            'specimens' => 'nullable|array',
            'specimens.*.id' => 'nullable|integer',
            'specimens.*.ident_mark' => 'nullable|string|max:50',
            'specimens.*.weight_kg' => 'nullable|numeric|min:0',
            'specimens.*.load_kn' => 'nullable|numeric|min:0',
            'specimens.*.compressive_strength' => 'nullable|numeric|min:0',

            // Status & Remarks
            'status' => 'nullable|string|in:pending,passed,failed',
            'remarks' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $validated['updated_by'] = auth()->id();

            // Compute Specimen Compressive Strengths
            $dimL = (float)($validated['dimension_length'] ?? 15);
            $dimW = (float)($validated['dimension_width'] ?? 15);
            $areaMm2 = ($dimL * 10) * ($dimW * 10);
            if ($areaMm2 <= 0) {
                $areaMm2 = 22500;
            }

            $specimensData = $validated['specimens'] ?? [];
            $computedStrengths = [];

            foreach ($specimensData as $idx => &$spec) {
                $loadKn = isset($spec['load_kn']) && is_numeric($spec['load_kn']) ? (float)$spec['load_kn'] : null;
                if ($loadKn !== null && $loadKn > 0) {
                    $strength = round(($loadKn * 1000) / $areaMm2, 2);
                    $spec['compressive_strength'] = $strength;
                    $computedStrengths[] = $strength;
                }
                if (empty($spec['ident_mark']) && !empty($validated['ident_mark'])) {
                    $spec['ident_mark'] = $validated['ident_mark'];
                }
                $spec['sort_order'] = $idx + 1;
            }
            unset($spec);

            // Compute Average Compressive Strength
            if (!empty($computedStrengths)) {
                $validated['avg_compressive_strength'] = round(array_sum($computedStrengths) / count($computedStrengths), 2);
            }

            if (($validated['age_of_test_days'] ?? 7) == 7 && isset($validated['avg_compressive_strength'])) {
                $validated['cube_strength_7_days'] = $validated['avg_compressive_strength'];
            } elseif (($validated['age_of_test_days'] ?? 28) == 28 && isset($validated['avg_compressive_strength'])) {
                $validated['cube_strength_28_days'] = $validated['avg_compressive_strength'];
            }

            unset($validated['specimens']);

            $concreteQualityTest->update($validated);

            // Sync specimens
            $existingSpecimenIds = $concreteQualityTest->specimens()->pluck('id')->toArray();
            $updatedSpecimenIds = [];

            foreach ($specimensData as $spec) {
                if (!empty($spec['id']) && in_array($spec['id'], $existingSpecimenIds)) {
                    $specimen = ConcreteQualityTestSpecimen::find($spec['id']);
                    if ($specimen) {
                        $specimen->update([
                            'ident_mark' => $spec['ident_mark'] ?? $concreteQualityTest->ident_mark ?? 'EB',
                            'weight_kg' => $spec['weight_kg'] ?? null,
                            'load_kn' => $spec['load_kn'] ?? null,
                            'compressive_strength' => $spec['compressive_strength'] ?? null,
                            'sort_order' => $spec['sort_order'] ?? 0,
                        ]);
                        $updatedSpecimenIds[] = $specimen->id;
                    }
                } elseif (!empty($spec['weight_kg']) || !empty($spec['load_kn']) || !empty($spec['compressive_strength'])) {
                    $newSpecimen = $concreteQualityTest->specimens()->create([
                        'ident_mark' => $spec['ident_mark'] ?? $concreteQualityTest->ident_mark ?? 'EB',
                        'weight_kg' => $spec['weight_kg'] ?? null,
                        'load_kn' => $spec['load_kn'] ?? null,
                        'compressive_strength' => $spec['compressive_strength'] ?? null,
                        'sort_order' => $spec['sort_order'] ?? 0,
                    ]);
                    $updatedSpecimenIds[] = $newSpecimen->id;
                }
            }

            // Delete removed specimens
            $toDelete = array_diff($existingSpecimenIds, $updatedSpecimenIds);
            if (!empty($toDelete)) {
                ConcreteQualityTestSpecimen::whereIn('id', $toDelete)->delete();
            }

            DB::commit();
            return redirect()->route('concrete-quality-tests.show', $concreteQualityTest->id)
                ->with('success', 'Concrete Quality Test record updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Concrete Quality Test update error: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Failed to update record: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ConcreteQualityTest $concreteQualityTest)
    {
        $this->authorizeModule('delete');
        try {
            $concreteQualityTest->update(['deleted_by' => auth()->id()]);
            $concreteQualityTest->delete();
            return redirect()->route('concrete-quality-tests.index')
                ->with('success', 'Concrete Quality Test record deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Concrete Quality Test delete error: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Failed to delete record.']);
        }
    }

    /**
     * Lookup invoices for auto-populating Concrete Quality Test form.
     */
    public function lookupInvoices(Request $request)
    {
        $search = $request->input('search');
        $plantId = $request->input('plant_id') ?: session('active_plant_id');

        $query = Invoice::with(['partner.addresses', 'items.mixDesign', 'items.concreteGrade'])
            ->when($plantId, function ($q) use ($plantId) {
                return $q->where('plant_id', $plantId);
            });

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhere(DB::raw("CONCAT(COALESCE(prefix, ''), invoice_number)"), 'like', "%{$search}%")
                  ->orWhereHas('partner', function ($pq) use ($search) {
                      $pq->where('legal_name', 'like', "%{$search}%");
                  });
            });
        }

        $invoices = $query->orderBy('id', 'desc')->limit(20)->get();

        $results = $invoices->map(function ($inv) {
            $partner = $inv->partner;
            $billingAddress = '';
            $shippingAddress = '';

            if ($partner) {
                $addr = $partner->addresses()->first();
                if ($addr) {
                    $billingAddress = trim("{$partner->name}, {$addr->line_1} {$addr->line_2}, {$addr->city} {$addr->zipcode}");
                    $shippingAddress = $billingAddress;
                } else {
                    $billingAddress = $partner->name;
                    $shippingAddress = $partner->name;
                }
            }

            // Extract grade from first invoice item
            $grade = '';
            if ($inv->items->isNotEmpty()) {
                $firstItem = $inv->items->first();
                $grade = $firstItem->concreteGrade?->name ?? $firstItem->mixDesign?->design_name ?? $firstItem->item_name ?? '';
            }

            return [
                'id' => $inv->id,
                'full_number' => $inv->full_number,
                'invoice_number' => $inv->invoice_number,
                'invoice_date' => $inv->invoice_date?->format('Y-m-d') ?? '',
                'account_name' => $partner?->name ?? '',
                'patron_id' => $partner?->id ?? null,
                'billing_address' => $billingAddress,
                'shipping_address' => $shippingAddress,
                'grade' => $grade,
                'concrete_date' => $inv->invoice_date?->format('Y-m-d') ?? '',
            ];
        });

        return response()->json($results);
    }
}
