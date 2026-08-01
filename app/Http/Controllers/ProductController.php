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
use App\Http\Controllers\Concerns\AuthorizesModule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'product';
    public function index()
    {
        $this->authorizeModule('menu');
        $this->authorize('viewAny', Product::class);

        $plantId = session('active_plant_id');
        $products = Product::forPlant($plantId)->withDetails()->get()->map(function ($product) {
            return array_merge($product->toArray(), [
                'can_delete' => auth()->user()->can('delete', $product),
                'can_update' => auth()->user()->can('update', $product),
                // 'is_in_use' => $product->is_in_use,
            ]);
        });

        return Inertia::render('Products/Index', [
            'products' => $products,
            'categories' => ProductCategoriesDropdown(),
            'units' => Productunit(),
            'purchaseTaxes' => TaxesDropdown('purchase','GST'),
            'saleTaxes' => TaxesDropdown('sales','GST'),
            'productTypes' => ProductTypesDropdown(),
        ]);
    }


    public function store(Request $request)
    {
        $this->authorizeModule('create');
        $this->authorize('create', Product::class);

        $plantId = session('active_plant_id');
        $plant = \App\Models\Plant::findOrFail($plantId);
       // 1. Safe Early Check (Fixed potential fatal crash & scoped to active plant)
    if ($request->filled('title')) {
        $existingProduct = Product::where('title', $request->title)
            ->where('plant_id', $plantId)
            ->first();

        if ($existingProduct) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'title' => 'Product already exists.'
            ]);
        }
    }
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
            'conversion_quantity' => 'nullable|numeric|min:0',
        ]);

        Product::create(array_merge($validated, [
            'plant_id' => $plantId,
            'entity_id' => $plant->entity_id,
        ]));
        return redirect()->back()->with('success', 'Product created successfully.');
    }


public function update(Request $request, Product $product)
{
    $this->authorizeModule('edit');

    $plantId = session('active_plant_id');

    // 1. Validation Rules
    $validated = $request->validate([
        'title' => [
            'required',
            'string',
            'max:255',
            Rule::unique('mm_products', 'title')
                ->ignore($product->id)
                ->where('plant_id', $plantId),
        ],
        'code'                => 'nullable|string|max:255',
        'category_id'         => 'nullable|exists:mm_product_categories,id',
        'unit_id'             => 'nullable|exists:mm_product_units,id',
        'purchase_price'      => 'required|numeric|min:0',
        'sales_price'         => 'required|numeric|min:0',
        'status'              => 'required|boolean',
        'hsn_code'            => 'nullable|string|max:255',
        'material_code'       => 'nullable|string|max:255',
        'tax_mode'            => 'nullable|boolean',
        'purchase_tax_id'     => 'nullable|exists:mm_taxes,id',
        'sale_tax_id'         => 'nullable|exists:mm_taxes,id',
        'is_service'          => 'nullable|boolean',
        'product_type'        => 'nullable|string|max:255',
        'stock_alert'         => 'nullable|numeric|min:0',
        'conversion_quantity' => 'nullable|numeric|min:0',
    ]);

    // 2. Check for Restricted Fields when Active Mix Design/Batch exists
    if ($product->isRestrictedFromModification()) {
        
        // Define fields that CANNOT be changed by non-admins when restricted
        $restrictedFields = [
            'title', 
            'status', 
            'tax_mode', 
            'sale_tax_id',
            'purchase_tax_id', 
        ];

        $booleanFields = ['status', 'tax_mode'];
        $detectedRestrictedChanges = [];

        foreach ($validated as $key => $value) {
            // Only check fields that belong to the restricted group
            if (!in_array($key, $restrictedFields, true)) {
                continue;
            }

            $currentVal = $product->$key;

            // Normalize types for accurate comparison
            if (in_array($key, $booleanFields, true)) {
                $currentVal = (bool) $currentVal;
                $value = (bool) $value;
            } elseif (is_string($value)) {
                $currentVal = strtolower(trim((string) $currentVal));
                $value = strtolower(trim($value));
            }

            // Check if the restricted field value is actually being changed
            if ($currentVal !== $value) {
                $detectedRestrictedChanges[] = $key;
            }
        }

        // Handle attempt to modify restricted fields
        if (!empty($detectedRestrictedChanges)) {
            $isSystemAdmin = auth()->user()?->isSystemAdmin();

            // Block non-system admin users
            if (!$isSystemAdmin) {
                throw ValidationException::withMessages([
                    'product' => ['Title, Status, and Tax configurations cannot be modified because this product is used in an active mix design or batch.'],
                ]);
            }

            // Provide a warning to system admins
            $warning = 'Warning: You modified restricted fields (title/status/tax) on a product linked to an active mix design or batch. This may affect production records.';
        }
    }

    // 3. Perform Update
    $product->update($validated);

    // 4. Return Redirect with Response Messages
    $redirect = redirect()->back()->with('success', 'Product updated successfully.');

    if (isset($warning)) {
        $redirect->with('warning', $warning);
    }

    return $redirect;
}
    
    public function destroy(Product $product)
    {
        // 2. Authorization check
        $this->authorizeModule('delete');

        $isRestricted = $product->isRestrictedFromModification();
        $isSystemAdmin = auth()->user()?->isSystemAdmin();

        if ($isRestricted) {
            if (!$isSystemAdmin) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'product' => ['This product cannot be deleted because it is used in an active mix design associated with a batch.'],
                ]);
            }
            $warning = 'Warning: This product was deleted although it is currently used in an active mix design associated with a batch. This may affect production records.';
        }

        $product->delete();

        $response = redirect()->back()->with('success', 'Product deleted successfully.');
        if (isset($warning)) {
            $response->with('warning', $warning);
        }
        return $response;
    }

    public function batchStore(Request $request)
    {
        $this->authorizeModule('create');
        $this->authorize('create', Product::class);

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