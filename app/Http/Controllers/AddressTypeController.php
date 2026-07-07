<?php

namespace App\Http\Controllers;

use App\Models\AddressType;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesModule;

class AddressTypeController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'address_types';
    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('AddressTypes/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorizeModule('create');
        $validated = $request->validate([
            'type' => 'required|string|max:100|unique:mm_address_types,type',
        ]);

        $addresstype = AddressType::create($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'addressType' => $addresstype,
                'message' => 'Address Type created successfully.'
            ]);
        }

        return redirect()->route('addresstypes.index')->with('success', 'Address Type created successfully.');
    }

    /**s
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorizeModule('menu');
        return Inertia::render('AddressTypes/Index', [
            'addressTypes' => AddressType::all()
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(AddressType $addresstype)
    {
        return Inertia::render('AddressTypes/Show', [
            'addresstype' => $addresstype
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(AddressType $addresstype)
    {
        return Inertia::render('AddressTypes/Edit', [
            'addresstype' => $addresstype
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, AddressType $addresstype)
    {
        $this->authorizeModule('edit');
        $validated = $request->validate([
            'type' => 'required|string|max:100|unique:mm_address_types,type,' . $addresstype->id,
        ]);

        $addresstype->update($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'addressType' => $addresstype,
                'message' => 'Address Type updated successfully.'
            ]);
        }

        return redirect()->route('addresstypes.index')->with('success', 'Address Type updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AddressType $addresstype)
    {
        $this->authorizeModule('delete');
        $addresstype->delete();

        if (request()->wantsJson()) {
            return response()->json([
                'message' => 'Address Type deleted successfully.'
            ]);
        }

        return redirect()->route('addresstypes.index')->with('success', 'Address Type deleted successfully.');
    }
}
