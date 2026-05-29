<?php

namespace App\Http\Controllers;

use App\Models\Payslip;
use App\Models\PayslipItem;
use App\Models\PayrollPeriod;
use App\Models\Personnel;
use App\Models\SalaryComponent;
use App\Models\StatutoryConfig;
use App\Models\Attendance;
use App\Models\LeaveApplication;
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

        $startDate = $period->from_date;
        $endDate = $period->to_date;
        $working_days = $startDate->diffInDays($endDate) + 1;

        DB::transaction(function () use ($personnelList, $period, $activePlantId, $startDate, $endDate, $working_days) {
            foreach ($personnelList as $personnel) {
                // Check if payslip already exists
                $existing = Payslip::where('payroll_period_id', $period->id)
                    ->where('personnel_id', $personnel->id)
                    ->first();

                if ($existing) {
                    continue;
                }

                // Query attendance records for the employee within the period
                $attendances = Attendance::where('personnel_id', $personnel->id)
                    ->whereBetween('attendance_date', [$startDate->toDateString(), $endDate->toDateString()])
                    ->get()
                    ->keyBy(fn($att) => $att->attendance_date->toDateString());

                // Query approved leave applications for the employee that overlap with the period
                $leaves = LeaveApplication::with('leaveType')
                    ->where('personnel_id', $personnel->id)
                    ->where('status', 'approved')
                    ->where(function($q) use ($startDate, $endDate) {
                        $q->whereBetween('from_date', [$startDate->toDateString(), $endDate->toDateString()])
                          ->orWhereBetween('to_date', [$startDate->toDateString(), $endDate->toDateString()])
                          ->or(function($sq) use ($startDate, $endDate) {
                              $sq->where('from_date', '<=', $startDate->toDateString())
                                 ->where('to_date', '>=', $endDate->toDateString());
                          });
                    })
                    ->get();

                // Map approved leave dates
                $leaveMap = [];
                foreach ($leaves as $leave) {
                    $from = $leave->from_date->copy();
                    $to = $leave->to_date;
                    while ($from->lte($to)) {
                        if ($from->between($startDate, $endDate)) {
                            $dateStr = $from->toDateString();
                            $daysCount = 1.0;
                            if ($leave->from_date->equalTo($leave->to_date) && $leave->days < 1) {
                                $daysCount = (float)$leave->days;
                            }
                            
                            if (isset($leaveMap[$dateStr])) {
                                $leaveMap[$dateStr]['days'] = min(1.0, $leaveMap[$dateStr]['days'] + $daysCount);
                            } else {
                                $leaveMap[$dateStr] = [
                                    'is_paid' => $leave->leaveType?->is_paid ?? false,
                                    'days' => $daysCount
                                ];
                            }
                        }
                        $from->addDay();
                    }
                }

                $present_days = 0.0;
                $absent_days = 0.0;
                $paid_leave_days = 0.0;

                if ($attendances->isEmpty() && empty($leaveMap)) {
                    // Fallback: If no attendance records and no leaves are logged, assume fully present
                    $present_days = (float)$working_days;
                    $absent_days = 0.0;
                    $paid_leave_days = 0.0;
                } else {
                    $currentDate = $startDate->copy();
                    while ($currentDate->lte($endDate)) {
                        $dateStr = $currentDate->toDateString();
                        
                        if (isset($attendances[$dateStr])) {
                            $att = $attendances[$dateStr];
                            $status = $att->status;
                            
                            if ($status === 'present' || $status === 'on_duty' || $status === 'weekoff' || $status === 'holiday') {
                                $present_days += 1.0;
                            } elseif ($status === 'absent') {
                                $absent_days += 1.0;
                            } elseif ($status === 'half_day') {
                                $present_days += 0.5;
                                if (isset($leaveMap[$dateStr])) {
                                    if ($leaveMap[$dateStr]['is_paid']) {
                                        $paid_leave_days += 0.5;
                                    } else {
                                        $absent_days += 0.5;
                                    }
                                } else {
                                    $absent_days += 0.5;
                                }
                            } elseif ($status === 'leave') {
                                if (isset($leaveMap[$dateStr])) {
                                    $leaveDays = $leaveMap[$dateStr]['days'];
                                    if ($leaveMap[$dateStr]['is_paid']) {
                                        $paid_leave_days += $leaveDays;
                                        $absent_days += (1.0 - $leaveDays);
                                    } else {
                                        $absent_days += 1.0;
                                    }
                                } else {
                                    $paid_leave_days += 1.0;
                                }
                            } else {
                                $absent_days += 1.0;
                            }
                        } else {
                            if (isset($leaveMap[$dateStr])) {
                                $leaveDays = $leaveMap[$dateStr]['days'];
                                if ($leaveMap[$dateStr]['is_paid']) {
                                    $paid_leave_days += $leaveDays;
                                    $absent_days += (1.0 - $leaveDays);
                                } else {
                                    $absent_days += 1.0;
                                }
                            } else {
                                // Sunday is default weekly off (paid rest day)
                                if ($currentDate->isSunday()) {
                                    $present_days += 1.0;
                                } else {
                                    $absent_days += 1.0;
                                }
                            }
                        }
                        $currentDate->addDay();
                    }
                }

                $totalEarnings = 0.0;
                $totalDeductions = 0.0;
                $items = [];

                foreach ($personnel->salaryStructures as $structure) {
                    $component = $structure->salaryComponent;
                    if (!$component) continue;

                    $baseAmount = (float)$structure->amount;
                    $calculatedAmount = $baseAmount;
                    
                    if ($component->type === 'earning' && $working_days > 0) {
                        $paidDays = $present_days + $paid_leave_days;
                        $calculatedAmount = ($baseAmount * $paidDays) / $working_days;
                        $calculatedAmount = round($calculatedAmount, 2);
                    }
                    
                    if ($component->type === 'earning') {
                        $totalEarnings += $calculatedAmount;
                    } else {
                        $totalDeductions += $baseAmount;
                        $calculatedAmount = $baseAmount;
                    }

                    $items[] = [
                        'salary_component_id' => $component->id,
                        'component_name' => $component->name,
                        'type' => $component->type,
                        'amount' => $calculatedAmount,
                        'calculation_source' => $component->calculation_type,
                    ];
                }

                $netSalary = max(0.0, $totalEarnings - $totalDeductions);
                $payslipNo = 'PAY-' . $period->id . '-' . $personnel->id . '-' . mt_rand(1000, 9999);

                $payslip = Payslip::create([
                    'plant_id' => $activePlantId,
                    'payroll_period_id' => $period->id,
                    'personnel_id' => $personnel->id,
                    'payslip_no' => $payslipNo,
                    'working_days' => $working_days,
                    'present_days' => $present_days,
                    'absent_days' => $absent_days,
                    'paid_leave_days' => $paid_leave_days,
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
