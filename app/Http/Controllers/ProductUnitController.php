<?php

namespace App\Http\Controllers;

use App\Models\ProductUnit;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductUnitController extends Controller
{
    public function index()
    {
        return Inertia::render('ProductUnits/Index', [
            'productUnits' => ProductUnit::all(),
            'unitTypes' => ['Measure','Weight', 'Volume', 'Units', 'Distance', 'Time', 'Other']
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'unit_name' => 'required|string|max:50',
            'unit_type' => 'required|string',
            'unit_code' => 'nullable|string|max:10',
        ]);

        ProductUnit::create($validated);
        return redirect()->back()->with('success', 'Unit created successfully');
    }

    public function update(Request $request, ProductUnit $productunit)
    {
        $validated = $request->validate([
            'unit_name' => 'required|string|max:50',
            'unit_type' => 'required|string',
            'unit_code' => 'nullable|string|max:10',
        ]);

        $productunit->update($validated);
        return redirect()->back()->with('success', 'Unit updated successfully');
    }

    public function destroy(ProductUnit $productunit)
    {
        $productunit->delete();
        return redirect()->back()->with('success', 'Unit deleted successfully');
    }
}
