<?php

namespace App\Http\Controllers;

use App\Models\StateCode;
use App\Models\Country;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesModule;

class StateCodeController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'state_codes';
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorizeModule('menu');
        return Inertia::render('StateCodes/Index', [
            'stateCodes' => StateCode::all(),
            'countries' => Country::all(['id', 'country_name'])
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorizeModule('create');
        $validated = $request->validate([
            'country_id' => 'required|exists:mm_countries,id',
            'state_code' => 'required|string|max:50',
            'state_name' => 'required|string|max:100',
            'zipcode' => 'nullable|string|max:20',
            'area' => 'nullable|string|max:150',
            'district' => 'nullable|string|max:150',
        ]);

        $stateCode = StateCode::create($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'stateCode' => $stateCode,
                'message' => 'State Code created successfully.'
            ]);
        }

        return redirect()->route('statecodes.index')->with('success', 'State Code created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, StateCode $statecode)
    {
        $this->authorizeModule('edit');
        $validated = $request->validate([
            'country_id' => 'required|exists:mm_countries,id',
            'state_code' => 'required|string|max:50',
            'state_name' => 'required|string|max:100',
            'zipcode' => 'nullable|string|max:20',
            'area' => 'nullable|string|max:150',
            'district' => 'nullable|string|max:150',
        ]);

        $statecode->update($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'stateCode' => $statecode,
                'message' => 'State Code updated successfully.'
            ]);
        }

        return redirect()->route('statecodes.index')->with('success', 'State Code updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(StateCode $statecode)
    {
        $this->authorizeModule('delete');
        $statecode->delete();

        if (request()->wantsJson()) {
            return response()->json([
                'message' => 'State Code deleted successfully.'
            ]);
        }

        return redirect()->route('statecodes.index')->with('success', 'State Code deleted successfully.');
    }

    /**
     * Get unique districts for a given state.
     */
    public function getDistricts($stateId)
    {
        $state = StateCode::findOrFail($stateId);
        
        $districts = StateCode::where('state_code', $state->state_code)
            ->whereNotNull('district')
            ->where('district', '!=', '')
            ->distinct()
            ->pluck('district')
            ->toArray();

        sort($districts);

        return response()->json($districts);
    }

    /**
     * Get zipcodes and areas for a given state and district.
     */
    public function getZipcodes(Request $request, $stateId)
    {
        $state = StateCode::findOrFail($stateId);
        $district = $request->query('district');

        $locations = StateCode::where('country_id', $state->country_id)
            ->where('state_code', $state->state_code)
            ->where('district', $district)
            ->whereNotNull('zipcode')
            ->select(['zipcode', 'area'])
            ->orderBy('zipcode')
            ->orderBy('area')
            ->get();

        return response()->json($locations);
    }
}
