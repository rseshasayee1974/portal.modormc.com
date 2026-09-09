<?php

namespace App\Http\Controllers\QC;

use App\Http\Controllers\Controller;
use App\Models\QC\QcUnit;
use App\Services\PlantContextService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class QCUnitController extends Controller
{
    public function index(Request $request)
    {
        $ctx = app(PlantContextService::class);
        $plantId = $ctx->plantId();

        $query = QcUnit::query();
        if ($plantId) {
            $query->where(function ($q) use ($plantId) {
                $q->whereNull('plant_id')
                  ->orWhere('plant_id', $plantId);
            });
        }

        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('symbol', 'like', "%{$search}%")
                  ->orWhere('dimension', 'like', "%{$search}%");
            });
        }

        $units = $query->orderBy('name')->get();

        return Inertia::render('Quality/Configuration/Units/Index', [
            'units' => $units,
            'filters' => $request->only(['search']),
            'dimensions' => ['pressure', 'force', 'mass', 'length', 'volume', 'density', 'temperature', 'time', 'ratio', 'area', 'other'],
        ]);
    }

    public function create()
    {
        return Inertia::render('Quality/Configuration/Units/Create', [
            'dimensions' => ['mass', 'length', 'volume', 'pressure', 'force', 'ratio', 'temperature', 'density', 'time', 'other'],
        ]);
    }

    public function store(Request $request)
    {
        $ctx = app(PlantContextService::class);
        $plantId = $ctx->plantId();

        $validated = $request->validate([
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:150',
            'symbol' => 'required|string|max:30',
            'dimension' => 'nullable|string|max:50',
            'is_active' => 'boolean',
        ]);

        $validated['plant_id'] = $plantId;
        $validated['created_by'] = auth()->id() ?: 1;

        $unit = QcUnit::create($validated);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'unit' => $unit,
                'message' => 'QC Unit created successfully.'
            ]);
        }

        return redirect()->route('quality.config.units.index')->with('success', 'QC Unit created successfully.');
    }

    public function edit(QcUnit $unit)
    {
        return Inertia::render('Quality/Configuration/Units/Edit', [
            'unit' => $unit,
            'dimensions' => ['mass', 'length', 'volume', 'pressure', 'force', 'ratio', 'temperature', 'density', 'time', 'other'],
        ]);
    }

    public function update(Request $request, QcUnit $unit)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:150',
            'symbol' => 'required|string|max:30',
            'dimension' => 'nullable|string|max:50',
            'is_active' => 'boolean',
        ]);

        $validated['updated_by'] = auth()->id() ?: 1;
        $unit->update($validated);

        return redirect()->route('quality.config.units.index')->with('success', 'QC Unit updated successfully.');
    }

    public function destroy(QcUnit $unit)
    {
        $unit->updateQuietly([
            'deleted_by' => auth()->id() ?: 1,
        ]);
        $unit->delete();

        return redirect()->back()->with('success', 'QC Unit removed.');
    }

    public function toggleActive(QcUnit $unit)
    {
        $unit->update([
            'is_active' => !$unit->is_active,
            'updated_by' => auth()->id() ?: 1,
        ]);

        return redirect()->back()->with('success', "Unit '{$unit->name}' status updated.");
    }
}

