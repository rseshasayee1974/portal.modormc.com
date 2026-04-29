<?php

namespace App\Http\Controllers;

use App\Models\TermsCondition;
use App\Http\Requests\StoreTermsConditionRequest;
use App\Http\Requests\UpdateTermsConditionRequest;
use App\Models\Entity;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class TermsConditionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        if ($user->hasRole('Super Administrator')) {
            $allowedEntityIds = Entity::pluck('id')->toArray();
        } else {
            $allowedEntityIds = $user->entityUsers()->pluck('entity_id')->toArray();
        }

        $query = TermsCondition::query()
            ->whereIn('entity_id', $allowedEntityIds)
            ->with(['entity']);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('order_type', 'like', '%' . $request->search . '%')
                  ->orWhere('terms_condition', 'like', '%' . $request->search . '%');
            });
        }

        $sortField = $request->input('sort_field', 'id');
        $sortDirection = $request->input('sort_direction', 'desc');

        $termsConditions = $query->orderBy($sortField, $sortDirection)->paginate(10)->withQueryString();
        $entities = Entity::whereIn('id', $allowedEntityIds)->select('id', 'legal_name')->get();

        return Inertia::render('TermsConditions/Index', [
            'termsConditions' => $termsConditions,
            'filters' => $request->only(['search', 'sort_field', 'sort_direction']),
            'entities' => $entities,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTermsConditionRequest $request)
    {
        $validated = $request->validated();
        
        TermsCondition::create(array_merge($validated, [
            'created_by' => Auth::id(),
            'status' => $validated['status'] ?? 'active',
        ]));

        return redirect()->back()->with('success', 'Terms and Condition entry created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTermsConditionRequest $request, TermsCondition $termsCondition)
    {
        $validated = $request->validated();

        $termsCondition->update(array_merge($validated, [
            'updated_by' => Auth::id(),
        ]));

        return redirect()->back()->with('success', 'Terms and Condition entry updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TermsCondition $termsCondition)
    {
        // Tracker deletion fields
        $termsCondition->deleted_by = Auth::id();
        $termsCondition->save();
        $termsCondition->delete();

        return redirect()->back()->with('success', 'Terms and Condition entry deleted successfully.');
    }
}
