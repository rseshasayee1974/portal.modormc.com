<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payslip;
use App\Models\PayrollPeriod;
use App\Models\StatutoryConfig;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use OpenApi\Attributes as OA;

class PayslipApiController extends Controller
{
    #[OA\Get(
        path: "/payslips",
        summary: "List authenticated employee's payslips",
        tags: ["Payroll"],
        security: [["bearerAuth" => []]]
    )]
    #[OA\Response(response: 200, description: "Successful operation")]
    #[OA\Response(response: 401, description: "Unauthorized")]
    public function index(Request $request)
    {
        $user = $request->user();
        $personnel = $user->personnel;

        if (!$personnel) {
            return response()->json([
                'success' => false,
                'message' => 'No employee record found for the authenticated user.',
                'data' => []
            ], 404);
        }

        $payslips = Payslip::with('payrollPeriod')
            ->where('personnel_id', $personnel->id)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $payslips
        ]);
    }

    #[OA\Get(
        path: "/payslips/{id}/pdf",
        summary: "Download PDF of a specific payslip",
        tags: ["Payroll"],
        security: [["bearerAuth" => []]]
    )]
    #[OA\Parameter(name: "id", in: "path", required: true, description: "Payslip ID")]
    #[OA\Response(response: 200, description: "Successful operation (PDF file)")]
    #[OA\Response(response: 403, description: "Forbidden")]
    #[OA\Response(response: 404, description: "Not Found")]
    public function downloadPdf(Request $request, $id)
    {
        $user = $request->user();
        $personnel = $user->personnel;
        $payslip = Payslip::with(['personnel', 'payrollPeriod', 'items'])->findOrFail($id);

        $isOwner = $personnel && $payslip->personnel_id === $personnel->id;
        $isAdmin = $user->hasRole('Saas Owner') || $user->hasRole('Platform Admin') || $user->hasRole('Super Admin');

        if (!$isOwner && !$isAdmin) {
            return response()->json(['error' => 'Unauthorized access to this payslip.'], 403);
        }

        $plant = \App\Models\Plant::with(['addresses.state', 'contacts', 'entity'])->find($payslip->plant_id);

        $pdf = Pdf::loadView('payroll.payslip', [
            'payslip' => $payslip,
            'plant' => $plant,
        ]);

        return $pdf->download('payslip-' . $payslip->payslip_no . '.pdf');
    }

    #[OA\Get(
        path: "/payroll/export-ecr",
        summary: "Export EPFO ECR text file for a payroll cycle",
        tags: ["Payroll Compliance"],
        security: [["bearerAuth" => []]]
    )]
    #[OA\Parameter(name: "payroll_period_id", in: "query", required: true, description: "Payroll Period ID")]
    #[OA\Parameter(name: "plant_id", in: "query", required: false, description: "Plant ID")]
    #[OA\Response(response: 200, description: "Successful operation (Text file)")]
    #[OA\Response(response: 400, description: "Bad Request")]
    #[OA\Response(response: 403, description: "Forbidden")]
    public function exportEcr(Request $request)
    {
        $user = $request->user();
        $isAdmin = $user->hasRole('Saas Owner') || $user->hasRole('Platform Admin') || $user->hasRole('Super Admin');
        if (!$isAdmin) {
            return response()->json(['error' => 'Unauthorized access. Compliance exports require administrative privileges.'], 403);
        }

        $request->validate([
            'payroll_period_id' => 'required|exists:mm_payroll_periods,id'
        ]);

        $activePlantId = $request->input('plant_id') ?: $user->default_plant_id;
        if (!$activePlantId) {
            return response()->json(['error' => 'No plant context specified.'], 400);
        }

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
            ->where('statute_name', 'like', '%Provident Fund%')
            ->first();
        
        $pfEmployeeRate = isset($pfConfig->rules['employee_rate']) ? (float)$pfConfig->rules['employee_rate'] : 12.0;
        $pfEmployerRate = isset($pfConfig->rules['employer_rate']) ? (float)$pfConfig->rules['employer_rate'] : 12.0;
        $pfCeiling      = isset($pfConfig->rules['wage_ceiling'])  ? (float)$pfConfig->rules['wage_ceiling']  : 15000.0;

        $lines = [];
        foreach ($payslips as $payslip) {
            $personnel = $payslip->personnel;
            if (!$personnel) continue;

            $grossWages = (float)$payslip->total_earnings;
            $basicItem = $payslip->items->first(function($item) {
                return $item->type === 'earning' && str_contains(strtolower($item->component_name), 'basic');
            });
            $basicWages = $basicItem ? (float)$basicItem->amount : $grossWages;

            $pfItem = $payslip->items->first(function($item) {
                return $item->type === 'deduction' && (str_contains(strtolower($item->component_name), 'provident') || str_contains(strtolower($item->component_name), 'pf'));
            });
            $pfEmployeeShare = $pfItem ? (float)$pfItem->amount : 0.0;

            if ($pfEmployeeShare > 0 || ($basicWages > 0 && $basicWages <= $pfCeiling)) {
                $uan = preg_replace('/[^0-9]/', '', $personnel->uan ?? '');
                
                $name = trim(($personnel->first_name ?? '') . ' ' . ($personnel->last_name ?? ''));
                $name = strtoupper(preg_replace('/[^a-zA-Z\s\.]/', '', $name));
                if (strlen($name) > 80) {
                    $name = substr($name, 0, 80);
                }

                $ncpDays = (int)$payslip->absent_days;

                $pfLimit = $pfCeiling;
                if ($pfEmployeeShare > ($pfEmployeeRate * $pfCeiling / 100)) {
                    $pfLimit = $basicWages;
                }
                $epfWages = min($basicWages, $pfLimit);
                $epsWages = min($basicWages, $pfCeiling);
                $edliWages = min($basicWages, $pfCeiling);

                $employerPfTotal = round($pfEmployerRate * $epfWages / 100, 2);
                $employerEpsShare = round(8.33 * $epsWages / 100, 2);
                $employerEpfShare = max(0.0, $employerPfTotal - $employerEpsShare);

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

    #[OA\Get(
        path: "/payroll/export-esic",
        summary: "Export ESIC Portal CSV file for a payroll cycle",
        tags: ["Payroll Compliance"],
        security: [["bearerAuth" => []]]
    )]
    #[OA\Parameter(name: "payroll_period_id", in: "query", required: true, description: "Payroll Period ID")]
    #[OA\Parameter(name: "plant_id", in: "query", required: false, description: "Plant ID")]
    #[OA\Response(response: 200, description: "Successful operation (CSV file)")]
    #[OA\Response(response: 400, description: "Bad Request")]
    #[OA\Response(response: 403, description: "Forbidden")]
    public function exportEsic(Request $request)
    {
        $user = $request->user();
        $isAdmin = $user->hasRole('Saas Owner') || $user->hasRole('Platform Admin') || $user->hasRole('Super Admin');
        if (!$isAdmin) {
            return response()->json(['error' => 'Unauthorized access. Compliance exports require administrative privileges.'], 403);
        }

        $request->validate([
            'payroll_period_id' => 'required|exists:mm_payroll_periods,id'
        ]);

        $activePlantId = $request->input('plant_id') ?: $user->default_plant_id;
        if (!$activePlantId) {
            return response()->json(['error' => 'No plant context specified.'], 400);
        }

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
            ->where('statute_name', 'like', '%Employee State Insurance%')
            ->first();
        $esiCeiling = isset($esiConfig->rules['wage_ceiling']) ? (float)$esiConfig->rules['wage_ceiling'] : 21000.0;

        $lines = [];
        foreach ($payslips as $payslip) {
            $personnel = $payslip->personnel;
            if (!$personnel) continue;

            $grossWages = (float)$payslip->total_earnings;

            $esiItem = $payslip->items->first(function($item) {
                return $item->type === 'deduction' && str_contains(strtolower($item->component_name), 'esi');
            });
            $esiEmployeeShare = $esiItem ? (float)$esiItem->amount : 0.0;

            if ($esiEmployeeShare > 0 || ($grossWages > 0 && $grossWages <= $esiCeiling)) {
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
