<?php

namespace App\Http\Controllers;

use App\Models\ContactType;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesModule;

class ContactTypeController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'contact_types';
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorizeModule('menu');
        return Inertia::render('ContactTypes/Index', [
            'contactTypes' => ContactType::all()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorizeModule('create');
        $validated = $request->validate([
            'type' => 'required|string|max:100|unique:mm_contact_types,type',
        ]);

        $contactType = ContactType::create($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'contactType' => $contactType,
                'message' => 'Contact Type created successfully.'
            ]);
        }

        return redirect()->route('contacttypes.index')->with('success', 'Contact Type created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ContactType $contactType)
    {
        $this->authorizeModule('edit');
        $validated = $request->validate([
            'type' => 'required|string|max:100|unique:mm_contact_types,type,' . $contactType->id,
        ]);

        $contactType->update($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'contactType' => $contactType,
                'message' => 'Contact Type updated successfully.'
            ]);
        }

        return redirect()->route('contacttypes.index')->with('success', 'Contact Type updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ContactType $contactType)
    {
        $this->authorizeModule('delete');
        $contactType->delete();

        if (request()->wantsJson()) {
            return response()->json([
                'message' => 'Contact Type deleted successfully.'
            ]);
        }

        return redirect()->route('contacttypes.index')->with('success', 'Contact Type deleted successfully.');
    }
}
