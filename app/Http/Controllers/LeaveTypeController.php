<?php

namespace App\Http\Controllers;

use App\Models\LeaveType;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesModule;

class LeaveTypeController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'leave_types';

    public function index()
    {
        $this->authorizeModule('view');

        $types = LeaveType::orderBy('name', 'asc')->get();

        if (request()->wantsJson()) {
            return response()->json($types);
        }

        return Inertia::render('LeaveTypes/Index', [
            'leaveTypes' => $types,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeModule('create');

        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:mm_leave_types,name',
            'is_paid' => 'boolean',
            'max_days_per_year' => 'nullable|integer|min:0',
            'carry_forward' => 'boolean',
        ]);

        LeaveType::create($validated);

        return redirect()->back()->with('success', 'Leave type created successfully.');
    }

    public function update(Request $request, LeaveType $leaveType)
    {
        $this->authorizeModule('edit');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', \Illuminate\Validation\Rule::unique('mm_leave_types', 'name')->ignore($leaveType->id)],
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
