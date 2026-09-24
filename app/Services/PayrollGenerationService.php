<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\LeaveApplication;
use App\Models\PayrollPeriod;
use App\Models\Personnel;
use App\Models\Payslip;
use App\Models\StatutoryConfig;
use Illuminate\Support\Facades\DB;
use Exception;

class PayrollGenerationService
{
    /**
     * Generate draft payslips for active personnel in a given payroll period.
     *
     * @param int $payrollPeriodId
     * @param int $plantId
     * @param array $personnelIds
     * @return int Number of payslips generated
     * @throws Exception
     */
    public function generateForPeriod(int $payrollPeriodId, int $plantId, array $personnelIds = []): int
    {
        $period = PayrollPeriod::withoutGlobalScope('plant_id')->withTrashed()->findOrFail($payrollPeriodId);

        $query = Personnel::withoutGlobalScope('plant_id')
            ->withTrashed()
            ->with([
                'salaryStructures' => function ($q) {
                    $q->withTrashed()->with([
                        'salaryComponent' => function ($sq) {
                            $sq->withoutGlobalScope('plant_id')->withTrashed();
                        }
                    ]);
                }
            ])
            ->where('plant_id', $plantId)
            ->where('status', 'active');

        if (!empty($personnelIds)) {
            $query->whereIn('id', $personnelIds);
        }

        $personnelList = $query->get();

        if ($personnelList->isEmpty()) {
            throw new Exception('No active personnel found to generate payslips.');
        }

        $startDate = $period->from_date;
        $endDate = $period->to_date;
        $working_days = $startDate->diffInDays($endDate) + 1;

        $targetPersonnelIds = $personnelList->pluck('id')->toArray();

        // Check existing active (non-deleted) payslips in batch so soft-deleted payslips can be regenerated
        $existingPayslipPersonnelIds = Payslip::withoutGlobalScope('plant_id')
            ->where('payroll_period_id', $period->id)
            ->whereIn('personnel_id', $targetPersonnelIds)
            ->pluck('personnel_id')
            ->flip();

        // Bulk fetch ALL attendances for these employees in ONE query (fixes N+1)
        $allAttendances = Attendance::withoutGlobalScope('plant_id')
            // ->withTrashed()
            ->whereIn('personnel_id', $targetPersonnelIds)
            ->whereBetween('attendance_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get()
            ->groupBy('personnel_id');

        // Bulk fetch ALL approved leaves for these employees in ONE query (fixes N+1)
        $allLeaves = LeaveApplication::withTrashed()
            ->with(['leaveType' => function ($q) {
                $q->withTrashed();
            }])
            ->whereIn('personnel_id', $targetPersonnelIds)
            ->where('status', 'approved')
            ->where(function($q) use ($startDate, $endDate) {
                $q->whereBetween('from_date', [$startDate->toDateString(), $endDate->toDateString()])
                  ->orWhereBetween('to_date', [$startDate->toDateString(), $endDate->toDateString()])
                  ->orWhere(function($sq) use ($startDate, $endDate) {
                      $sq->where('from_date', '<=', $startDate->toDateString())
                         ->where('to_date', '>=', $endDate->toDateString());
                  });
            })
            ->get()
            ->groupBy('personnel_id');

        // Fetch statutory configurations for the plant
        $pfConfig = StatutoryConfig::withoutGlobalScope('plant_id')
            // ->withTrashed()
            ->where('plant_id', $plantId)
            ->where(function($q) {
                $q->where('code', 'EPF')->orWhere('statute_name', 'like', '%Provident Fund%');
            })->first();

        $esiConfig = StatutoryConfig::withoutGlobalScope('plant_id')
            // ->withTrashed()
            ->where('plant_id', $plantId)
            ->where(function($q) {
                $q->where('code', 'ESIC')->orWhere('statute_name', 'like', '%Employee State Insurance%');
            })->first();

        $pfDefaultEmployeeRate = isset($pfConfig->rules['employee_rate']) ? (float)$pfConfig->rules['employee_rate'] : 12.0;
        $pfWageCeiling = (isset($pfConfig->rules['wage_ceiling']) && is_numeric($pfConfig->rules['wage_ceiling']) && (float)$pfConfig->rules['wage_ceiling'] > 0)
            ? (float)$pfConfig->rules['wage_ceiling']
            : null;

        $esiDefaultEmployeeRate = isset($esiConfig->rules['employee_rate']) ? (float)$esiConfig->rules['employee_rate'] : 0.75;
        $esiWageCeiling = (isset($esiConfig->rules['wage_ceiling']) && is_numeric($esiConfig->rules['wage_ceiling']) && (float)$esiConfig->rules['wage_ceiling'] > 0)
            ? (float)$esiConfig->rules['wage_ceiling']
            : null;

        $existingCount = Payslip::withoutGlobalScope('plant_id')->where('payroll_period_id', $period->id)->count();
        $seq = $existingCount;
        $generatedCount = 0;

        DB::transaction(function () use (
            $personnelList,
            $period,
            $plantId,
            $startDate,
            $endDate,
            $working_days,
            $existingPayslipPersonnelIds,
            $allAttendances,
            $allLeaves,
            $pfDefaultEmployeeRate,
            $pfWageCeiling,
            $esiDefaultEmployeeRate,
            $esiWageCeiling,
            &$seq,
            &$generatedCount
        ) {
            foreach ($personnelList as $personnel) {
                if (isset($existingPayslipPersonnelIds[$personnel->id])) {
                    continue;
                }

                $employeeAttendances = $allAttendances->get($personnel->id, collect())
                    ->keyBy(fn($att) => $att->attendance_date->toDateString());

                $leaves = $allLeaves->get($personnel->id, collect());

                // Build leave map for the employee
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

                // If no attendance records exist at all for this employee in the period, fallback to full working days present
                if ($employeeAttendances->isEmpty() && empty($leaveMap)) {
                    $present_days = (float)$working_days;
                    $absent_days = 0.0;
                    $paid_leave_days = 0.0;
                } else {
                    $currentDate = $startDate->copy();
                    while ($currentDate->lte($endDate)) {
                        $dateStr = $currentDate->toDateString();
                        
                        // 1. Approved Leave Application takes top priority
                        if (isset($leaveMap[$dateStr])) {
                            $leaveDays = (float)$leaveMap[$dateStr]['days'];
                            $isPaid = (bool)$leaveMap[$dateStr]['is_paid'];
                            
                            if ($isPaid) {
                                $paid_leave_days += $leaveDays;
                                $uncovered = 1.0 - $leaveDays;
                                if ($uncovered > 0) {
                                    if (isset($employeeAttendances[$dateStr]) && in_array($employeeAttendances[$dateStr]->status, ['present', 'on_duty'])) {
                                        $present_days += $uncovered;
                                    } else {
                                        $absent_days += $uncovered;
                                    }
                                }
                            } else {
                                $absent_days += $leaveDays;
                                $uncovered = 1.0 - $leaveDays;
                                if ($uncovered > 0) {
                                    if (isset($employeeAttendances[$dateStr]) && in_array($employeeAttendances[$dateStr]->status, ['present', 'on_duty'])) {
                                        $present_days += $uncovered;
                                    } else {
                                        $absent_days += $uncovered;
                                    }
                                }
                            }
                        } elseif (isset($employeeAttendances[$dateStr])) {
                            // 2. Attendance status evaluation
                            $att = $employeeAttendances[$dateStr];
                            $status = $att->status;
                            
                            if (in_array($status, ['present', 'on_duty', 'weekoff', 'holiday'])) {
                                $present_days += 1.0;
                            } elseif ($status === 'half_day') {
                                $present_days += 0.5;
                                $absent_days += 0.5;
                            } elseif ($status === 'leave') {
                                $paid_leave_days += 1.0;
                            } else {
                                $absent_days += 1.0;
                            }
                        } else {
                            // 3. Date with no attendance record and no approved leave
                            if ($employeeAttendances->isEmpty()) {
                                // If company doesn't upload daily attendance, un-entered days default to present
                                $present_days += 1.0;
                            } else {
                                $absent_days += 1.0;
                            }
                        }
                        $currentDate->addDay();
                    }
                }

                $paidDays = $present_days + $paid_leave_days;
                $totalEarnings = 0.0;
                $totalDeductions = 0.0;
                $items = [];
                $calculatedBasicEarning = 0.0;

                // Split into earnings and deductions so earnings are processed first
                $earningStructures = [];
                $deductionStructures = [];

                foreach ($personnel->salaryStructures as $structure) {
                    $component = $structure->salaryComponent;
                    if (!$component) continue;

                    if ($component->type === 'earning') {
                        $earningStructures[] = $structure;
                    } else {
                        $deductionStructures[] = $structure;
                    }
                }

                // 1. Process Earnings first
                foreach ($earningStructures as $structure) {
                    $component = $structure->salaryComponent;
                    $structAmount = (float)$structure->amount;
                    $calcType = strtolower($component->calculation_type ?? 'fixed');

                    $isPercentage = ($calcType === 'percentage' || $calcType === 'percentage_of_basic' || $calcType === '%');

                    if ($isPercentage) {
                        // Rate determination: structure amount if <= 100, else default_value if <= 100
                        $rate = 0.0;
                        if ($structAmount > 0 && $structAmount <= 100) {
                            $rate = $structAmount;
                        } elseif ($component->default_value > 0 && (float)$component->default_value <= 100) {
                            $rate = (float)$component->default_value;
                        }

                        if ($rate > 0 && $calculatedBasicEarning > 0) {
                            $calculatedAmount = round(($calculatedBasicEarning * $rate) / 100, 2);
                        } else {
                            // If structAmount is a flat monthly amount > 100
                            $calculatedAmount = ($working_days > 0)
                                ? round(($structAmount * $paidDays) / $working_days, 2)
                                : $structAmount;
                        }
                    } else {
                        // Fixed earning: prorated by attendance
                        $calculatedAmount = ($working_days > 0)
                            ? round(($structAmount * $paidDays) / $working_days, 2)
                            : $structAmount;
                    }

                    $totalEarnings += $calculatedAmount;

                    // Track basic salary calculated earning for percentage-based deductions & allowance formulas
                    if (str_contains(strtolower($component->name), 'basic')) {
                        $calculatedBasicEarning = $calculatedAmount;
                    }

                    $items[] = [
                        'salary_component_id' => $component->id,
                        'component_name' => $component->name,
                        'type' => $component->type,
                        'amount' => $calculatedAmount,
                        'calculation_source' => $component->calculation_type,
                    ];
                }

                // 2. Process Deductions second
                foreach ($deductionStructures as $structure) {
                    $component = $structure->salaryComponent;
                    $structAmount = (float)$structure->amount;
                    $compNameLower = strtolower($component->name);
                    $calcType = strtolower($component->calculation_type ?? 'fixed');

                    // Statutory Deduction Logic
                    if (str_contains($compNameLower, 'provident') || str_contains($compNameLower, 'pf')) {
                        // PF Rate resolution (PF employee rate is max 25%)
                        $rate = ($structAmount > 0 && $structAmount <= 25)
                            ? $structAmount
                            : (($component->default_value > 0 && (float)$component->default_value <= 25)
                                ? (float)$component->default_value
                                : $pfDefaultEmployeeRate);

                        // PF is calculated on basic wages (subject to wage ceiling if dynamically configured)
                        $epfWages = ($pfWageCeiling !== null && $pfWageCeiling > 0)
                            ? min($calculatedBasicEarning, $pfWageCeiling)
                            : $calculatedBasicEarning;

                        $calculatedAmount = round(($epfWages * $rate) / 100, 2);
                    } elseif (str_contains($compNameLower, 'esi')) {
                        // ESI Rate resolution (ESI employee rate is max 15%)
                        $rate = ($structAmount > 0 && $structAmount <= 15)
                            ? $structAmount
                            : (($component->default_value > 0 && (float)$component->default_value <= 15)
                                ? (float)$component->default_value
                                : $esiDefaultEmployeeRate);

                        // ESI applies only if total monthly gross earnings are within statutory wage ceiling (if dynamically configured)
                        if ($esiWageCeiling !== null && $esiWageCeiling > 0) {
                            $monthlyGrossEstimate = ($paidDays > 0)
                                ? ($totalEarnings * $working_days) / $paidDays
                                : $totalEarnings;

                            if ($monthlyGrossEstimate <= $esiWageCeiling || $totalEarnings <= $esiWageCeiling) {
                                $calculatedAmount = round(($totalEarnings * $rate) / 100, 2);
                            } else {
                                $calculatedAmount = 0.0;
                            }
                        } else {
                            $calculatedAmount = round(($totalEarnings * $rate) / 100, 2);
                        }
                    } else {
                        // Non-statutory deduction
                        $isPercentage = ($calcType === 'percentage' || $calcType === 'percentage_of_basic' || $calcType === '%');

                        if ($isPercentage) {
                            $rate = ($structAmount > 0 && $structAmount <= 100)
                                ? $structAmount
                                : (float)$component->default_value;

                            $calculatedAmount = round(($calculatedBasicEarning * $rate) / 100, 2);
                        } else {
                            // Fixed deduction (flat repayment or fixed amount)
                            // If structAmount <= remainingNet, take structAmount; else cap at remainingNet to prevent negative paycheck
                            $remainingNet = max(0.0, $totalEarnings - $totalDeductions);
                            $calculatedAmount = min($structAmount, $remainingNet);
                        }
                    }

                    $totalDeductions += $calculatedAmount;

                    $items[] = [
                        'salary_component_id' => $component->id,
                        'component_name' => $component->name,
                        'type' => $component->type,
                        'amount' => $calculatedAmount,
                        'calculation_source' => $component->calculation_type,
                    ];
                }

                $netSalary = max(0.0, round($totalEarnings - $totalDeductions, 2));

                $seq++;
                $formattedSeq = str_pad($seq, 4, '0', STR_PAD_LEFT);
                $payslipNo = 'PAY-' . $period->id . '-' . $formattedSeq;

                while (Payslip::withoutGlobalScope('plant_id')->withTrashed()->where('payslip_no', $payslipNo)->exists()) {
                    $seq++;
                    $formattedSeq = str_pad($seq, 4, '0', STR_PAD_LEFT);
                    $payslipNo = 'PAY-' . $period->id . '-' . $formattedSeq;
                }

                $payslip = Payslip::create([
                    'plant_id' => $plantId,
                    'payroll_period_id' => $period->id,
                    'personnel_id' => $personnel->id,
                    'payslip_no' => $payslipNo,
                    'working_days' => $working_days,
                    'present_days' => $present_days,
                    'absent_days' => $absent_days,
                    'paid_leave_days' => $paid_leave_days,
                    'gross_salary' => round($totalEarnings, 2),
                    'total_earnings' => round($totalEarnings, 2),
                    'total_deductions' => round($totalDeductions, 2),
                    'net_salary' => $netSalary,
                    'status' => 'draft',
                ]);

                foreach ($items as $item) {
                    $payslip->items()->create($item);
                }

                $generatedCount++;
            }
        });

        return $generatedCount;
    }
}
