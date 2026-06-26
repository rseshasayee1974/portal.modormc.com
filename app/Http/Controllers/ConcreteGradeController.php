<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\ConcreteGrade;
use App\Models\ConcreteGradeItem;
use App\Models\Product;
use App\Models\Entity;
use App\Models\MixDesign;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use App\Http\Controllers\Concerns\AuthorizesModule;

class ConcreteGradeController extends Controller
<<<<<<< HEAD
{
    use AuthorizesModule;

    protected string $module = 'concrete_grades';
=======
{ use AuthorizesModule;
>>>>>>> 344061bb331e907ba0957b5bb1c965d316f4e9f9
  

    public function index()
    {
        $this->authorizeModule('menu');
        $plantId = session('active_plant_id');

        return Inertia::render('ConcreteGrades/Index', [
            'grades' => ConcreteGrade::where('plant_id', $plantId)
                // ->withExists(['mixDesigns as is_in_use'])
                ->with(['items.product'])
                ->latest()
                ->get(),
            'products' => Product::forPlant($plantId)->with(['category', 'unit'])->where('product_type', 'Purchase')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeModule('create');
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
        $this->authorizeModule('edit');
        $plantId = session('active_plant_id');

        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:mm_concrete_grades,name,' . $concretegrade->id . ',id,plant_id,' . $plantId,
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

            // Sync items safely instead of delete-all-and-recreate
            $newProductIds = collect($validated['items'])->pluck('product_id')->toArray();

            // Find existing items that are not in the new payload (about to be removed)
            $itemsToRemove = $concretegrade->items()->whereNotIn('product_id', $newProductIds)->get();

            foreach ($itemsToRemove as $item) {
                if ($item->is_in_use) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'items' => ["Cannot remove ingredient '" . ($item->product->title ?? 'Unknown') . "' because it is currently in use by active mix designs or batches."]
                    ]);
                }
                $item->deleted_by = Auth::id();
                $item->save();
                $item->delete();
            }

            // Upsert the remaining and new items
            foreach ($validated['items'] as $item) {
                $existing = ConcreteGradeItem::withTrashed()->where([
                    'plant_id' => $plantId,
                    'concrete_grade_id' => $concretegrade->id,
                    'product_id' => $item['product_id'],
                ])->first();

                if ($existing) {
                    if ($existing->trashed()) {
                        $existing->restore();
                    }
                    $existing->update([
                        'quantity' => $item['quantity'],
                        'updated_by' => Auth::id(),
                    ]);
                } else {
                    ConcreteGradeItem::create([
                        'plant_id' => $plantId,
                        'concrete_grade_id' => $concretegrade->id,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'created_by' => Auth::id(),
                        'updated_by' => Auth::id(),
                    ]);
                }
            }
        });

        return redirect()->back()->with('success', 'Concrete Grade master updated successfully.');
    }

    public function destroy(ConcreteGrade $concretegrade)
    {
<<<<<<< HEAD
        $this->authorizeModule('delete');
=======
         $this->authorizeModule('delete', $concretegrade);
>>>>>>> 344061bb331e907ba0957b5bb1c965d316f4e9f9
        try {
            $concretegrade->delete();
            return back()->with('success', 'Concrete Grade master deleted successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->with('error', $e->validator->errors()->first());
        }
    }
}
