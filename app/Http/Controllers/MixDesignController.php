<?php

namespace App\Http\Controllers;

use App\Models\MixDesign;
use App\Models\MixDesignItem;
use App\Models\CustomSetting;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Patron;
use App\Models\Entity;
use App\Models\ConcreteGrade;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MixDesignController extends Controller
{
    public function index()
    {
        $plantId = session('active_plant_id');
        $units = Productunit();
        $defaultUomId = $this->resolveDefaultUomId($plantId, $units);

        return Inertia::render('MixDesigns/Index', [
            'mixDesigns' => MixDesign::where('plant_id', '=', $plantId)
                ->with(['partner', 'items.product', 'items.uom', 'unit'])
                ->latest()
                ->get(),
            'partners'  => PatronsDropdown($plantId,['Customer']),
            // 'sites'    => SitesDropdown($plantId),
            'products' => ProductsDropdown($plantId),
            // 'taxes'    => TaxesDropdown($plantId, 'Sales', ['GST', 'IGST']),
            // 'vehicles' => MachinesDropdown($plantId),
            // 'drivers'  => PersonnelDropdown($plantId),
            'units' => $units,
            'defaultUomId' => $defaultUomId,
            'designTypes' => ConcreteGrade::where('plant_id', '=', $plantId)->select(['id', 'name'])->get(),
        ]);
    }

    public function store(Request $request)
    {
        $plantId = session('active_plant_id');

        $validated = $request->validate([
            'partner_id' => 'required|exists:mm_patrons,id',
            'design_name' => 'required|string|max:255|unique:mm_mix_designs,design_name,NULL,id,plant_id,' . $plantId . ',partner_id,' . $request->partner_id,
            'design_code' => 'nullable|string|max:100',
            'design_type' => 'nullable|string',
            'unit_id' => 'nullable|exists:mm_product_units,id',
            'rate_per_qty' => 'nullable|numeric',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:mm_products,id',
            'items.*.uom_id' => 'required|exists:mm_product_units,id',
            'items.*.rate' => 'nullable|numeric',
            'items.*.actual_quantity' => 'required|numeric',
            'items.*.cross_quantity' => 'nullable|numeric',
            'items.*.variation_quantity' => 'nullable|numeric',
        ]);

        DB::transaction(function () use ($validated, $plantId) {
            $mixDesign = MixDesign::create([
                'plant_id' => $plantId,
                'partner_id' => $validated['partner_id'],
                'design_name' => $validated['design_name'],
                'design_code' => $validated['design_code'],
                'design_type' => $validated['design_type'],
                'unit_id' => $validated['unit_id'],
                'rate_per_qty' => $validated['rate_per_qty'],
                'created_by' => Auth::id(),
            ]);

            foreach ($validated['items'] as $item) {
                MixDesignItem::create([
                    'plant_id' => $plantId,
                    'mix_design_id' => $mixDesign->id,
                    'product_id' => $item['product_id'],
                    'uom_id' => $item['uom_id'],
                    'rate' => $item['rate'],
                    'actual_quantity' => $item['actual_quantity'],
                    'cross_quantity' => $item['cross_quantity'],
                    'variation_quantity' => $item['variation_quantity'],
                    'created_by' => Auth::id(),
                ]);
            }
        });

        return redirect()->back()->with('success', 'Mix Design created successfully.');
    }

    public function update(Request $request, MixDesign $mixdesign)
    {
        $plantId = session('active_plant_id');

        $validated = $request->validate([
            'partner_id' => 'required|exists:mm_patrons,id',
            'design_name' => 'required|string|max:255|unique:mix_designs,design_name,' . $mixdesign->id . ',id,plant_id,' . $plantId . ',partner_id,' . $request->partner_id,
            'design_code' => 'nullable|string|max:100',
            'design_type' => 'nullable|string',
            'unit_id' => 'nullable|exists:mm_product_units,id',
            'rate_per_qty' => 'nullable|numeric',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:mm_products,id',
            'items.*.uom_id' => 'required|exists:mm_product_units,id',
            'items.*.rate' => 'nullable|numeric',
            'items.*.actual_quantity' => 'required|numeric',
            'items.*.cross_quantity' => 'nullable|numeric',
            'items.*.variation_quantity' => 'nullable|numeric',
        ]);

        DB::transaction(function () use ($validated, $mixdesign, $plantId) {
            $mixdesign->update([
                'partner_id' => $validated['partner_id'],
                'design_name' => $validated['design_name'],
                'design_code' => $validated['design_code'],
                'design_type' => $validated['design_type'],
                'unit_id' => $validated['unit_id'],
                'rate_per_qty' => $validated['rate_per_qty'],
                'updated_by' => Auth::id(),
            ]);

            $mixdesign->items()->forceDelete();

            foreach ($validated['items'] as $item) {
                MixDesignItem::create([
                    'plant_id' => $plantId,
                    'mix_design_id' => $mixdesign->id,
                    'product_id' => $item['product_id'],
                    'uom_id' => $item['uom_id'],
                    'rate' => $item['rate'],
                    'actual_quantity' => $item['actual_quantity'],
                    'cross_quantity' => $item['cross_quantity'],
                    'variation_quantity' => $item['variation_quantity'],
                    'created_by' => Auth::id(),
                ]);
            }
        });

        return redirect()->back()->with('success', 'Mix Design updated successfully.');
    }

    public function destroy(MixDesign $mixdesign)
    {
        $mixdesign->delete();
        return redirect()->back()->with('success', 'Mix Design deleted successfully.');
    }

    public function getGradeIngredients($gradeId)
    {
        $plantId = session('active_plant_id');
        
        $grade = ConcreteGrade::where('plant_id', '=', $plantId)
            ->where('id', $gradeId)
            // ->where('status', '=', 'Active')
            ->whereNull('deleted_at')
            ->with(['items.product'])
            ->first();

        if (!$grade) {
            return response()->json(['items' => []]);
        }

        $items = $grade->items->map(function ($item) {
            return [
                'product_id' => $item->product_id,
                'uom_id' => $item->product->unit_id ?? null,
                'actual_quantity' => (float)$item->quantity,
                'rate' => (float)($item->product->purchase_price ?? 0),
                'cross_quantity' => (float)$item->quantity,
                'variation_quantity' => 0,
            ];
        });

        return response()->json(['items' => $items]);
    }

    private function resolveDefaultUomId(?int $plantId, $units): ?int
    {
        if (!$plantId) {
            return null;
        }

        $settings = CustomSetting::getForModule($plantId, 'mix_design');
        if (empty($settings)) {
            $settings = CustomSetting::getForModule($plantId, 'mixdesign');
        }

        $configuredDefault = (int) (
            $settings['default_uom_id']
            ?? $settings['uom_id']
            ?? $settings['default_mix_uom_id']
            ?? 0
        );

        $unitsCollection = collect($units);
        $isValidConfiguredDefault = $configuredDefault > 0
            && $unitsCollection->contains(fn ($unit) => (int) $this->unitValue($unit, 'id') === $configuredDefault);
        if ($isValidConfiguredDefault) {
            return $configuredDefault;
        }

        $cbmUnit = $unitsCollection->first(function ($unit) {
            return strtoupper((string) $this->unitValue($unit, 'unit_code')) === 'CBM';
        });
        if ($cbmUnit) {
            return (int) $this->unitValue($cbmUnit, 'id');
        }

        $firstUnit = $unitsCollection->first();
        return $firstUnit ? (int) $this->unitValue($firstUnit, 'id') : null;
    }

    private function unitValue($unit, string $key)
    {
        if (is_array($unit)) {
            return $unit[$key] ?? null;
        }

        return $unit->{$key} ?? null;
    }
}
