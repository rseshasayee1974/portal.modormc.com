<?php

namespace App\Http\Controllers;

use App\Models\PayrollPeriod;
use Illuminate\Http\Request;
use App\Http\Controllers\Concerns\AuthorizesModule;

class PayrollPeriodController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'payroll_periods';

    public function index()
    {
        $this->authorizeModule('view');

        $activePlantId = session('active_plant_id');
        $periods = PayrollPeriod::where('plant_id', $activePlantId)->get();

        if (request()->wantsJson()) {
            return response()->json($periods);
        }

        return redirect()->route('payslips.index');
    }

    public function store(Request $request)
    {
        $this->authorizeModule('create');

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'status' => 'required|in:draft,processing,completed,failed,locked',
        ]);

        $validated['plant_id'] = session('active_plant_id');
        $validated['from_date'] = date('Y-m-d', strtotime($validated['from_date']));
        $validated['to_date'] = date('Y-m-d', strtotime($validated['to_date']));

        PayrollPeriod::create($validated);

        return redirect()->back()->with('success', 'Payroll period created successfully.');
    }

    public function update(Request $request, PayrollPeriod $payrollPeriod)
    {
        $this->authorizeModule('edit');

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'status' => 'required|in:draft,processing,completed,failed,locked',
        ]);

        $validated['from_date'] = date('Y-m-d', strtotime($validated['from_date']));
        $validated['to_date'] = date('Y-m-d', strtotime($validated['to_date']));

        $payrollPeriod->update($validated);

        return redirect()->back()->with('success', 'Payroll period updated successfully.');
    }

    public function destroy(PayrollPeriod $payrollPeriod)
    {
        $this->authorizeModule('delete');

        $payrollPeriod->delete();

        return redirect()->back()->with('success', 'Payroll period deleted successfully.');
    }
}
