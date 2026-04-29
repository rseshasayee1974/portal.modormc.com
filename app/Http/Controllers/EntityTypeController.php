<?php

namespace App\Http\Controllers;

use App\Models\EntityType;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesModule;

class EntityTypeController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'entity_types';
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorizeModule('create');
        $validated = $request->validate([
            'type' => 'required|string|max:100|unique:mm_entity_types,type',
        ]);

        $entityType = EntityType::create($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'entityType' => $entityType,
                'message' => 'Entity Type created successfully.'
            ]);
        }

        return redirect()->route('entitytypes.index')->with('success', 'Entity Type created successfully.');
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorizeModule('menu');
        return Inertia::render('EntityTypes/Index', [
            'entityTypes' => EntityType::all()
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, EntityType $entityType)
    {
        $this->authorizeModule('edit');
        $validated = $request->validate([
            'type' => 'required|string|max:100|unique:mm_entity_types,type,' . $entityType->id,
        ]);

        $entityType->update($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'entityType' => $entityType,
                'message' => 'Entity Type updated successfully.'
            ]);
        }

        return redirect()->route('entitytypes.index')->with('success', 'Entity Type updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(EntityType $entityType)
    {
        $this->authorizeModule('delete');
        $entityType->delete();

        if (request()->wantsJson()) {
            return response()->json([
                'message' => 'Entity Type deleted successfully.'
            ]);
        }

        return redirect()->route('entitytypes.index')->with('success', 'Entity Type deleted successfully.');
    }
}
