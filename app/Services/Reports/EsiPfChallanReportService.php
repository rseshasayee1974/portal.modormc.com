<?php

namespace App\Services\Reports;

use App\Models\Payslip;
use App\Models\StatutoryConfig;
use App\Services\PlantContextService;
use Carbon\Carbon;

class EsiPfChallanReportService implements ReportServiceInterface
{
    public function __construct(private readonly PlantContextService $ctx) {}

    public function generate(array $params): array
    {
        $plantId = $this->ctx->requirePlantId();
        $start   = $params['start'];
        $end     = $params['end'];

        // 1. Fetch Statutory Configurations for PF and ESI
        $pfConfig = StatutoryConfig::where('plant_id', $plantId)
            ->where('statute_name', 'like', '%Provident Fund%')
            ->first();
        
        $esiConfig = StatutoryConfig::where('plant_id', $plantId)
            ->where('statute_name', 'like', '%Employee State Insurance%')
            ->first();

        // 2. Extract rules or use statutory standard defaults
        $pfEmployeeRate = isset($pfConfig->rules['employee_rate']) ? (float)$pfConfig->rules['employee_rate'] : 12.0;
        $pfEmployerRate = isset($pfConfig->rules['employer_rate']) ? (float)$pfConfig->rules['employer_rate'] : 12.0;
        $pfCeiling      = isset($pfConfig->rules['wage_ceiling'])  ? (float)$pfConfig->rules['wage_ceiling']  : 15000.0;

        $esiEmployeeRate = isset($esiConfig->rules['employee_rate']) ? (float)$esiConfig->rules['employee_rate'] : 0.75;
        $esiEmployerRate = isset($esiConfig->rules['employer_rate']) ? (float)$esiConfig->rules['employer_rate'] : 3.25;
        $esiCeiling      = isset($esiConfig->rules['wage_ceiling'])  ? (float)$esiConfig->rules['wage_ceiling']  : 21000.0;

        // 3. Fetch payslips in the selected date range
        $payslips = Payslip::where('plant_id', $plantId)
            ->with(['personnel.department', 'personnel.designation', 'payrollPeriod', 'items'])
            ->whereHas('payrollPeriod', function ($q) use ($start, $end) {
                $q->whereBetween('from_date', [$start, $end])
                  ->orWhereBetween('to_date', [$start, $end])
                  ->or(function($sq) use ($start, $end) {
                      $sq->where('from_date', '<=', $start)
                         ->where('to_date', '>=', $end);
                  });
            })
            ->get();

        $pfRows = [];
        $esiRows = [];

        // Totals
        $pfTotals = [
            'gross_wages' => 0.0,
            'epf_wages' => 0.0,
            'eps_wages' => 0.0,
            'employee_contribution' => 0.0,
            'employer_eps_share' => 0.0,
            'employer_epf_share' => 0.0,
            'total_contribution' => 0.0,
        ];

        $esiTotals = [
            'gross_wages' => 0.0,
            'employee_contribution' => 0.0,
            'employer_contribution' => 0.0,
            'total_contribution' => 0.0,
            'days_worked' => 0.0,
        ];

        foreach ($payslips as $payslip) {
            $personnel = $payslip->personnel;
            if (!$personnel) continue;

            $name = trim(($personnel->first_name ?? '') . ' ' . ($personnel->last_name ?? ''));
            $grossWages = (float)$payslip->total_earnings;
            $daysWorked = (float)($payslip->present_days + $payslip->paid_leave_days);

            // Find Basic Salary item
            $basicItem = $payslip->items->first(function($item) {
                return $item->type === 'earning' && str_contains(strtolower($item->component_name), 'basic');
            });
            $basicWages = $basicItem ? (float)$basicItem->amount : $grossWages;

            // ─────────────────────────────────────────────────────────────
            // PROVIDENT FUND (PF)
            // ─────────────────────────────────────────────────────────────
            // Find employee PF deduction
            $pfItem = $payslip->items->first(function($item) {
                return $item->type === 'deduction' && (str_contains(strtolower($item->component_name), 'provident') || str_contains(strtolower($item->component_name), 'pf'));
            });
            $pfEmployeeShare = $pfItem ? (float)$pfItem->amount : 0.0;

            // Determine if they contribute on full basic or ceiling capped basic
            $pfLimit = $pfCeiling;
            if ($pfEmployeeShare > ($pfEmployeeRate * $pfCeiling / 100)) {
                $pfLimit = $basicWages;
            }
            $epfWages = min($basicWages, $pfLimit);
            $epsWages = min($basicWages, $pfCeiling); // EPS is strictly capped at 15000

            // If PF was deducted or the employee is eligible
            if ($pfEmployeeShare > 0 || ($basicWages > 0 && $basicWages <= $pfCeiling)) {
                $employerPfTotal = round($pfEmployerRate * $epfWages / 100, 2);
                $employerEpsShare = round(8.33 * $epsWages / 100, 2);
                $employerEpfShare = max(0.0, $employerPfTotal - $employerEpsShare);

                $pfTotalContribution = $pfEmployeeShare + $employerPfTotal;

                $pfRows[] = [
                    'employee_code'         => $personnel->employee_code,
                    'name'                  => $name,
                    'uan'                   => $personnel->uan ?? 'N/A',
                    'gross_wages'           => $grossWages,
                    'epf_wages'             => $epfWages,
                    'eps_wages'             => $epsWages,
                    'employee_contribution' => $pfEmployeeShare,
                    'employer_eps_share'   => $employerEpsShare,
                    'employer_epf_share'   => $employerEpfShare,
                    'total_contribution'    => $pfTotalContribution,
                ];

                // Aggregate totals
                $pfTotals['gross_wages'] += $grossWages;
                $pfTotals['epf_wages'] += $epfWages;
                $pfTotals['eps_wages'] += $epsWages;
                $pfTotals['employee_contribution'] += $pfEmployeeShare;
                $pfTotals['employer_eps_share'] += $employerEpsShare;
                $pfTotals['employer_epf_share'] += $employerEpfShare;
                $pfTotals['total_contribution'] += $pfTotalContribution;
            }

            // ─────────────────────────────────────────────────────────────
            // EMPLOYEE STATE INSURANCE (ESI)
            // ─────────────────────────────────────────────────────────────
            // Find employee ESI deduction
            $esiItem = $payslip->items->first(function($item) {
                return $item->type === 'deduction' && str_contains(strtolower($item->component_name), 'esi');
            });
            $esiEmployeeShare = $esiItem ? (float)$esiItem->amount : 0.0;

            // If ESI was deducted or the employee is eligible
            if ($esiEmployeeShare > 0 || ($grossWages > 0 && $grossWages <= $esiCeiling)) {
                // ESI is calculated on actual gross wages
                $employerEsiShare = round($esiEmployerRate * $grossWages / 100, 2);
                $esiTotalContribution = $esiEmployeeShare + $employerEsiShare;

                $esiRows[] = [
                    'employee_code'         => $personnel->employee_code,
                    'name'                  => $name,
                    'esi_number'            => $personnel->esi_number ?? 'N/A',
                    'days_worked'           => $daysWorked,
                    'gross_wages'           => $grossWages,
                    'employee_contribution' => $esiEmployeeShare,
                    'employer_contribution' => $employerEsiShare,
                    'total_contribution'    => $esiTotalContribution,
                ];

                // Aggregate totals
                $esiTotals['gross_wages'] += $grossWages;
                $esiTotals['employee_contribution'] += $esiEmployeeShare;
                $esiTotals['employer_contribution'] += $employerEsiShare;
                $esiTotals['total_contribution'] += $esiTotalContribution;
                $esiTotals['days_worked'] += $daysWorked;
            }
        }

        return [
            'transactions' => [
                'pf'  => $pfRows,
                'esi' => $esiRows,
            ],
            'pf'              => $pfRows,
            'esi'             => $esiRows,
            'pf_totals'       => $pfTotals,
            'esi_totals'      => $esiTotals,
            'opening_balance' => 0
        ];
    }

    public function targetName(array $params): string
    {
        return 'ESI/PF Statutory Challan';
    }
}
