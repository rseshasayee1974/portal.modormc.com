<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use App\Models\Tax;
use App\Models\Entity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function index()
    {
        $plantId = session('active_plant_id');
        $products = Product::forPlant($plantId)->withDetails()->get();

        return Inertia::render('Products/Index', [
            'products' => $products,
            'categories' => ProductCategoriesDropdown($plantId),
            'units' => Productunit(),
            'purchaseTaxes' => TaxesDropdown($plantId, 'purchase','GST'),
            'saleTaxes' => TaxesDropdown($plantId, 'sales','GST'),
            'productTypes' => ProductTypesDropdown(),
        ]);
    }


    public function store(Request $request)
    {
        $plantId = session('active_plant_id');
        $plant = \App\Models\Plant::findOrFail($plantId);

        $validated = $request->validate([
            'title' => 'required|string|max:255|unique:mm_products,title,NULL,id,plant_id,' . $plantId,
            'code' => 'nullable|string|max:255',
            'category_id' => 'nullable|exists:mm_product_categories,id',
            'unit_id' => 'nullable|exists:mm_product_units,id',
            'purchase_price' => 'required|numeric|min:0',
            'sales_price' => 'required|numeric|min:0',
            'status' => 'required|boolean',
            'hsn_code' => 'nullable|string|max:255',
            'material_code' => 'nullable|string|max:255',
            'tax_mode' => 'nullable|boolean',
            'purchase_tax_id' => 'nullable|exists:mm_taxes,id',
            'sale_tax_id' => 'nullable|exists:mm_taxes,id',
            'is_service' => 'nullable|boolean',
            'product_type' => 'nullable|string|max:255',
            'stock_alert' => 'nullable|numeric|min:0',
            'convertsion_quantity' => 'nullable|numeric|min:0',
        ]);

        Product::create(array_merge($validated, [
            'plant_id' => $plantId,
            'entity_id' => $plant->entity_id,
        ]));
        return redirect()->back()->with('success', 'Product created successfully.');
    }

    public function update(Request $request, Product $product)
    {
        $plantId = session('active_plant_id');
        $validated = $request->validate([
            'title' => 'required|string|max:255|unique:mm_products,title,' . $product->id . ',id,plant_id,' . $plantId,
            'code' => 'nullable|string|max:255',
            'category_id' => 'nullable|exists:mm_product_categories,id',
            'unit_id' => 'nullable|exists:mm_product_units,id',
            'purchase_price' => 'required|numeric|min:0',
            'sales_price' => 'required|numeric|min:0',
            'status' => 'required|boolean',
            'hsn_code' => 'nullable|string|max:255',
            'material_code' => 'nullable|string|max:255',
            'tax_mode' => 'nullable|boolean',
            'purchase_tax_id' => 'nullable|exists:mm_taxes,id',
            'sale_tax_id' => 'nullable|exists:mm_taxes,id',
            'is_service' => 'nullable|boolean',
            'product_type' => 'nullable|string|max:255',
            'stock_alert' => 'nullable|numeric|min:0',
            'convertsion_quantity' => 'nullable|numeric|min:0',
        ]);

        $product->update($validated);
        return redirect()->back()->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return redirect()->back()->with('success', 'Product deleted successfully.');
    }

    public function batchStore(Request $request)
    {
        $plantId = session('active_plant_id');
        $plant = \App\Models\Plant::findOrFail($plantId);

        foreach ($request->products as $productData) {
            Product::create(array_merge($productData, [
                'plant_id' => $plantId,
                'entity_id' => $plant->entity_id,
                'status' => true,
            ]));
        }
        return redirect()->back()->with('success', 'Products imported successfully.');
    }
}
