<?php

namespace App\Http\Controllers;

use App\Models\Ledger;
use App\Models\Patron;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\Reports\SalesRegisterService;
use App\Services\Reports\PurchaseRegisterService;
use App\Services\Reports\ReportServiceFactory;
use Illuminate\Support\Facades\Cache;
use App\Services\Reports\ExcelExportService;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $plantId = session('active_plant_id');
        $ledgers = Ledger::where('plant_id', $plantId)->orderBy('title')->get();
        $patrons = Patron::where('plant_id', $plantId)->orderBy('legal_name')->get();

        return Inertia::render('Reports/Index', [
            'ledgers' => $ledgers,
            'patrons' => $patrons,
            'filters' => [
                'start_date' => $request->input('start_date', now()->subDays(30)->toDateString()),
                'end_date' => $request->input('end_date', now()->toDateString()),
            ]
        ]);
    }

    public function generate(Request $request, ReportServiceFactory $factory, ExcelExportService $excelService)
    {
        $type     = $request->input('type');
        $id       = $request->input('id');
        $patronId = $request->input('patron_id');
        $start    = $request->input('start_date');
        $end      = $request->input('end_date');
        $export   = $request->input('export');

        try {
            $service = $factory->make($type);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => 'Invalid report type'], 400);
        }

        // Parse and format dates to clean YYYY-MM-DD strings to ensure compatibility with all query formats
        $startFormatted = $start ? \Carbon\Carbon::parse($start)->toDateString() : now()->subDays(30)->toDateString();
        $endFormatted   = $end ? \Carbon\Carbon::parse($end)->toDateString() : now()->toDateString();

        $params = [
            'start'            => $startFormatted,
            'end'              => $endFormatted,
            'id'               => $id,
            'patron_id'        => $patronId,
            'voucher_type'     => strtoupper($type),
            'valuation_method' => $request->input('valuation_method', 'FIFO'),
            'consolidation'    => $request->input('consolidation', 'po'),
            'plant_id'         => session('active_plant_id'),
        ];

        if ($export === 'excel' || $export === 'pdf') {
            $statusKey = 'report_export_' . \Illuminate\Support\Str::uuid();
            Cache::put($statusKey, ['status' => 'queued', 'progress' => 0], now()->addHour());

            try {
                \App\Jobs\QueueReportExportJob::dispatchSync($type, $params, $statusKey, $export);
            } catch (\Exception $e) {
                // Job already updated cache with 'failed' status; return the status_key so frontend can poll and see the error
            }

            return response()->json([
                'status'     => true,
                'queued'     => true,
                'status_key' => $statusKey,
                'message'    => 'Report generation has been queued.',
            ]);
        }

        $data       = $service->generate($params);
        return response()->json($data);
    }

    private function exportPdf($type, $targetName, $start, $end, $data, $ledgerId = null, $patronId = null, $consolidation = 'po')
    {
        $viewMap = [
            'LEDGER'               => 'reports.ledger_report',
            'PATRON'               => 'reports.patron_report',
            'SALES'                => 'reports.sales_report',
            'PURCHASE'             => 'reports.purchase_report',
            'PAYMENT'              => 'reports.payment_report',
            'RECEIPT'              => 'reports.receipt_report',
            'INVENTORY_STOCK'      => 'reports.generic_report',
            'INVENTORY_INWARD'     => 'reports.generic_report',
            'PRODUCTION_BATCH'     => 'reports.generic_report',
            'MACHINES_LIST'        => 'reports.generic_report',
            'PAYROLL_PERSONNEL'    => 'reports.generic_report',
            'SILO_STOCK_VALUATION' => 'reports.generic_report',
            'GSTR1'                => 'reports.gstr1_report',
            'GSTR3B'               => 'reports.gstr3b_report',
            'TDS_CERTIFICATE'      => 'reports.tds_certificate_report',
            'ESI_PF_CHALLAN'       => 'reports.esi_pf_challan_report',
        ];

        $view = $viewMap[strtoupper($type)] ?? 'reports.ledger_report';

        $plantId = session('active_plant_id');
        $plant   = \App\Models\Plant::with(['addresses.state', 'contacts'])->find($plantId);

        $patron = null;
        if ($patronId) {
            $patron = \App\Models\Patron::with(['addresses.state'])->find($patronId);
        } elseif ($ledgerId) {
            $patron = \App\Models\Patron::with(['addresses.state'])->where('ledger_id', $ledgerId)->first();
        }

        $extraParams = [];
        if (str_contains(strtolower($type), 'inventory_stock')) {
            $extraParams = [
                'headers'    => ['Date', 'Product Name', 'UOM', 'Opening Qty', 'Current Stock', 'Status'],
                'fields'     => ['date', 'product_name', 'uom', 'opening_qty', 'quantity', 'status'],
                'alignments' => ['center', 'left', 'center', 'right', 'right', 'center'],
                'totals'     => ['quantity' => $data['total_quantity'] ?? 0]
            ];
        } elseif (str_contains(strtolower($type), 'inventory_inward')) {
            $extraParams = [
                'headers'    => ['Received Date', 'Inward No', 'PO No', 'Supplier Name', 'Product', 'Quantity', 'Truck No'],
                'fields'     => ['date', 'inward_no', 'po_number', 'vendor_name', 'product_name', 'quantity', 'truck_no'],
                'alignments' => ['center', 'center', 'center', 'left', 'left', 'right', 'center'],
                'totals'     => ['quantity' => $data['total_quantity'] ?? 0]
            ];
        } elseif (str_contains(strtolower($type), 'production_batch')) {
            $extraParams = [
                'headers'    => ['Start Date', 'Batch No', 'Sales Order', 'Mix Design', 'Batch Size (m³)', 'Operator', 'Status'],
                'fields'     => ['date', 'batch_no', 'sales_order', 'mix_design', 'batch_size', 'operator', 'status'],
                'alignments' => ['center', 'center', 'center', 'left', 'right', 'left', 'center'],
                'totals'     => ['batch_size' => $data['total_batch_size'] ?? 0]
            ];
        } elseif (str_contains(strtolower($type), 'machines_list')) {
            $extraParams = [
                'headers'    => ['Registration', 'Vehicle Model', 'Vehicle Type', 'Make Year', 'Capacity', 'Owner'],
                'fields'     => ['registration', 'vehicle_model', 'vehicle_type', 'make_year', 'capacity', 'owner'],
                'alignments' => ['center', 'left', 'center', 'center', 'right', 'left']
            ];
        } elseif (str_contains(strtolower($type), 'payroll_personnel')) {
            $extraParams = [
                'headers'    => ['Name', 'Role / Employee Type', 'Joining Date', 'Status', 'Email', 'Phone'],
                'fields'     => ['name', 'employee_type', 'joining_date', 'status', 'email', 'phone'],
                'alignments' => ['left', 'left', 'center', 'center', 'left', 'center']
            ];
        } elseif (str_contains(strtolower($type), 'silo_stock_valuation')) {
            $extraParams = [
                'headers'    => ['Product Name', 'Category', 'UOM', 'Opening Qty', 'Opening Value', 'Inward Qty', 'Inward Value', 'Consumed Qty', 'COGS Value', 'Ending Qty', 'Ending Value', 'Avg Unit Cost'],
                'fields'     => ['product_name', 'category', 'uom', 'opening_qty', 'opening_value_formatted', 'inward_qty', 'inward_value_formatted', 'consumed_qty', 'consumed_value_formatted', 'ending_qty', 'ending_value_formatted', 'avg_unit_cost_formatted'],
                'alignments' => ['left', 'left', 'center', 'right', 'right', 'right', 'right', 'right', 'right', 'right', 'right', 'right'],
                'totals'     => [
                    'opening_value_formatted'  => $data['total_opening_value_formatted'] ?? '₹ 0',
                    'inward_value_formatted'   => $data['total_inward_value_formatted'] ?? '₹ 0',
                    'consumed_value_formatted' => $data['total_consumed_value_formatted'] ?? '₹ 0',
                    'ending_value_formatted'   => $data['total_ending_value_formatted'] ?? '₹ 0',
                ]
            ];
        }

        $pdfData = array_merge([
            'type'          => strtoupper($type),
            'target_name'   => $targetName,
            'start'         => \Carbon\Carbon::parse($start)->format('d-m-Y'),
            'end'           => \Carbon\Carbon::parse($end)->format('d-m-Y'),
            'plant'         => $plant,
            'patron'        => $patron,
            'consolidation' => $consolidation
        ], $data, $extraParams);

        $pdf = Pdf::loadView($view, $pdfData)->setPaper('a4', 'portrait');

        return $pdf->download("Report_{$type}_{$start}.pdf");
    }

    private function exportExcel($type, $start, $end, $data, ExcelExportService $excelService)
    {
        $filename = "Report_{$type}_{$start}_to_{$end}.xlsx";
        $headers  = [
            "Content-Type" => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
            "Content-Disposition" => "attachment; filename=$filename"
        ];

        $spreadsheet = $excelService->generateExcelReport($type, $start, $end, $data);

        return response()->stream(function() use ($spreadsheet) {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, $headers);
    }

    /**
     * Generate optimized Sales Register Report.
     */
    public function salesRegister(Request $request, SalesRegisterService $service)
    {
        $filters = $request->validate([
            'from_date'      => 'required|date',
            'to_date'        => 'required|date|after_or_equal:from_date',
            'branch_id'      => 'nullable|integer',
            'plant_id'       => 'nullable|integer',
            'customer_id'    => 'nullable|integer',
            'gst_type'       => 'nullable|string|in:intra,inter',
            'invoice_type'   => 'nullable|string',
            'product_id'     => 'nullable|integer',
            'salesman_id'    => 'nullable|integer',
            'payment_status' => 'nullable|string|in:paid,unpaid,partial',
            'per_page'       => 'nullable|integer|min:1|max:500',
            'export'         => 'nullable|string|in:excel,pdf',
            'refresh'        => 'nullable|boolean',
            'queue'          => 'nullable|boolean'
        ]);

        $response = $service->generate($filters);

        if ($response instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse || $response instanceof \Illuminate\Http\Response) {
            return $response;
        }

        return response()->json($response);
    }

    /**
     * Generate optimized Purchase Register Report.
     */
    public function purchaseRegister(Request $request, PurchaseRegisterService $service)
    {
        $filters = $request->validate([
            'from_date'   => 'required|date',
            'to_date'     => 'required|date|after_or_equal:from_date',
            'branch_id'   => 'nullable|integer',
            'plant_id'    => 'nullable|integer',
            'supplier_id' => 'nullable|integer',
            'gst_type'    => 'nullable|string|in:intra,inter',
            'product_id'  => 'nullable|integer',
            'per_page'    => 'nullable|integer|min:1|max:500',
            'export'      => 'nullable|string|in:excel,pdf',
            'refresh'     => 'nullable|boolean',
            'queue'       => 'nullable|boolean'
        ]);

        $response = $service->generate($filters);

        if ($response instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse || $response instanceof \Illuminate\Http\Response) {
            return $response;
        }

        return response()->json($response);
    }

    /**
     * Get queued export job status.
     */
    public function getExportStatus(string $key)
    {
        $status = Cache::get($key);

        if (!$status) {
            return response()->json([
                'status'  => 'not_found',
                'message' => 'Export job not found or expired.'
            ], 404);
        }

        return response()->json($status);
    }

    /**
     * Generate Machine Summary Report.
     */
    public function machineSummary(Request $request, \App\Services\Reports\MachineReportService $service)
    {
        $filters = $request->validate([
            'from_date' => 'required|date',
            'to_date'   => 'required|date|after_or_equal:from_date',
            'branch_id' => 'nullable|integer',
            'plant_id'  => 'nullable|integer',
            'per_page'  => 'nullable|integer|min:1|max:500',
            'export'    => 'nullable|string|in:excel,pdf',
            'refresh'   => 'nullable|boolean',
            'queue'     => 'nullable|boolean'
        ]);

        $response = $service->generateMachineSummary($filters);

        if ($response instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse || $response instanceof \Illuminate\Http\Response) {
            return $response;
        }

        return response()->json($response);
    }

    /**
     * Generate Vehicle Wise Profit & Loss Report.
     */
    public function vehiclePL(Request $request, \App\Services\Reports\MachineReportService $service)
    {
        $filters = $request->validate([
            'from_date' => 'required|date',
            'to_date'   => 'required|date|after_or_equal:from_date',
            'branch_id' => 'nullable|integer',
            'plant_id'  => 'nullable|integer',
            'per_page'  => 'nullable|integer|min:1|max:500',
            'export'    => 'nullable|string|in:excel,pdf',
            'refresh'   => 'nullable|boolean',
            'queue'     => 'nullable|boolean'
        ]);

        $response = $service->generateVehiclePL($filters);

        if ($response instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse || $response instanceof \Illuminate\Http\Response) {
            return $response;
        }
 
        return response()->json($response);
    }

    /**
     * Retrieve all active schedules for the active plant context.
     */
    public function listSchedules(Request $request)
    {
        $plantId = session('active_plant_id');
        $schedules = \App\Models\ReportSchedule::where('plant_id', $plantId)->get();
        return response()->json($schedules);
    }

    /**
     * Store a new report schedule for the active plant.
     */
    public function storeSchedule(Request $request)
    {
        $plantId = session('active_plant_id');
        $data = $request->validate([
            'report_type'      => 'required|string',
            'report_params'    => 'nullable|array',
            'email_recipients' => 'required|string',
            // 'frequency'        => 'required|string|in:daily,weekly,monthly',
            'frequency'        => ['required', 'string', Rule::in(['daily','weekly','monthly','DAILY','WEEKLY','MONTHLY','Daily','Weekly','Monthly'])],
            'schedule_time'    => 'required|string',
        ]);

        $data['plant_id'] = $plantId;
        $data['is_active'] = true;

        $schedule = \App\Models\ReportSchedule::create($data);

        return response()->json([
            'message' => 'Report schedule created successfully.',
            'schedule' => $schedule
        ]);
    }

    /**
     * Delete/cancel a report schedule.
     */
    public function deleteSchedule(\App\Models\ReportSchedule $schedule)
    {
        if ($schedule->plant_id != session('active_plant_id')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $schedule->delete();

        return response()->json([
            'message' => 'Report schedule deleted successfully.'
        ]);
    }
}
