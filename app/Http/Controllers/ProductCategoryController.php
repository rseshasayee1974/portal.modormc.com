<?php

namespace App\Http\Controllers;

use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ProductCategoryController extends Controller
{
    public function index()
    {
        $plantId = session('active_plant_id');
        return Inertia::render('Products/Categories', [
            'categories' => ProductCategory::where('plant_id', $plantId)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $plantId = session('active_plant_id');
        if (! $plantId) {
            return redirect()->back()->withErrors(['plant_id' => 'Select an active plant first.']);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:150|unique:mm_product_categories,name,NULL,id,plant_id,' . $plantId,
            'parent_id' => 'nullable|exists:mm_product_categories,id',
            'code' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:255',
            'status' => 'required|boolean',
        ]);

        ProductCategory::create([
            ...$validated,
            'plant_id' => $plantId,
            'sort_order' => (int) $request->input('sort_order', 0),
        ]);

        return redirect()->back()->with('success', 'Category created successfully.');
    }

    public function update(Request $request, ProductCategory $productcategory)
    {
        $plantId = session('active_plant_id');
        if (! $plantId) {
            return redirect()->back()->withErrors(['plant_id' => 'Select an active plant first.']);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:150|unique:mm_product_categories,name,' . $productcategory->id . ',id,plant_id,' . $plantId,
            'parent_id' => 'nullable|exists:mm_product_categories,id',
            'code' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:255',
            'status' => 'required|boolean',
        ]);

        $productcategory->update([
            'name' => $validated['name'],
            'parent_id' => $validated['parent_id'] ?? null,
            'code' => $validated['code'] ?? null,
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'],
        ]);

        return redirect()->back()->with('success', 'Category updated successfully.');
    }

    public function destroy(ProductCategory $productcategory)
    {
        $productcategory->delete();
        return redirect()->back()->with('success', 'Category deleted successfully.');
    }
}
