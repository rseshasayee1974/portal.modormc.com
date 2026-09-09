<?php

namespace App\Http\Controllers\QC;

use App\Http\Controllers\Controller;
use App\Models\QC\QcTestType;
use App\Models\QC\QcMaterialTest;
use App\Models\Product;
use App\Services\PlantContextService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class QCMaterialTestMappingController extends Controller
{
    public function index(Request $request)
    {
        $ctx = app(PlantContextService::class);
        $plantId = $ctx->plantId();

        $materials = Product::with(['category']);
        if ($plantId) {
            $materials->where('plant_id', $plantId);
        }
        $materials = $materials->where('status', true)->get();

        $testTypesQuery = QcTestType::where('is_active', true);
        if ($plantId) {
            $testTypesQuery->where(function ($q) use ($plantId) {
                $q->where('plant_id', $plantId)
                  ->orWhereNull('plant_id');
            });
        }
        $testTypes = $testTypesQuery->get(['id', 'code', 'name', 'category']);

        $mappingsQuery = QcMaterialTest::where('is_active', true);
        if ($plantId) {
            $mappingsQuery->where(function ($q) use ($plantId) {
                $q->where('plant_id', $plantId)
                  ->orWhereNull('plant_id');
            });
        }
        $mappings = $mappingsQuery->get();

        return Inertia::render('Quality/Configuration/MaterialMapping/Index', [
            'materials' => $materials,
            'testTypes' => $testTypes,
            'mappings' => $mappings,
        ]);
    }

    public function save(Request $request)
    {
        $ctx = app(PlantContextService::class);
        $plantId = $ctx->plantId();

        $validated = $request->validate([
            'material_id' => 'required|exists:mm_products,id',
            'test_type_ids' => 'array',
            'test_type_ids.*' => 'exists:mm_qc_test_types,id',
        ]);

        $materialId = $validated['material_id'];
        $testTypeIds = $validated['test_type_ids'] ?? [];

        // Deactivate existing mappings for this material
        $deactivateQuery = QcMaterialTest::where('material_id', $materialId);
        if ($plantId) {
            $deactivateQuery->where('plant_id', $plantId);
        }
        $deactivateQuery->update(['is_active' => false]);

        // Insert or activate selected test types
        foreach ($testTypeIds as $testTypeId) {
            QcMaterialTest::updateOrCreate(
                [
                    'plant_id' => $plantId,
                    'material_id' => $materialId,
                    'test_type_id' => $testTypeId,
                ],
                [
                    'is_required' => true,
                    'is_active' => true,
                    'updated_by' => auth()->id(),
                ]
            );
        }

        return redirect()->back()->with('success', 'Material QC tests configured successfully.');
    }
}
