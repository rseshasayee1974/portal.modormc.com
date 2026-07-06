<?php

namespace App\Http\Controllers;

use App\Models\TermsCondition;
use App\Http\Requests\StoreTermsConditionRequest;
use App\Http\Requests\UpdateTermsConditionRequest;
use App\Models\Entity;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\Plant;

class TermsConditionController extends Controller
{
    use AuthorizesModule;

    protected string $module = 'terms_conditions';
    /**
     * Display a listing of the resource.
     */
 public function index(Request $request)
{
    $this->authorizeModule('menu');
    $user = Auth::user();

    $allowedEntityIds = $user->hasRole('Super Administrator')
        ? Entity::pluck('id')->toArray()
        : $user->entityUsers()->pluck('entity_id')->toArray();

    $query = TermsCondition::query()
        ->whereIn('entity_id', $allowedEntityIds)
        ->with(['entity:id,legal_name']);

    if ($request->filled('search')) {
        $query->where(function ($q) use ($request) {
            $q->where('order_type', 'like', '%' . $request->search . '%')
              ->orWhere('terms_condition', 'like', '%' . $request->search . '%');
        });
    }

    // filter terms by entity if user picked one
    $entityId = $request->input('entity_id');
    if ($entityId) {
        $query->where('entity_id', $entityId);
    }

    $sortField = $request->input('sort_field', 'id');
    $sortDirection = $request->input('sort_direction', 'desc');

    $termsConditions = $query->orderBy($sortField, $sortDirection)
        ->paginate(10)->withQueryString();

    $entities = Entity::whereIn('id', $allowedEntityIds)
        ->select('id', 'legal_name')
        ->orderBy('legal_name')
        ->get();

    // --- plants for THAT particular entity ---
    $plantsQuery = Plant::whereIn('entity_id', $allowedEntityIds)
        ->select('id', 'name', 'entity_id'); // keep entity_id!

    if ($entityId && in_array((int)$entityId, $allowedEntityIds)) {
        $plantsQuery->where('entity_id', $entityId); // only this entity
    }

    $plants = $plantsQuery->orderBy('name')->get();
// return json_encode([
//         'termsConditions' => $termsConditions,
//         'filters' => $request->only(['search', 'sort_field', 'sort_direction', 'entity_id']),
//         'entities' => $entities,
//         'plants' => $plants, // now: [{id:1,name:"Parker LLC Plant",entity_id:1}, ...]
//     ]);
    return Inertia::render('TermsConditions/Index', [
        'termsConditions' => $termsConditions,
        'filters' => $request->only(['search', 'sort_field', 'sort_direction', 'entity_id']),
        'entities' => $entities,
        'plants' => $plants, // now: [{id:1,name:"Parker LLC Plant",entity_id:1}, ...]
    ]);
}

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTermsConditionRequest $request)
    {
        $this->authorizeModule('create');
        $validated = $request->validated();
        
        $termsCondition = TermsCondition::create(array_merge($validated, [
            'created_by' => Auth::id(),
            'status' => $validated['status'] ?? 'active',
        ]));

        if ($request->wantsJson()) {
            return response()->json([
                'termsCondition' => $termsCondition,
                'message' => 'Terms and Condition entry created successfully.'
            ]);
        }

        return redirect()->back()->with('success', 'Terms and Condition entry created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTermsConditionRequest $request, TermsCondition $termscondition)
    {
        $this->authorizeModule('edit');
        $validated = $request->validated();

        $termscondition->update(array_merge($validated, [
            'updated_by' => Auth::id(),
        ]));

        if ($request->wantsJson()) {
            return response()->json([
                'termsCondition' => $termscondition,
                'message' => 'Terms and Condition entry updated successfully.'
            ]);
        }

        return redirect()->back()->with('success', 'Terms and Condition entry updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TermsCondition $termscondition)
    {
        $this->authorizeModule('delete');
        // Tracker deletion fields
        $termscondition->deleted_by = Auth::id();
        $termscondition->save();
        $termscondition->delete();

        if (request()->wantsJson()) {
            return response()->json([
                'message' => 'Terms and Condition entry deleted successfully.'
            ]);
        }

        return redirect()->back()->with('success', 'Terms and Condition entry deleted successfully.');
    }
}
