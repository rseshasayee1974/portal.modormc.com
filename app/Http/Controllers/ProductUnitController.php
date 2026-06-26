<?php

namespace App\Http\Controllers;

use App\Models\ProductUnit;
use Illuminate\Http\Request;
use Inertia\Inertia;

use App\Http\Controllers\Concerns\AuthorizesModule;

class ProductUnitController extends Controller
{
    use AuthorizesModule;

    protected string $module = 'product_units';
    public function index()
    {
        $this->authorizeModule('menu');
        return Inertia::render('ProductUnits/Index', [
            'productUnits' => ProductUnit::all(),
            'unitTypes' => ['Measure','Weight', 'Volume', 'Units', 'Distance', 'Time', 'Other']
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeModule('create');
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
        $this->authorizeModule('edit');
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
        $this->authorizeModule('delete');
        $productunit->delete();
        return redirect()->back()->with('success', 'Unit deleted successfully');
    }
}
