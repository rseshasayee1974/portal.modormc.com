<?php

namespace App\Http\Controllers;

use App\Models\LeaveType;
use Illuminate\Http\Request;
use App\Http\Controllers\Concerns\AuthorizesModule;

class LeaveTypeController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'personnel';

    public function index()
    {
        $this->authorizeModule('view');

        $activePlantId = session('active_plant_id');
        $types = LeaveType::where('plant_id', $activePlantId)->get();

        if (request()->wantsJson()) {
            return response()->json($types);
        }

        return redirect()->route('leave-applications.index');
    }

    public function store(Request $request)
    {
        $this->authorizeModule('create');

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'is_paid' => 'boolean',
            'max_days_per_year' => 'nullable|integer|min:0',
            'carry_forward' => 'boolean',
        ]);

        $validated['plant_id'] = session('active_plant_id');

        LeaveType::create($validated);

        return redirect()->back()->with('success', 'Leave type created successfully.');
    }

    public function update(Request $request, LeaveType $leaveType)
    {
        $this->authorizeModule('edit');

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'is_paid' => 'boolean',
            'max_days_per_year' => 'nullable|integer|min:0',
            'carry_forward' => 'boolean',
        ]);

        $leaveType->update($validated);

        return redirect()->back()->with('success', 'Leave type updated successfully.');
    }

    public function destroy(LeaveType $leaveType)
    {
        $this->authorizeModule('delete');

        $leaveType->delete();

        return redirect()->back()->with('success', 'Leave type deleted successfully.');
    }
}
