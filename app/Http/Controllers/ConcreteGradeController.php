<?php

namespace App\Http\Controllers;

use App\Models\ConcreteGrade;
use App\Models\ConcreteGradeItem;
use App\Models\Product;
use App\Models\Entity;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ConcreteGradeController extends Controller
{
    public function index()
    {
        $plantId = session('active_plant_id');

        return Inertia::render('ConcreteGrades/Index', [
            'grades' => ConcreteGrade::where('plant_id', $plantId)
                ->with(['items.product'])
                ->latest()
                ->get(),
            'products' => Product::forPlant($plantId)->with(['category', 'unit'])->where('product_type', 'Purchase')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $plantId = session('active_plant_id');

        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:mm_concrete_grades,name,NULL,id,plant_id,' . $plantId,
            'concrete_code' => 'nullable|string|max:50|unique:mm_concrete_grades,concrete_code,NULL,id ,plant_id,' . $plantId,
            'concrete_ratio' => 'nullable|string|max:50',
            'cement_ratio' => 'nullable|numeric',
            'sand_ratio' => 'nullable|numeric',
            'aggregate_ratio' => 'nullable|numeric',
            'status' => 'boolean',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:mm_products,id',
            'items.*.quantity' => 'required|numeric',
        ]);

        DB::transaction(function () use ($validated, $plantId) {
            $grade = ConcreteGrade::create([
                'plant_id' => $plantId,
                'name' => $validated['name'],
                'concrete_code' => $validated['concrete_code'],
                'concrete_ratio' => $validated['concrete_ratio'],
                'cement_ratio' => $validated['cement_ratio'],
                'sand_ratio' => $validated['sand_ratio'],
                'aggregate_ratio' => $validated['aggregate_ratio'],
                'status' => $validated['status'] ?? true,
                'created_by' => Auth::id(),
            ]);

            foreach ($validated['items'] as $item) {
                ConcreteGradeItem::create([
                    'plant_id' => $plantId,
                    'concrete_grade_id' => $grade->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'created_by' => Auth::id(),
                ]);
            }
        });

        return redirect()->back()->with('success', 'Concrete Grade master created successfully.');
    }

    public function update(Request $request, ConcreteGrade $concretegrade)
    {
        $plantId = session('active_plant_id');

        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:concrete_grades,name,' . $concretegrade->id . ',id,plant_id,' . $plantId,
            'concrete_code' => 'nullable|string|max:50',
            'concrete_ratio' => 'nullable|string|max:50',
            'cement_ratio' => 'nullable|numeric',
            'sand_ratio' => 'nullable|numeric',
            'aggregate_ratio' => 'nullable|numeric',
            'status' => 'boolean',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:mm_products,id',
            'items.*.quantity' => 'required|numeric',
        ]);

        DB::transaction(function () use ($validated, $concretegrade, $plantId) {
            $concretegrade->update([
                'name' => $validated['name'],
                'concrete_code' => $validated['concrete_code'],
                'concrete_ratio' => $validated['concrete_ratio'],
                'cement_ratio' => $validated['cement_ratio'],
                'sand_ratio' => $validated['sand_ratio'],
                'aggregate_ratio' => $validated['aggregate_ratio'],
                'status' => $validated['status'] ?? true,
                'updated_by' => Auth::id(),
            ]);

            $concretegrade->items()->forceDelete();

            foreach ($validated['items'] as $item) {
                ConcreteGradeItem::create([
                    'plant_id' => $plantId,
                    'concrete_grade_id' => $concretegrade->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'created_by' => Auth::id(),
                ]);
            }
        });

        return redirect()->back()->with('success', 'Concrete Grade master updated successfully.');
    }

    public function destroy(ConcreteGrade $concretegrade)
    {
        $concretegrade->delete();
        return redirect()->back()->with('success', 'Concrete Grade master deleted successfully.');
    }
}
