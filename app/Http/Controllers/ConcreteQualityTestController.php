<?php

namespace App\Http\Controllers;

use App\Models\ConcreteQualityTest;
use App\Models\Plant;
use App\Models\Batch;
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
            'batch.workOrder.mixDesign', 
            'batch.workOrder.customer', 
            'batch.dispatches.truck',
            'photos'
        ])
            ->when($activePlantId, function ($q) use ($activePlantId) {
                return $q->where('plant_id', $activePlantId);
            });

        // Search Filter
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('test_code', 'like', "%{$search}%")
                  ->orWhere('tested_by', 'like', "%{$search}%")
                  ->orWhere('status', 'like', "%{$search}%")
                  ->orWhere('remarks', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortField = $request->input('sort_field', 'id');
        $sortDirection = $request->input('sort_direction', 'desc');
        
        $allowedSorts = [
            'id', 'test_code', 'test_date', 'status', 'slump_value', 
            'fresh_temperature', 'cube_strength_7_days', 'cube_strength_28_days'
        ];

        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDirection);
        } else {
            $query->orderBy('id', 'desc');
        }

        $tests = $query->paginate(30)->withQueryString();

        // Optimized dropdown data
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
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorizeModule('create');
        if ($request->hasFile('photos') && !is_array($request->file('photos'))) {
            $request->files->set('photos', [$request->file('photos')]);
        }

        $validated = $request->validate([
            
            'batch_id' => 'nullable|exists:mm_batches,id',
            'test_date' => 'required|date',
            'tested_by' => 'nullable|string|max:100',
            
            // Fresh Concrete
            'slump_value' => 'required|numeric|min:0|max:500',
            'fresh_temperature' => 'required|numeric|min:0|max:100',
            'air_content' => 'required|numeric|min:0|max:20',
            'fresh_density' => 'required|numeric|min:100|max:5000',
            
            // Hardened Concrete
            'cube_strength_7_days' => 'required|numeric|min:0|max:200',
            'cube_strength_28_days' => 'required|numeric|min:0|max:200',
            'core_test_strength' => 'nullable|numeric|min:0|max:200',
            
            // Durability
            'water_permeability' => 'nullable|numeric|min:0|max:500',
            'rapid_chloride_permeability' => 'nullable|numeric|min:0|max:10000',
            
            'status' => 'required|string|in:pending,passed,failed',
            'remarks' => 'nullable|string',
            'photos' => 'nullable|array',
            'photos.*' => 'required|file|image|max:10240',
        ]);

        DB::beginTransaction();
        try {
            // Generate elegant Quality Code
            $year = date('Y', strtotime($validated['test_date']));
            $count = ConcreteQualityTest::whereYear('test_date', $year)->count() + 1;
            $validated['test_code'] = 'QC-' . $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
            $validated['created_by'] = auth()->id();
            $validated['plant_id'] = session('active_plant_id');

            $test = ConcreteQualityTest::create($validated);

            if ($request->hasFile('photos')) {
                $plant = Plant::find($validated['plant_id']);
                $plantFolderName = $plant ? preg_replace('/\s+/', '', $plant->name) : 'default';
                $targetDir = "qc_photos/{$plantFolderName}";

                $files = $request->file('photos');
                if (!is_array($files)) {
                    $files = [$files];
                }
                foreach ($files as $file) {
                    $path = $file->store($targetDir, 'public');
                    Image::create([
                        'category' => 'QCTest',
                        'ref_no' => $test->id,
                        'image_path' => $path,
                        'image_name' => $file->getClientOriginalName(),
                        'plant_id' => $test->plant_id,
                        'created_by' => auth()->id(),
                    ]);
                }
            }

            DB::commit();
            return redirect()->back()->with('success', 'Concrete Quality Test record created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Concrete Quality Test store error: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Failed to save record: ' . $e->getMessage()]);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ConcreteQualityTest $concreteQualityTest)
    {
        $this->authorizeModule('edit');
        if ($request->hasFile('photos') && !is_array($request->file('photos'))) {
            $request->files->set('photos', [$request->file('photos')]);
        }

        $validated = $request->validate([
            'batch_id' => 'nullable|exists:mm_batches,id',
            'test_date' => 'required|date',
            'tested_by' => 'nullable|string|max:100',
            
            // Fresh Concrete
            'slump_value' => 'required|numeric|min:0|max:500',
            'fresh_temperature' => 'required|numeric|min:0|max:100',
            'air_content' => 'required|numeric|min:0|max:20',
            'fresh_density' => 'required|numeric|min:100|max:5000',
            
            // Hardened Concrete
            'cube_strength_7_days' => 'required|numeric|min:0|max:200',
            'cube_strength_28_days' => 'required|numeric|min:0|max:200',
            'core_test_strength' => 'nullable|numeric|min:0|max:200',
            
            // Durability
            'water_permeability' => 'nullable|numeric|min:0|max:500',
            'rapid_chloride_permeability' => 'nullable|numeric|min:0|max:10000',
            
            'status' => 'required|string|in:pending,passed,failed',
            'remarks' => 'nullable|string',
            'photos' => 'nullable|array',
            'photos.*' => 'required|file|image|max:10240',
            'deleted_photo_ids' => 'nullable|array',
            'deleted_photo_ids.*' => 'required|exists:mm_images,id',
        ]);

        DB::beginTransaction();
        try {
            $validated['updated_by'] = auth()->id();
            $validated['plant_id'] = session('active_plant_id');

            // Handle deleted photo ids
            if ($request->has('deleted_photo_ids')) {
                $deletedIds = $request->input('deleted_photo_ids');
                $imagesToDelete = Image::whereIn('id', $deletedIds)
                    ->where('category', 'QCTest')
                    ->where('ref_no', $concreteQualityTest->id)
                    ->get();
                foreach ($imagesToDelete as $img) {
                    if ($img->image_path) {
                        \Illuminate\Support\Facades\Storage::disk('public')->delete($img->image_path);
                    }
                    $img->delete();
                }
            }

            // Handle new photo uploads
            if ($request->hasFile('photos')) {
                $plant = Plant::find($validated['plant_id']);
                $plantFolderName = $plant ? preg_replace('/\s+/', '', $plant->name) : 'default';
                $targetDir = "qc_photos/{$plantFolderName}";

                $files = $request->file('photos');
                if (!is_array($files)) {
                    $files = [$files];
                }
                foreach ($files as $file) {
                    $path = $file->store($targetDir, 'public');
                    Image::create([
                        'category' => 'QCTest',
                        'ref_no' => $concreteQualityTest->id,
                        'image_path' => $path,
                        'image_name' => $file->getClientOriginalName(),
                        'plant_id' => $concreteQualityTest->plant_id,
                        'created_by' => auth()->id(),
                    ]);
                }
            }

            $concreteQualityTest->update($validated);

            DB::commit();
            return redirect()->back()->with('success', 'Concrete Quality Test record updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Concrete Quality Test update error: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Failed to update record: ' . $e->getMessage()]);
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
            return redirect()->back()->with('success', 'Concrete Quality Test record deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Concrete Quality Test delete error: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Failed to delete record.']);
        }
    }
}
