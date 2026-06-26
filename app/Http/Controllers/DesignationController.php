<?php

namespace App\Http\Controllers;

use App\Models\Designation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesModule;

class DesignationController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'designations';

    public function index()
    {
        $this->authorizeModule('menu');

        return Inertia::render('Designations/Index', [
            'designations' => Designation::orderBy('name', 'asc')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeModule('create');

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'nullable|string|max:50',
            'min_salary' => 'nullable|numeric|min:0',
            'max_salary' => 'nullable|numeric|min:0',
        ]);


        Designation::create($validated);

        return redirect()->back()->with('success', 'Designation created successfully.');
    }

    public function update(Request $request, Designation $designation)
    {
        $this->authorizeModule('edit');

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'nullable|string|max:50',
            'min_salary' => 'nullable|numeric|min:0',
            'max_salary' => 'nullable|numeric|min:0',
        ]);

        $designation->update($validated);

        return redirect()->back()->with('success', 'Designation updated successfully.');
    }

    public function destroy(Designation $designation)
    {
        $this->authorizeModule('delete');

        $designation->delete();

        return redirect()->back()->with('success', 'Designation deleted successfully.');
    }
}
