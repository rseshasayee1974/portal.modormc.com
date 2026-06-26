<?php

namespace App\Http\Controllers;

use App\Models\SalaryComponent;
use Illuminate\Http\Request;
use App\Http\Controllers\Concerns\AuthorizesModule;

class SalaryComponentController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'salary_components';

    public function index()
    {
        $this->authorizeModule('view');

        $activePlantId = session('active_plant_id');
        $components = SalaryComponent::where('plant_id', $activePlantId)->get();

        if (request()->wantsJson()) {
            return response()->json($components);
        }

        return redirect()->route('payslips.index');
    }

    public function store(Request $request)
    {
        $this->authorizeModule('create');

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'type' => 'required|in:earning,deduction',
            'calculation_type' => 'required|in:fixed,%,formula,attendance_based',
            'default_value' => 'required|numeric|min:0',
            'is_taxable' => 'boolean',
            'is_statutory' => 'boolean',
            'config' => 'nullable|array',
        ]);

        $validated['plant_id'] = session('active_plant_id');

        SalaryComponent::create($validated);

        return redirect()->back()->with('success', 'Salary component created successfully.');
    }

    public function update(Request $request, SalaryComponent $salaryComponent)
    {
        $this->authorizeModule('edit');

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'type' => 'required|in:earning,deduction',
            'calculation_type' => 'required|in:fixed,%,formula,attendance_based',
            'default_value' => 'required|numeric|min:0',
            'is_taxable' => 'boolean',
            'is_statutory' => 'boolean',
            'config' => 'nullable|array',
        ]);

        $salaryComponent->update($validated);

        return redirect()->back()->with('success', 'Salary component updated successfully.');
    }

    public function destroy(SalaryComponent $salaryComponent)
    {
        $this->authorizeModule('delete');

        $salaryComponent->delete();

        return redirect()->back()->with('success', 'Salary component deleted successfully.');
    }
}
