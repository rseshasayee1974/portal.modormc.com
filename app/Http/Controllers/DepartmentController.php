<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Personnel;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesModule;

class DepartmentController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'departments';

    public function index()
    {
        $this->authorizeModule('menu');

        return Inertia::render('Departments/Index', [
            'departments' => Department::orderBy('name', 'asc')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeModule('create');

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'nullable|string|max:50'
        ]);


        Department::create($validated);

        return redirect()->back()->with('success', 'Department created successfully.');
    }

    public function update(Request $request, Department $department)
    {
        $this->authorizeModule('edit');

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'nullable|string|max:50',
        ]);

        $department->update($validated);

        return redirect()->back()->with('success', 'Department updated successfully.');
    }

    public function destroy(Department $department)
    {
        $this->authorizeModule('delete');

        $department->delete();

        return redirect()->back()->with('success', 'Department deleted successfully.');
    }
}