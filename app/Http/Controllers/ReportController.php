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
use App\Services\Reports\ReportPermissions;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Concerns\AuthorizesModule;

class ReportController extends Controller
{
    use AuthorizesModule;

    protected string $module = 'report';

    protected function authorizeReport(string $type, string $action = 'view', array $params = []): void
    {
        app(ReportPermissions::class)->authorize($type, $action, $params);
    }

    public function index(Request $request)
    {
        $reportPermissions = app(ReportPermissions::class)->matrix();
        if (!$request->filled('type')) {
            $first = array_key_first(array_filter($reportPermissions, fn ($actions) => $actions['view']));
            abort_unless($first, 403, 'No reports have been assigned to your role.');
            $request->merge(['type' => $first === 'detailed_sales_register' ? 'sales_register' : $first,
                'register_view' => $first === 'detailed_sales_register' ? 'detail' : 'summary']);
        }
        $this->authorizeReport($request->input('type'), 'view', $request->all() + ['register_view' => 'summary']);
        $plantId = session('active_plant_id');
        $ledgers = Ledger::where('plant_id', $plantId)->orderBy('title')->whereNull('deleted_at')->get();
        $patrons = Patron::where('plant_id', $plantId)->orderBy('legal_name')->whereNull('deleted_at')->get();
        $machines = MachinesDropdown();
        $drivers = \App\Models\Personnel::where(function ($q) use ($plantId) {
            $q->where('plant_id', $plantId)->orWhereNull('plant_id');
        })->whereNull('deleted_at')
          ->orderBy('first_name')
          ->get(['id', 'first_name', 'last_name', 'employee_code'])
          ->map(fn($p) => [
              'id'   => $p->id,
              'name' => trim($p->first_name . ' ' . $p->last_name) . ($p->employee_code ? " ({$p->employee_code})" : '')
          ]);

        $salesExecutives = \App\Models\Personnel::where(function ($q) use ($plantId) {
            $q->where('plant_id', $plantId)->orWhereNull('plant_id');
        })->whereNull('deleted_at')
          ->orderBy('first_name')
          ->get(['id', 'first_name', 'last_name', 'employee_code'])
          ->map(fn($p) => [
              'id'   => $p->id,
              'name' => trim($p->first_name . ' ' . $p->last_name) . ($p->employee_code ? " ({$p->employee_code})" : '')
          ]);

        $concreteGrades = \App\Models\ConcreteGrade::where(function ($q) use ($plantId) {
            $q->where('plant_id', $plantId)->orWhereNull('plant_id');
        })->whereNull('deleted_at')
          ->orderBy('name')
          ->get(['id', 'name', 'concrete_code']);

        $mixDesigns = \App\Models\MixDesign::where(function ($q) use ($plantId) {
            $q->where('plant_id', $plantId)->orWhereNull('plant_id');
        })->whereNull('deleted_at')
          ->orderBy('design_name')
          ->get(['id', 'design_name', 'design_code']);

        $employees = \App\Models\Personnel::where(function ($q) use ($plantId) {
            $q->where('plant_id', $plantId)->orWhereNull('plant_id');
        })->whereNull('deleted_at')
          ->orderBy('first_name')
          ->get(['id', 'first_name', 'last_name', 'employee_code'])
          ->map(fn($p) => [
              'id'   => $p->id,
              'name' => trim($p->first_name . ' ' . $p->last_name) . ($p->employee_code ? " ({$p->employee_code})" : '')
          ]);

        $payrollPeriods = \App\Models\PayrollPeriod::where(function ($q) use ($plantId) {
            $q->where('plant_id', $plantId)->orWhereNull('plant_id');
        })->whereNull('deleted_at')
          ->orderByDesc('from_date')
          ->get(['id', 'name', 'from_date', 'to_date']);

        return Inertia::render('Reports/Index', [
            'ledgers'          => $ledgers,
            'patrons'          => $patrons,
            'machines'         => $machines,
            'drivers'          => $drivers,
            'salesExecutives'  => $salesExecutives,
            'employees'        => $employees,
            'payrollPeriods'   => $payrollPeriods,
            'concreteGrades'   => $concreteGrades,
            'mixDesigns'       => $mixDesigns,
            'reportPermissions' => $reportPermissions,
            'filters' => [
                'type'       => $request->input('type'),
                'module'     => $request->input('module'),
                'register_view' => $request->input('register_view', 'summary'),
                'start_date' => $request->input('start_date', now()->subDays(30)->startOfDay()->format('Y-m-d H:i:s')),
                'end_date'   => $request->input('end_date', now()->endOfDay()->format('Y-m-d H:i:s')),
            ]
        ]);
    }

    public function generate(Request $request, ReportServiceFactory $factory, ExcelExportService $excelService)
    {
        $type = strtolower((string) $request->input('type'));
        $this->authorizeReport($type, $request->filled('export') ? 'export' : 'view', $request->all());
        try {
            $export   = $request->input('export');
            $type     = $request->input('type');
            $id       = $request->input('id');
            $patronId = $request->input('patron_id');
            $start    = $request->input('start_date');
            $end      = $request->input('end_date');

            try {
                $service = $factory->make($type);
            } catch (\InvalidArgumentException $e) {
                return response()->json(['error' => 'Invalid report type: ' . $type], 400);
            }

            // Parse and format dates to full datetime strings (preserve time if provided)
            $startFormatted = $start 
                ? (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($start)) 
                    ? \Carbon\Carbon::parse($start)->startOfDay()->toDateTimeString() 
                    : \Carbon\Carbon::parse($start)->toDateTimeString())
                : now()->startOfYear()->startOfDay()->toDateTimeString();

            $endFormatted = $end 
                ? (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($end)) 
                    ? \Carbon\Carbon::parse($end)->endOfDay()->toDateTimeString() 
                    : \Carbon\Carbon::parse($end)->toDateTimeString())
                : now()->endOfDay()->toDateTimeString();

            $params = [
                'start'               => $startFormatted,
                'end'                 => $endFormatted,
                'id'                  => $id,
                'patron_id'           => $patronId,
                'voucher_type'        => strtoupper($type),
                'valuation_method'    => $request->input('valuation_method', 'FIFO'),
                'consolidation'       => $request->input('consolidation', 'po'),
                'plant_id'            => session('active_plant_id'),
                'truck_id'            => $request->input('truck_id'),
                'driver_id'           => $request->input('driver_id'),
                'sales_executive_id'  => $request->input('sales_executive_id'),
                'employee_id'         => $request->input('employee_id') ?? $request->input('personnel_id'),
                'month'               => $request->input('month'),
                'grade_id'            => $request->input('grade_id'),
                'mix_design_id'       => $request->input('mix_design_id'),
                'voucher_type_filter' => $request->input('voucher_type_filter'),
                'register_view'       => $request->input('register_view', 'detail'),
                'document_status'     => $request->input('document_status', 'active'),
            ];

            if ($export === 'excel' || $export === 'pdf') {
                $statusKey = 'report_export_' . \Illuminate\Support\Str::uuid();
                Cache::put($statusKey, ['status' => 'queued', 'progress' => 0], now()->addHour());

                try {
                    \App\Jobs\QueueReportExportJob::dispatchExport($type, $params, $statusKey, $export);
                } catch (\Exception $e) {
                    // Job already updated cache with 'failed' status; return the status_key so frontend can poll and see the error
                }

                return response()->json([
                    'status'     => true,
                    'queued'     => true,
                    'status_key' => $statusKey,
                    'export'     => Cache::get($statusKey),
                    'message'    => 'Report generation has been queued.',
                ]);
            }

            $data = $service->generate($params);
            return response()->json($data);
        } catch (\Throwable $e) {
            $logMsg = date('Y-m-d H:i:s') . " Report Error [{$request->input('type')}]: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n" . $e->getTraceAsString() . "\n\n";
            @file_put_contents(storage_path('logs/report_error.log'), $logMsg, FILE_APPEND);
            \Illuminate\Support\Facades\Log::error("Report generation failed: " . $e->getMessage(), [
                'type'   => $request->input('type'),
                'file'   => $e->getFile(),
                'line'   => $e->getLine(),
                'trace'  => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error'   => $e->getMessage(),
                'message' => $e->getMessage(),
                'file'    => basename($e->getFile()) . ':' . $e->getLine(),
            ], 500);
        }
    }

    private function exportPdf($type, $targetName, $start, $end, $data, $ledgerId = null, $patronId = null, $consolidation = 'po')
    {
        $viewMap = [
            'LEDGER'                    => 'reports.ledger_report',
            'PATRON'                    => 'reports.patron_report',
            'SALES'                     => 'reports.sales_report',
            'PRODUCT_CONSOLIDATED'      => 'reports.product_consolidated_report',
            'CUSTOMER_CONSOLIDATED'     => 'reports.customer_consolidated_report',
            'CUSTOMER_OUTSTANDING'      => 'reports.customer_outstanding_report',
            'OVERALL'                   => 'reports.overall_report',
            'TRUCK_CONSOLIDATED'        => 'reports.truck_consolidated_report',
            'SITE_CONSOLIDATED'         => 'reports.site_consolidated_report',
            'PAYMENT_MODE_CONSOLIDATED' => 'reports.payment_mode_consolidated_report',
            'SALES_EXECUTIVE'           => 'reports.sales_executive_report',
            'DRIVER'                    => 'reports.driver_report',
            'CANCELLED_DISPATCH'        => 'reports.cancelled_dispatch_report',
            'PURCHASE'                  => 'reports.purchase_report',
            'PAYMENT'              => 'reports.payment_report',
            'RECEIPT'              => 'reports.receipt_report',
            'INVENTORY_STOCK'      => 'reports.generic_report',
            'INVENTORY_INWARD'     => 'reports.generic_report',
            'PRODUCTION_BATCH'     => 'reports.generic_report',
            'MACHINES_LIST'        => 'reports.generic_report',
            'MACHINE_TRACKER'     => 'reports.generic_report',
            'PAYROLL_PERSONNEL'    => 'reports.generic_report',
            'SILO_STOCK_VALUATION' => 'reports.generic_report',
            'GSTR1'                => 'reports.gstr1_report',
            'GSTR3B'               => 'reports.gstr3b_report',
            'TDS_CERTIFICATE'      => 'reports.tds_certificate_report',
            'ESI_PF_CHALLAN'       => 'reports.esi_pf_challan_report',
            'DELETED'              => 'reports.deleted_report',
            'DELETED_REPORT'       => 'reports.deleted_report',
        ];

        $view = $viewMap[strtoupper($type)] ?? 'reports.ledger_report';

        $plantId = session('active_plant_id');
        $plant   = \App\Models\Plant::with(['addresses.state', 'contacts'])->whereNull('deleted_at')->find($plantId);

        $patron = null;
        if ($patronId) {
            $patron = \App\Models\Patron::with(['addresses.state'])->whereNull('deleted_at')->find($patronId);
        } elseif ($ledgerId) {
            $patron = \App\Models\Patron::with(['addresses.state'])->where(fn ($q) => $q->where('debit_ledger_id', $ledgerId)->orWhere('credit_ledger_id', $ledgerId))->whereNull('deleted_at')->first();
        }

        $extraParams = [];
        if (str_contains(strtolower($type), 'inventory_stock')) {
            $extraParams = [
                'headers'    => ['Date', 'Product Name', 'UOM', 'Opening Qty', 'Current Stock'],
                'fields'     => ['date', 'product_name', 'uom', 'opening_qty', 'quantity'],
                'alignments' => ['center', 'left', 'center', 'right', 'right'],
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
        } elseif (str_contains(strtolower($type), 'machine_tracker')) {
            $extraParams = [
                'headers'    => ['Date', 'Machine / Vehicle', 'Shift', 'Operator', 'Odometer (KM | KM/L)', 'Hourmeter (Hrs | Hrs/L)', 'EB Units', 'Fuel (L)', 'Fuel Cost (₹)'],
                'fields'     => ['date', 'machine_registration', 'shift_label', 'operator_name', 'odometer_display', 'hourmeter_display', 'eb_display', 'fuel', 'fuel_amount'],
                'alignments' => ['center', 'left', 'center', 'left', 'center', 'center', 'center', 'right', 'right'],
                'totals'     => [
                    'fuel'        => $data['total_fuel_liters'] ?? 0,
                    'fuel_amount' => $data['total_fuel_amount'] ?? 0,
                ]
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

        $startLabel = $start ? (str_contains($start, ':') ? \Carbon\Carbon::parse($start)->format('d-m-Y H:i') : \Carbon\Carbon::parse($start)->format('d-m-Y')) : '';
        $endLabel   = $end ? (str_contains($end, ':') ? \Carbon\Carbon::parse($end)->format('d-m-Y H:i') : \Carbon\Carbon::parse($end)->format('d-m-Y')) : '';

        $orientation = 'portrait';
        if (in_array(strtoupper($type), ['SILO_STOCK_VALUATION', 'GSTR1', 'GSTR3B', 'PRODUCT_CONSOLIDATED', 'CUSTOMER_CONSOLIDATED', 'TRUCK_CONSOLIDATED', 'SITE_CONSOLIDATED', 'PAYMENT_MODE_CONSOLIDATED', 'SALES_EXECUTIVE', 'DRIVER', 'MACHINE_TRACKER'])
            || (strtoupper($type) === 'CUSTOMER_OUTSTANDING' && empty($data['is_single_patron']))) {
            $orientation = 'landscape';
        }

        $css = $this->getReportCss($type);

        $pdfData = array_merge([
            'type'          => strtoupper($type),
            'target_name'   => $targetName,
            'start'         => $startLabel,
            'end'           => $endLabel,
            'plant'         => $plant,
            'patron'        => $patron,
            'consolidation' => $consolidation,
            'css'           => $css,
            'report_css'    => $css,
        ], $data, $extraParams);

        $pdf = Pdf::loadView($view, $pdfData)
            ->setPaper('a4', $orientation)
            ->setOption([
                'defaultFont' => 'DejaVu Sans',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
            ]);

        $cleanStart = str_replace([':', ' '], ['-', '_'], $startLabel);
        return $pdf->download("Report_{$type}_{$cleanStart}.pdf");
    }

    /**
     * Load CSS content for PDF reports from separate CSS file.
     */
    private function getReportCss(string $type = 'default'): string
    {
        $normalizedType = strtolower($type);
        $candidates = [
            public_path("css/reports/{$normalizedType}_report.css"),
            public_path("css/reports/{$normalizedType}.css"),
            public_path("css/reports/report_pdf.css"),
            resource_path("css/reports/report_pdf.css"),
        ];

        foreach ($candidates as $path) {
            if (file_exists($path)) {
                return file_get_contents($path);
            }
        }

        return '';
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
        $this->authorizeReport('sales_register', $request->filled('export') ? 'export' : 'view', $request->all());
        $filters = $request->validate([
            'page' => 'nullable|integer|min:1',
            'register_view' => 'nullable|in:summary,detail',
            'document_status' => 'nullable|in:active,all,cancelled',
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

        // Registers always belong to the user's currently selected plant.
        $plantId = app(\App\Services\PlantContextService::class)->requirePlantId();
        foreach (['plant_id', 'branch_id'] as $key) {
            abort_if(!empty($filters[$key]) && (int) $filters[$key] !== $plantId, 403, 'Select this plant before running its register.');
        }
        $filters['plant_id'] = $plantId;
        unset($filters['branch_id']);

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
        $this->authorizeReport('purchase_register', $request->filled('export') ? 'export' : 'view', $request->all());
        $filters = $request->validate([
            'page' => 'nullable|integer|min:1',
            'register_view' => 'nullable|in:summary,detail',
            'document_status' => 'nullable|in:active,all,cancelled',
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

        // Registers always belong to the user's currently selected plant.
        $plantId = app(\App\Services\PlantContextService::class)->requirePlantId();
        foreach (['plant_id', 'branch_id'] as $key) {
            abort_if(!empty($filters[$key]) && (int) $filters[$key] !== $plantId, 403, 'Select this plant before running its register.');
        }
        $filters['plant_id'] = $plantId;
        unset($filters['branch_id']);

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
        $access = Cache::get($key.':access');
        abort_unless($access, 404, 'Export job not found or expired.');
        abort_unless((int) $access['user_id'] === (int) auth()->id()
            && (int) $access['plant_id'] === (int) session('active_plant_id'), 403);
        $this->authorizeReport($access['type'], 'export', $access['params']);
        $status = Cache::get($key);

        if (!$status) {
            return response()->json([
                'status'  => 'not_found',
                'message' => 'Export job not found or expired.'
            ], 404);
        }

        return response()->json($status)->header('Cache-Control', 'private, no-store');
    }

    public function downloadExport(string $key)
    {
        // Reuse the same owner, active-plant and current report-permission checks.
        $status = $this->getExportStatus($key)->getData(true);
        abort_unless(($status['status'] ?? '') === 'completed', 404);
        $filename = $status['filename'] ?? '';
        abort_unless($filename !== '' && basename($filename) === $filename, 404);
        $path = storage_path('app/private/reports/'.$filename);
        abort_unless(is_file($path), 404);
        return response()->download($path, $filename, ['Cache-Control' => 'private, no-store']);
    }

    /**
     * Generate Machine Summary Report.
     */
    public function machineSummary(Request $request, \App\Services\Reports\MachineReportService $service)
    {
        $this->authorizeReport('machine_summary', $request->filled('export') ? 'export' : 'view');
        $filters = $request->validate([
            'from_date' => 'required|date',
            'to_date'   => 'required|date|after_or_equal:from_date',
            'branch_id' => 'nullable|integer',
            'plant_id'  => 'nullable|integer',
            'machine_id' => 'nullable|integer|min:1',
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
        $this->authorizeReport('vehicle_pl', $request->filled('export') ? 'export' : 'view');
        $filters = $request->validate([
            'from_date' => 'required|date',
            'to_date'   => 'required|date|after_or_equal:from_date',
            'branch_id' => 'nullable|integer',
            'plant_id'  => 'nullable|integer',
            'machine_id' => 'nullable|integer|min:1',
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
        $permissions = app(ReportPermissions::class)->matrix();
        abort_unless(array_filter($permissions, fn ($actions) => $actions['schedule']), 403);
        $plantId = session('active_plant_id');
        $schedules = \App\Models\ReportSchedule::where('plant_id', $plantId)->get()
            ->filter(fn ($schedule) => $permissions[ReportPermissions::reportId($schedule->report_type, $schedule->report_params ?? [])]['schedule'] ?? false)->values();
        return response()->json($schedules);
    }

    /**
     * Store a new report schedule for the active plant.
     */
    public function storeSchedule(Request $request)
    {
        $this->authorizeReport((string) $request->input('report_type'), 'schedule', (array) $request->input('report_params', []));
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
        $data['created_by'] = auth()->id();
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
        $this->authorizeReport($schedule->report_type, 'schedule', $schedule->report_params ?? []);
        if ($schedule->plant_id != session('active_plant_id')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $schedule->delete();

        return response()->json([
            'message' => 'Report schedule deleted successfully.'
        ]);
    }
}
