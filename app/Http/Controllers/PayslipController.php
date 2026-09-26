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
use App\Services\PayrollGenerationService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Concerns\AuthorizesModule;
use Illuminate\Support\Facades\DB;

class PayslipController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'payslip';

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
            'working_days' => 'required|numeric|min:0',
            'present_days' => 'required|numeric|min:0',
            'absent_days' => 'required|numeric|min:0',
            'paid_leave_days' => 'required|numeric|min:0',
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
            'working_days' => 'required|numeric|min:0',
            'present_days' => 'required|numeric|min:0',
            'absent_days' => 'required|numeric|min:0',
            'paid_leave_days' => 'required|numeric|min:0',
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

    public function generate(Request $request, PayrollGenerationService $payrollService)
    {
        $this->authorizeModule('create');

        $request->validate([
            'payroll_period_id' => 'required|exists:mm_payroll_periods,id',
            'personnel_ids'     => 'nullable|array',
            'personnel_ids.*'   => 'exists:mm_personnels,id',
        ]);

        try {
            $payrollService->generateForPeriod(
                (int)$request->payroll_period_id,
                (int)session('active_plant_id'),
                $request->input('personnel_ids', [])
            );

            return redirect()->back()->with('success', 'Payslips generated successfully in draft status.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function show(Payslip $payslip)
    {
        $this->authorizeModule('show');

        $activePlantId = session('active_plant_id');
        if ($payslip->plant_id !== $activePlantId) {
            abort(403, 'Unauthorized access to this plant\'s payslip.');
        }

        $payslip->load(['personnel.department', 'personnel.designation', 'payrollPeriod', 'items.salaryComponent']);
        
        $plant = \App\Models\Plant::with(['addresses.state', 'contacts', 'entity'])->find($activePlantId);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('payroll.payslip', [
            'payslip' => $payslip,
            'plant' => $plant,
        ]);

        $filename = 'payslip-' . $payslip->payslip_no . '.pdf';
        
        if (request('action') === 'view') {
            return $pdf->stream($filename);
        }
        return $pdf->download($filename);
    }

    public function exportEcr(Request $request)
    {
        $this->authorizeModule('show');

        $request->validate([
            'payroll_period_id' => 'required|exists:mm_payroll_periods,id'
        ]);

        $activePlantId = session('active_plant_id');
        $period = PayrollPeriod::where('plant_id', $activePlantId)->findOrFail($request->payroll_period_id);

        $payslips = Payslip::with(['personnel', 'items'])
            ->where('payroll_period_id', $period->id)
            ->where('plant_id', $activePlantId)
            ->whereIn('status', ['approved', 'paid'])
            ->get();

        if ($payslips->isEmpty()) {
            return response('No approved or paid payslips found for this cycle.', 400);
        }

        $pfConfig = StatutoryConfig::where('plant_id', $activePlantId)
            ->where(function($q) {
                $q->where('code', 'EPF')
                  ->orWhere('statute_name', 'like', '%Provident Fund%');
            })
            ->first();
        
        $pfEmployeeRate = isset($pfConfig->rules['employee_rate']) ? (float)$pfConfig->rules['employee_rate'] : 12.0;
        $pfEmployerRate = isset($pfConfig->rules['employer_rate']) ? (float)$pfConfig->rules['employer_rate'] : 12.0;
        $pfCeiling      = (isset($pfConfig->rules['wage_ceiling']) && is_numeric($pfConfig->rules['wage_ceiling']) && (float)$pfConfig->rules['wage_ceiling'] > 0)
            ? (float)$pfConfig->rules['wage_ceiling']
            : null;

        $lines = [];
        foreach ($payslips as $payslip) {
            $personnel = $payslip->personnel;
            if (!$personnel) continue;

            // Find Basic Salary item
            $grossWages = (float)$payslip->total_earnings;
            $basicItem = $payslip->items->first(function($item) {
                return $item->type === 'earning' && str_contains(strtolower($item->component_name), 'basic');
            });
            $basicWages = $basicItem ? (float)$basicItem->amount : $grossWages;

            // Find employee PF deduction
            $pfItem = $payslip->items->first(function($item) {
                return $item->type === 'deduction' && (str_contains(strtolower($item->component_name), 'provident') || str_contains(strtolower($item->component_name), 'pf'));
            });
            $pfEmployeeShare = $pfItem ? (float)$pfItem->amount : 0.0;

            // Filter for employees contributing or eligible
            $isPfEligible = ($pfCeiling !== null && $pfCeiling > 0) ? ($basicWages <= $pfCeiling) : true;
            if ($pfEmployeeShare > 0 || ($basicWages > 0 && $isPfEligible)) {
                $uan = preg_replace('/[^0-9]/', '', $personnel->uan ?? '');
                
                $name = trim(($personnel->first_name ?? '') . ' ' . ($personnel->last_name ?? ''));
                $name = strtoupper(preg_replace('/[^a-zA-Z\s\.]/', '', $name));
                if (strlen($name) > 80) {
                    $name = substr($name, 0, 80);
                }

                $ncpDays = (int)$payslip->absent_days;

                // Determine if they contribute on full basic or ceiling capped basic
                $pfLimit = ($pfCeiling !== null && $pfCeiling > 0) ? $pfCeiling : $basicWages;
                if ($pfCeiling !== null && $pfCeiling > 0 && $pfEmployeeShare > ($pfEmployeeRate * $pfCeiling / 100)) {
                    $pfLimit = $basicWages;
                }
                $epfWages = ($pfCeiling !== null && $pfCeiling > 0) ? min($basicWages, $pfLimit) : $basicWages;
                $epsWages = ($pfCeiling !== null && $pfCeiling > 0) ? min($basicWages, $pfCeiling) : $basicWages;
                $edliWages = ($pfCeiling !== null && $pfCeiling > 0) ? min($basicWages, $pfCeiling) : $basicWages;

                $employerPfTotal = round($pfEmployerRate * $epfWages / 100, 2);
                $employerEpsShare = round(8.33 * $epsWages / 100, 2);
                $employerEpfShare = max(0.0, $employerPfTotal - $employerEpsShare);

                // Round all wage and contribution fields to nearest integer
                $grossWagesInt = (int)round($grossWages);
                $epfWagesInt = (int)round($epfWages);
                $epsWagesInt = (int)round($epsWages);
                $edliWagesInt = (int)round($edliWages);
                $epfContributionInt = (int)round($pfEmployeeShare);
                $epsContributionInt = (int)round($employerEpsShare);
                $epfEpsDiffInt = (int)round($employerEpfShare);
                $refunds = 0;
                $refundOfAdvances = 0;

                $lines[] = implode('~#~', [
                    $uan,
                    $name,
                    $grossWagesInt,
                    $epfWagesInt,
                    $epsWagesInt,
                    $edliWagesInt,
                    $epfContributionInt,
                    $epsContributionInt,
                    $epfEpsDiffInt,
                    $ncpDays,
                    $refundOfAdvances
                ]) . '~#~';
            }
        }

        if (empty($lines)) {
            return response('No eligible PF transactions found for approved/paid payslips in this cycle.', 400);
        }

        $content = implode("\r\n", $lines);
        $filename = 'ECR_' . str_replace(' ', '_', $period->name) . '.txt';

        return response($content, 200, [
            'Content-Type' => 'text/plain',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function exportEsic(Request $request)
    {
        $this->authorizeModule('show');

        $request->validate([
            'payroll_period_id' => 'required|exists:mm_payroll_periods,id'
        ]);

        $activePlantId = session('active_plant_id');
        $period = PayrollPeriod::where('plant_id', $activePlantId)->findOrFail($request->payroll_period_id);

        $payslips = Payslip::with(['personnel', 'items'])
            ->where('payroll_period_id', $period->id)
            ->where('plant_id', $activePlantId)
            ->whereIn('status', ['approved', 'paid'])
            ->get();

        if ($payslips->isEmpty()) {
            return response('No approved or paid payslips found for this cycle.', 400);
        }

        $esiConfig = StatutoryConfig::where('plant_id', $activePlantId)
            ->where(function($q) {
                $q->where('code', 'ESIC')
                  ->orWhere('statute_name', 'like', '%Employee State Insurance%');
            })
            ->first();
        $esiCeiling = (isset($esiConfig->rules['wage_ceiling']) && is_numeric($esiConfig->rules['wage_ceiling']) && (float)$esiConfig->rules['wage_ceiling'] > 0)
            ? (float)$esiConfig->rules['wage_ceiling']
            : null;

        $lines = [];
        foreach ($payslips as $payslip) {
            $personnel = $payslip->personnel;
            if (!$personnel) continue;

            $grossWages = (float)$payslip->total_earnings;

            // Find employee ESI deduction
            $esiItem = $payslip->items->first(function($item) {
                return $item->type === 'deduction' && str_contains(strtolower($item->component_name), 'esi');
            });
            $esiEmployeeShare = $esiItem ? (float)$esiItem->amount : 0.0;

            // Filter for employees contributing or eligible
            $isEsiEligible = ($esiCeiling !== null && $esiCeiling > 0) ? ($grossWages <= $esiCeiling) : true;
            if ($esiEmployeeShare > 0 || ($grossWages > 0 && $isEsiEligible)) {
                $esiNumber = preg_replace('/[^0-9]/', '', $personnel->esi_number ?? '');

                $name = trim(($personnel->first_name ?? '') . ' ' . ($personnel->last_name ?? ''));
                $name = strtoupper(preg_replace('/[^a-zA-Z\s\.]/', '', $name));
                if (strlen($name) > 80) {
                    $name = substr($name, 0, 80);
                }

                $noOfDays = (int)($payslip->present_days + $payslip->paid_leave_days);
                $totalWages = (int)round($grossWages);
                $reasonCode = 0;

                $lines[] = implode('~#~', [
                    $esiNumber,
                    $name,
                    $noOfDays,
                    $totalWages,
                    $reasonCode
                ]) . '~#~';
            }
        }

        if (empty($lines)) {
            return response('No eligible ESI transactions found for approved/paid payslips in this cycle.', 400);
        }

        $content = implode("\r\n", $lines);
        $filename = 'ESIC_' . str_replace(' ', '_', $period->name) . '.csv';

        return response($content, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
