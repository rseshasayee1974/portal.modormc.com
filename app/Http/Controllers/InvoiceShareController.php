<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\PublicDocumentLink;
use App\Models\Plant;
use App\Models\Patron;
use App\Services\PrintDataFormatter;
use App\Services\Reports\ReportServiceFactory;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;

class InvoiceShareController extends Controller
{
    /**
     * Generate a unique secure sharing link.
     * Accessible by authenticated users only.
     */
    public function generateLink(Request $request)
    {
        $request->validate([
            'document_type' => ['required', Rule::in(['invoice','report','batch','Invoice','Report','Batch','INVOICE','REPORT','BATCH'])],
            'document_id' => 'required_if:document_type,invoice|required_if:document_type,batch|nullable|integer',
            'expiry' => 'required|in:1,7,30,0', // 1 day, 7 days, 30 days, 0 (never)
            'report_params' => 'required_if:document_type,report|array',
        ]);

        $docType = strtolower($request->input('document_type'));
        $docId = $request->input('document_id');
        $expiryOption = (string) $request->input('expiry');
        $reportParams = $request->input('report_params');

        // Determine expiration timestamp
        $expiresAt = null;
        if ($expiryOption === '1') {
            $expiresAt = Carbon::now()->addDay();
        } elseif ($expiryOption === '7') {
            $expiresAt = Carbon::now()->addDays(7);
        } elseif ($expiryOption === '30') {
            $expiresAt = Carbon::now()->addDays(30);
        }

        // Determine plant context scoping
        $plantId = null;
        if ($docType === 'invoice') {
            $invoice = Invoice::findOrFail($docId);
            $plantId = $invoice->plant_id;
        } elseif ($docType === 'batch') {
            $batch = \App\Models\Batch::findOrFail($docId);
            $plantId = $batch->plant_id;
        } else {
            // For reports, default to active plant in session
            $plantId = session('active_plant_id') ?? app(\App\Services\PlantContextService::class)->plantId();
        }

        if (!$plantId) {
            return response()->json([
                'success' => false,
                'message' => 'No active plant context found to share this document.'
            ], 422);
        }

        // Generate secure 64-char token
        do {
            $token = Str::random(64);
        } while (PublicDocumentLink::where('token', $token)->exists());

        // Save Link
        $link = PublicDocumentLink::create([
            'document_type' => $docType,
            'document_id' => $docId,
            'token' => $token,
            'expires_at' => $expiresAt,
            'is_active' => true,
            'created_by' => auth()->id(),
            'plant_id' => $plantId,
            'document_params' => $reportParams,
        ]);

        // Build URL
        $path = $docType === 'invoice' ? '/public/invoice/' : ($docType === 'batch' ? '/public/batch/' : '/public/report/');
        $url = url($path . $token);

        return response()->json([
            'success' => true,
            'url' => $url,
            'expires_at' => $expiresAt ? $expiresAt->toDateTimeString() : 'Never',
        ]);
    }

    /**
     * View Batch Report Publicly (No login required)
     */
    public function viewBatch(string $token)
    {
        $link = PublicDocumentLink::where('token', $token)->first();

        if (!$this->validateLink($link) || $link->document_type !== 'batch') {
            return response()->view('public.invalid_link', [], 410);
        }

        Session::put('active_plant_id', $link->plant_id);

        $batch = \App\Models\Batch::findOrFail($link->document_id);
        $batch->load([
            'workOrder.customer',
            'workOrder.site',
            'workOrder.plant.entity',
            'workOrder.mixDesign.concrete_grade',
            'dispatches.truck',
            'dispatches.driver',
            'materials.product.category',
            'materials.uom',
        ]);

        $sheet = $batch->getReportData();

        return view('public.batch', [
            'batch' => $batch,
            'sheet' => $sheet,
            'token' => $token,
        ]);
    }

    /**
     * Download Batch Report PDF Publicly (No login required)
     */
    public function downloadBatchPDF(string $token)
    {
        $link = PublicDocumentLink::where('token', $token)->first();

        if (!$this->validateLink($link) || $link->document_type !== 'batch') {
            abort(410, "This link is no longer available.");
        }

        Session::put('active_plant_id', $link->plant_id);

        $batch = \App\Models\Batch::findOrFail($link->document_id);
        $batch->load([
            'workOrder.customer',
            'workOrder.site',
            'workOrder.plant.entity',
            'workOrder.mixDesign.concrete_grade',
            'dispatches.truck',
            'dispatches.driver',
            'materials.product.category',
            'materials.uom',
        ]);

        $sheet = $batch->getReportData();

        $pdf = Pdf::loadView('pdfs.batches.batch_sheet', [
            'batch' => $batch,
            'sheet' => $sheet,
            'isPreview' => false,
        ])->setPaper('a4', 'landscape');

        $orderNo = $batch->workOrder?->order_no ?? 'order';
        $safeOrderNo = str_replace(['/', '\\'], '-', $orderNo);
        $filename = sprintf(
            'batch-sheet-%s-%s.pdf',
            $safeOrderNo,
            $batch->batch_no ?? $batch->id
        );

        return $pdf->download($filename);
    }

    /**
     * View Invoice Publicly (No login required)
     */
    public function viewInvoice(string $token)
    {
        $link = PublicDocumentLink::where('token', $token)->first();

        if (!$this->validateLink($link) || $link->document_type !== 'invoice') {
            return response()->view('public.invalid_link', [], 410);
        }

        // Override session plant context temporarily for this request lifecycle
        Session::put('active_plant_id', $link->plant_id);

        $invoice = Invoice::with(['plant', 'plant.entity', 'partner', 'items.tax', 'items.uom', 'orderTaxes'])
            ->findOrFail($link->document_id);

        $data = PrintDataFormatter::fromInvoice($invoice);
        $templateKey = PrintDataFormatter::resolveTemplateKey('invoices', $invoice->plant_id);
        $view = PrintDataFormatter::resolveView($templateKey);

        return view('public.invoice', [
            'data' => $data,
            'token' => $token,
            'view' => $view,
            'invoice' => $invoice,
        ]);
    }

    /**
     * Download Invoice PDF Publicly (No login required)
     */
    public function downloadPDF(string $token)
    {
        $link = PublicDocumentLink::where('token', $token)->first();

        if (!$this->validateLink($link) || $link->document_type !== 'invoice') {
            abort(410, "This link is no longer available.");
        }

        Session::put('active_plant_id', $link->plant_id);

        $invoice = Invoice::findOrFail($link->document_id);
        $data = PrintDataFormatter::fromInvoice($invoice);
        $templateKey = PrintDataFormatter::resolveTemplateKey('invoices', $invoice->plant_id);
        $view = PrintDataFormatter::resolveView($templateKey);

        $pdf = Pdf::loadView($view, ['data' => $data]);
        
        $safeDocNo = str_replace(['/', '\\'], '-', $data['doc_no']);
        $filename = Str::slug($data['doc_title'] . '_' . $safeDocNo) . '.pdf';
        
        return $pdf->download($filename);
    }

    /**
     * View Report Publicly (No login required)
     */
    public function viewReport(string $token)
    {
        $link = PublicDocumentLink::where('token', $token)->first();

        if (!$this->validateLink($link) || strtolower($link->document_type) !== 'report') {
            return response()->view('public.invalid_link', [], 410);
        }

        Session::put('active_plant_id', $link->plant_id);

        $pdfData = $this->compileReportData($link);

        return view('public.report', [
            'pdfData' => $pdfData['pdfData'],
            'view'    => $pdfData['view'],
            'token'   => $token,
            'type'    => $pdfData['type'],
            'start'   => $pdfData['start'],
        ]);
    }

    /**
     * Download Report PDF Publicly (No login required)
     */
    public function downloadReportPDF(string $token)
    {
        $link = PublicDocumentLink::where('token', $token)->first();

        if (!$this->validateLink($link) || strtolower($link->document_type) !== 'report') {
            abort(410, "This link is no longer available.");
        }

        Session::put('active_plant_id', $link->plant_id);

        $pdfData = $this->compileReportData($link);

        $landscapeTypes = ['sales_register', 'purchase_register', 'machine_summary', 'vehicle_pl', 'silo_stock_valuation', 'gstr1', 'gstr3b'];
        $orientation = in_array(strtolower($pdfData['type']), $landscapeTypes) ? 'landscape' : 'portrait';

        $pdf = Pdf::loadView($pdfData['view'], $pdfData['pdfData'])->setPaper('a4', $orientation);

        return $pdf->download("Report_" . $pdfData['type'] . "_" . $pdfData['start'] . ".pdf");
    }

    /**
     * Private helper to validate shared link status and expiry
     */
    private function validateLink($link)
    {
        if (!$link || !$link->is_active) {
            return false;
        }

        if ($link->expires_at && Carbon::parse($link->expires_at)->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Helper to compile report data dynamically using ReportServiceFactory or dedicated services
     */
    private function compileReportData(PublicDocumentLink $link): array
    {
        $params = $link->document_params ?? [];
        $type = strtolower($params['type'] ?? 'sales');

        $start = $params['start_date'] ?? $params['from_date'] ?? $params['start'] ?? now()->startOfMonth()->toDateString();
        $end = $params['end_date'] ?? $params['to_date'] ?? $params['end'] ?? now()->toDateString();

        $plant = Plant::with(['addresses.state', 'contacts'])->find($link->plant_id);

        if (in_array($type, ['sales_register', 'purchase_register', 'machine_summary', 'vehicle_pl'])) {
            $filters = [
                'from_date'      => $start,
                'to_date'        => $end,
                'start_date'     => $start,
                'end_date'       => $end,
                'customer_id'    => $params['patron_id'] ?? $params['customer_id'] ?? null,
                'supplier_id'    => $params['patron_id'] ?? $params['supplier_id'] ?? null,
                'gst_type'       => $params['gst_type'] ?? null,
                'payment_status' => $params['payment_status'] ?? null,
                'plant_id'       => $link->plant_id,
                'per_page'       => 10000,
            ];

            if ($type === 'sales_register') {
                $service = app(\App\Services\Reports\SalesRegisterService::class);
                $result = $service->generate($filters);
                $view = 'reports.sales_register_pdf';
            } elseif ($type === 'purchase_register') {
                $service = app(\App\Services\Reports\PurchaseRegisterService::class);
                $result = $service->generate($filters);
                $view = 'reports.purchase_register_pdf';
            } elseif ($type === 'machine_summary') {
                $service = app(\App\Services\Reports\MachineReportService::class);
                $result = $service->generateMachineSummary($filters);
                $view = 'reports.machine_summary_pdf';
            } else { // vehicle_pl
                $service = app(\App\Services\Reports\MachineReportService::class);
                $result = $service->generateVehiclePL($filters);
                $view = 'reports.vehicle_pl_pdf';
            }

            $pdfData = [
                'items'        => $result['data'] ?? [],
                'totals'       => $result['totals'] ?? [],
                'tax_columns'  => $result['tax_columns'] ?? [],
                'filters'      => $filters,
                'plant'        => $plant,
                'generated_at' => now()->format('d-m-Y H:i:s'),
                'type'         => strtoupper($type),
            ];

            return [
                'pdfData' => $pdfData,
                'view'    => $view,
                'type'    => $type,
                'start'   => $start,
            ];
        }

        $factory = app(ReportServiceFactory::class);
        $service = $factory->make($type);

        // Standardize params for report service
        $reportParams = [
            'start'            => $start,
            'end'              => $end,
            'id'               => $params['id'] ?? null,
            'patron_id'        => $params['patron_id'] ?? $params['customer_id'] ?? $params['supplier_id'] ?? null,
            'voucher_type'     => strtoupper($type),
            'valuation_method' => $params['valuation_method'] ?? 'FIFO',
            'truck_id'         => $params['truck_id'] ?? null,
            'plant_id'         => $link->plant_id,
        ];

        $data = $service->generate($reportParams);
        $targetName = method_exists($service, 'targetName') ? $service->targetName($reportParams) : 'All';

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

        $patron = null;
        $patronId = $reportParams['patron_id'];
        $ledgerId = $reportParams['id'];

        if ($patronId) {
            $patron = Patron::with(['addresses.state'])->find($patronId);
        } elseif ($ledgerId) {
            $patron = Patron::with(['addresses.state'])->where('ledger_id', $ledgerId)->first();
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
            'start'         => Carbon::parse($reportParams['start'])->format('d-m-Y'),
            'end'           => Carbon::parse($reportParams['end'])->format('d-m-Y'),
            'plant'         => $plant,
            'patron'        => $patron,
            'consolidation' => $params['consolidation'] ?? 'po'
        ], $data, $extraParams);

        return [
            'pdfData' => $pdfData,
            'view' => $view,
            'type' => $type,
            'start' => $reportParams['start'],
        ];
    }
}
