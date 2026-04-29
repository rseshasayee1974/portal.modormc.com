<?php

namespace App\Http\Controllers;

use App\Models\Country;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesModule;

class CountryController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'countries';
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorizeModule('menu');
        return Inertia::render('Countries/Index', [
            'countries' => Country::all()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorizeModule('create');
        $validated = $request->validate([
            'country_name' => 'required|string|max:100|unique:mm_countries,country_name',
            'country_code' => 'required|string|max:10|unique:mm_countries,country_code',
            'is_active' => 'sometimes|boolean'
        ]);

        if (!isset($validated['is_active'])) {
            $validated['is_active'] = 1;
        }

        $country = Country::create($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'country' => $country,
                'message' => 'Country created successfully.'
            ]);
        }

        return redirect()->route('countries.index')->with('success', 'Country created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Country $country)
    {
        $this->authorizeModule('edit');
        $validated = $request->validate([
            'country_name' => 'required|string|max:100|unique:mm_countries,country_name,' . $country->id,
            'country_code' => 'required|string|max:10|unique:mm_countries,country_code,' . $country->id,
            'is_active' => 'sometimes|boolean'
        ]);

        $country->update($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'country' => $country,
                'message' => 'Country updated successfully.'
            ]);
        }

        return redirect()->route('countries.index')->with('success', 'Country updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Country $country)
    {
        $this->authorizeModule('delete');
        $country->delete();

        if (request()->wantsJson()) {
            return response()->json([
                'message' => 'Country deleted successfully.'
            ]);
        }

        return redirect()->route('countries.index')->with('success', 'Country deleted successfully.');
    }
}
