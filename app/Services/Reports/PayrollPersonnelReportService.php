<?php

namespace App\Services\Reports;

use App\Models\Personnel;
use App\Models\Payslip;
use App\Services\PlantContextService;
use Carbon\Carbon;

class PayrollPersonnelReportService implements ReportServiceInterface
{
    public function __construct(private readonly PlantContextService $ctx) {}

    public function generate(array $params): array
    {
        $plantId = $this->ctx->requirePlantId();
        $employeeId = $params['employee_id'] ?? null;
        $start = $params['start'] ?? null;
        $end = $params['end'] ?? null;

        $query = Personnel::where(function ($q) use ($plantId) {
                $q->where('plant_id', $plantId)->orWhereNull('plant_id');
            })
            ->whereNull('deleted_at')
            ->with(['user', 'contacts', 'department', 'designation']);

        if (!empty($employeeId)) {
            $query->where('id', $employeeId);
        }

        $personnelList = $query->orderBy('first_name')->get();

        // Build Payslips query within date range
        $payslipsQuery = Payslip::with(['payrollPeriod', 'items'])
            ->where(function ($q) use ($plantId) {
                $q->where('plant_id', $plantId)->orWhereNull('plant_id');
            })
            ->whereNull('deleted_at');

        if (!empty($employeeId)) {
            $payslipsQuery->where('personnel_id', $employeeId);
        } else {
            $payslipsQuery->whereIn('personnel_id', $personnelList->pluck('id'));
        }

        if (!empty($start) && !empty($end)) {
            $fromDate = Carbon::parse($start)->startOfDay()->toDateString();
            $toDate = Carbon::parse($end)->endOfDay()->toDateString();

            $payslipsQuery->where(function ($q) use ($fromDate, $toDate) {
                $q->whereHas('payrollPeriod', function ($pq) use ($fromDate, $toDate) {
                    $pq->where(function ($sq) use ($fromDate, $toDate) {
                        $sq->where('from_date', '<=', $toDate)
                           ->where('to_date', '>=', $fromDate);
                    });
                })->orWhere(function ($sq) use ($fromDate, $toDate) {
                    $sq->whereBetween('created_at', [$fromDate . ' 00:00:00', $toDate . ' 23:59:59']);
                });
            });
        }

        $payslipsByPersonnel = $payslipsQuery->get()->groupBy('personnel_id');

        $rows = collect();

        foreach ($personnelList as $p) {
            $matchingPayslips = $payslipsByPersonnel->get($p->id, collect());

            $empCode = $p->employee_code ?: ('EMP-' . str_pad($p->id, 4, '0', STR_PAD_LEFT));
            $empName = trim(($p->first_name ?? '') . ' ' . ($p->last_name ?? ''));
            $empType = $p->employment_type ?? 'Full-time';
            $deptName = $p->department?->name ?? 'General';
            $desigName = $p->designation?->name ?? 'Staff';
            $joiningDate = $p->joining_date ? Carbon::parse($p->joining_date)->toDateString() : 'N/A';
            $empStatus = $p->status ? (is_string($p->status) ? ucfirst($p->status) : 'Active') : 'Inactive';
            $email = $p->email ?: ($p->user?->email ?: 'N/A');
            $phone = $p->mobile ?: ($p->contacts->first()?->contact_value ?: 'N/A');

            if ($matchingPayslips->isNotEmpty()) {
                foreach ($matchingPayslips as $slip) {
                    $rows->push([
                        'id'                 => $p->id,
                        'employee_code'      => $empCode,
                        'name'               => $empName,
                        'employee_type'      => $empType,
                        'department'         => $deptName,
                        'designation'        => $desigName,
                        'joining_date'       => $joiningDate,
                        'status'             => $empStatus,
                        'email'              => $email,
                        'phone'              => $phone,
                        'has_payslip'        => true,
                        'payslip_id'         => $slip->id,
                        'payslip_no'         => $slip->payslip_no,
                        'period_name'        => $slip->payrollPeriod?->name ?? Carbon::parse($slip->created_at)->format('M Y'),
                        'cycle'              => $slip->payrollPeriod ? ($slip->payrollPeriod->from_date->format('d/m/Y') . ' - ' . $slip->payrollPeriod->to_date->format('d/m/Y')) : 'N/A',
                        'working_days'       => (float)$slip->working_days,
                        'present_days'       => (float)$slip->present_days,
                        'absent_days'        => (float)$slip->absent_days,
                        'paid_leave_days'    => (float)$slip->paid_leave_days,
                        'gross_salary'       => (float)$slip->gross_salary,
                        'total_earnings'     => (float)$slip->total_earnings,
                        'total_deductions'   => (float)$slip->total_deductions,
                        'net_salary'         => (float)$slip->net_salary,
                        'payslip_status'     => ucfirst($slip->status ?? 'draft'),
                        'payment_reference'  => $slip->payment_reference ?? '—',
                    ]);
                }
            } else {
                $rows->push([
                    'id'                 => $p->id,
                    'employee_code'      => $empCode,
                    'name'               => $empName,
                    'employee_type'      => $empType,
                    'department'         => $deptName,
                    'designation'        => $desigName,
                    'joining_date'       => $joiningDate,
                    'status'             => $empStatus,
                    'email'              => $email,
                    'phone'              => $phone,
                    'has_payslip'        => false,
                    'payslip_id'         => null,
                    'payslip_no'         => '—',
                    'period_name'        => 'Not Generated',
                    'cycle'              => '—',
                    'working_days'       => 0,
                    'present_days'       => 0,
                    'absent_days'        => 0,
                    'paid_leave_days'    => 0,
                    'gross_salary'       => 0.0,
                    'total_earnings'     => 0.0,
                    'total_deductions'   => 0.0,
                    'net_salary'         => 0.0,
                    'payslip_status'     => 'Not Generated',
                    'payment_reference'  => '—',
                ]);
            }
        }

        return [
            'transactions' => $rows->values(),
            'summary' => [
                'total_employees'        => $personnelList->count(),
                'generated_payslips'     => $rows->where('has_payslip', true)->count(),
                'total_earnings'         => (float)$rows->sum('total_earnings'),
                'total_deductions'       => (float)$rows->sum('total_deductions'),
                'total_net_salary'       => (float)$rows->sum('net_salary'),
            ],
            'opening_balance' => 0
        ];
    }

    public function targetName(array $params): string
    {
        return 'Personnel & Payroll Directory';
    }
}
