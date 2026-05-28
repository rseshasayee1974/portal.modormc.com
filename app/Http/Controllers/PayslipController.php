<?php

namespace App\Http\Controllers;

use App\Models\Payslip;
use App\Models\PayslipItem;
use App\Models\PayrollPeriod;
use App\Models\Personnel;
use App\Models\SalaryComponent;
use App\Models\StatutoryConfig;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesModule;
use Illuminate\Support\Facades\DB;

class PayslipController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'personnel';

    public function index()
    {
        $this->authorizeModule('menu');

        $activePlantId = session('active_plant_id');

        return Inertia::render('Payroll/Index', [
            'payslips' => Payslip::with(['personnel', 'payrollPeriod', 'items'])
                ->where('plant_id', $activePlantId)
                ->latest()
                ->get(),
            'payrollPeriods' => PayrollPeriod::where('plant_id', $activePlantId)->get(),
            'personnel' => Personnel::where('plant_id', $activePlantId)->get(['id', 'first_name', 'last_name', 'employee_code']),
            'salaryComponents' => SalaryComponent::where('plant_id', $activePlantId)->get(),
            'statutoryConfigs' => StatutoryConfig::where('plant_id', $activePlantId)->get(),
            'statuses' => ['draft', 'approved', 'paid', 'rejected'],
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeModule('create');

        $validated = $request->validate([
            'payroll_period_id' => 'required|exists:mm_payroll_periods,id',
            'personnel_id' => 'required|exists:mm_personnels,id',
            'payslip_no' => 'required|string|unique:mm_payslips,payslip_no',
            'working_days' => 'required|integer|min:0',
            'present_days' => 'required|integer|min:0',
            'absent_days' => 'required|integer|min:0',
            'paid_leave_days' => 'required|integer|min:0',
            'gross_salary' => 'required|numeric|min:0',
            'total_earnings' => 'required|numeric|min:0',
            'total_deductions' => 'required|numeric|min:0',
            'net_salary' => 'required|numeric|min:0',
            'status' => 'required|in:draft,approved,paid,rejected',
            'payment_reference' => 'nullable|string',
            'items' => 'nullable|array',
            'items.*.salary_component_id' => 'nullable|exists:mm_salary_components,id',
            'items.*.component_name' => 'required|string',
            'items.*.type' => 'required|in:earning,deduction',
            'items.*.amount' => 'required|numeric|min:0',
            'items.*.calculation_source' => 'nullable|string',
        ]);

        DB::transaction(function () use ($validated) {
            $payslipData = collect($validated)->except('items')->toArray();
            $payslipData['plant_id'] = session('active_plant_id');

            $payslip = Payslip::create($payslipData);

            if (!empty($validated['items'])) {
                foreach ($validated['items'] as $item) {
                    $payslip->items()->create($item);
                }
            }
        });

        return redirect()->back()->with('success', 'Payslip created successfully.');
    }

    public function update(Request $request, Payslip $payslip)
    {
        $this->authorizeModule('edit');

        $validated = $request->validate([
            'payroll_period_id' => 'required|exists:mm_payroll_periods,id',
            'personnel_id' => 'required|exists:mm_personnels,id',
            'payslip_no' => 'required|string|unique:mm_payslips,payslip_no,' . $payslip->id,
            'working_days' => 'required|integer|min:0',
            'present_days' => 'required|integer|min:0',
            'absent_days' => 'required|integer|min:0',
            'paid_leave_days' => 'required|integer|min:0',
            'gross_salary' => 'required|numeric|min:0',
            'total_earnings' => 'required|numeric|min:0',
            'total_deductions' => 'required|numeric|min:0',
            'net_salary' => 'required|numeric|min:0',
            'status' => 'required|in:draft,approved,paid,rejected',
            'payment_reference' => 'nullable|string',
            'items' => 'nullable|array',
            'items.*.id' => 'nullable|exists:mm_payslip_items,id',
            'items.*.salary_component_id' => 'nullable|exists:mm_salary_components,id',
            'items.*.component_name' => 'required|string',
            'items.*.type' => 'required|in:earning,deduction',
            'items.*.amount' => 'required|numeric|min:0',
            'items.*.calculation_source' => 'nullable|string',
        ]);

        DB::transaction(function () use ($validated, $payslip) {
            $payslipData = collect($validated)->except('items')->toArray();
            $payslip->update($payslipData);

            if (isset($validated['items'])) {
                $itemIds = collect($validated['items'])->pluck('id')->filter()->toArray();
                $payslip->items()->whereNotIn('id', $itemIds)->delete();

                foreach ($validated['items'] as $item) {
                    if (isset($item['id'])) {
                        PayslipItem::where('id', $item['id'])->update(collect($item)->except('id')->toArray());
                    } else {
                        $payslip->items()->create($item);
                    }
                }
            }
        });

        return redirect()->back()->with('success', 'Payslip updated successfully.');
    }

    public function destroy(Payslip $payslip)
    {
        $this->authorizeModule('delete');

        $payslip->delete();

        return redirect()->back()->with('success', 'Payslip deleted successfully.');
    }

    public function generate(Request $request)
    {
        $this->authorizeModule('create');

        $request->validate([
            'payroll_period_id' => 'required|exists:mm_payroll_periods,id'
        ]);

        $period = PayrollPeriod::findOrFail($request->payroll_period_id);
        $activePlantId = session('active_plant_id');

        $personnelList = Personnel::with(['salaryStructures.salaryComponent'])
            ->where('plant_id', $activePlantId)
            ->where('status', 'active')
            ->get();

        if ($personnelList->isEmpty()) {
            return redirect()->back()->with('error', 'No active personnel found to generate payslips.');
        }

        DB::transaction(function () use ($personnelList, $period, $activePlantId) {
            foreach ($personnelList as $personnel) {
                // Check if payslip already exists
                $existing = Payslip::where('payroll_period_id', $period->id)
                    ->where('personnel_id', $personnel->id)
                    ->first();

                if ($existing) {
                    continue;
                }

                $totalEarnings = 0;
                $totalDeductions = 0;

                $items = [];
                foreach ($personnel->salaryStructures as $structure) {
                    $component = $structure->salaryComponent;
                    if (!$component) continue;

                    $amount = $structure->amount;
                    if ($component->type === 'earning') {
                        $totalEarnings += $amount;
                    } else {
                        $totalDeductions += $amount;
                    }

                    $items[] = [
                        'salary_component_id' => $component->id,
                        'component_name' => $component->name,
                        'type' => $component->type,
                        'amount' => $amount,
                        'calculation_source' => $component->calculation_type,
                    ];
                }

                $netSalary = $totalEarnings - $totalDeductions;
                $payslipNo = 'PAY-' . $period->id . '-' . $personnel->id . '-' . mt_rand(1000, 9999);

                $payslip = Payslip::create([
                    'plant_id' => $activePlantId,
                    'payroll_period_id' => $period->id,
                    'personnel_id' => $personnel->id,
                    'payslip_no' => $payslipNo,
                    'working_days' => 30, // Default values, can be computed from attendances in real scenarios
                    'present_days' => 30,
                    'absent_days' => 0,
                    'paid_leave_days' => 0,
                    'gross_salary' => $totalEarnings,
                    'total_earnings' => $totalEarnings,
                    'total_deductions' => $totalDeductions,
                    'net_salary' => $netSalary,
                    'status' => 'draft',
                ]);

                foreach ($items as $item) {
                    $payslip->items()->create($item);
                }
            }
        });

        return redirect()->back()->with('success', 'Payslips generated successfully in draft status.');
    }
}
