<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Repositories\ReportRepository;
use App\Exports\SalesRegisterExport;
use App\Exports\PurchaseRegisterExport;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;

class QueueReportExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $type;
    protected array $filters;
    protected string $statusCacheKey;
    protected string $format;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 600; // 10 minutes
    public $tries = 1;
    public $failOnTimeout = true;

    public static function dispatchExport(string $type, array $filters, string $statusCacheKey, string $format = 'excel'): void
    {
        $job = new static($type, $filters, $statusCacheKey, $format);

        try {
            if (config('reports.async_exports')) {
                $connection = config('reports.queue_connection', 'database');
                if (config("queue.connections.{$connection}.driver") !== 'database') {
                    throw new \RuntimeException('Background reports require a database queue connection.');
                }
                if ((int) config("queue.connections.{$connection}.retry_after") <= $job->timeout) {
                    throw new \RuntimeException('Report queue retry_after must exceed the 600 second job timeout.');
                }
                $job->onConnection($connection)->onQueue(config('reports.queue', 'reports'));
                \Illuminate\Support\Facades\Bus::dispatch($job);
            } else {
                \Illuminate\Support\Facades\Bus::dispatchSync($job);
            }
        } catch (\Throwable $e) {
            $job->failed($e);
            throw $e;
        }
    }

    public function failed(?\Throwable $exception): void
    {
        Cache::put($this->statusCacheKey, [
            'status' => 'failed',
            'error' => 'Report export failed. Please try again or contact support.',
        ], now()->addHour());
    }

    /**
     * Create a new job instance.
     */
    public function __construct(string $type, array $filters, string $statusCacheKey, string $format = 'excel')
    {
        $this->type = $type;
        $this->filters = $filters;
        $this->filters['plant_id'] ??= session('active_plant_id');
        $this->statusCacheKey = $statusCacheKey;
        $this->format = $format;
    }

    /**
     * Execute the job.
     */
    public function middleware(): array
    {
        return [new \App\Jobs\Middleware\UseEntityTimezone(isset($this->filters['plant_id']) ? (int) $this->filters['plant_id'] : null)];
    }

    public function handle(ReportRepository $repository): void
    {
        $previousSession = session()->all();
        try {
            Cache::put($this->statusCacheKey, ['status' => 'processing', 'progress' => 20], now()->addHour());

            $extension = $this->format === 'pdf' ? 'pdf' : 'xlsx';
            $fileName = 'Report_' . ucfirst($this->type) . '_' . \Illuminate\Support\Str::uuid() . '.' . $extension;
            
            $tempDir = storage_path('app/public/reports');
            \Illuminate\Support\Facades\File::ensureDirectoryExists($tempDir, 0775);
            
            $filePath = $tempDir . '/' . $fileName;

            Cache::put($this->statusCacheKey, ['status' => 'processing', 'progress' => 50], now()->addHour());

            // Set active plant context for scoping in repositories/services
            session()->flush();
            $plantId = $this->filters['plant_id'] ?? null;
            if ($plantId) {
                session(['active_plant_id' => $plantId]);
            }

            if ($this->type === 'sales_register') {
                $service = app(\App\Services\Reports\SalesRegisterService::class);
                $service->generateAndSaveReport($this->format, $this->filters, $filePath);
            } elseif ($this->type === 'purchase_register') {
                $service = app(\App\Services\Reports\PurchaseRegisterService::class);
                $service->generateAndSaveReport($this->format, $this->filters, $filePath);
            } elseif ($this->type === 'machine_summary') {
                $service = app(\App\Services\Reports\MachineReportService::class);
                $service->generateAndSaveReport('machine_summary', $this->format, $this->filters, $filePath);
            } elseif ($this->type === 'vehicle_pl') {
                $service = app(\App\Services\Reports\MachineReportService::class);
                $service->generateAndSaveReport('vehicle_pl', $this->format, $this->filters, $filePath);
            } else {
                // Unified reports
                $factory = app(\App\Services\Reports\ReportServiceFactory::class);
                $service = $factory->make($this->type);

                $params = array_merge($this->filters, [
                    'start'            => $this->filters['start_date'] ?? $this->filters['start'] ?? null,
                    'end'              => $this->filters['end_date'] ?? $this->filters['end'] ?? null,
                    'id'               => $this->filters['id'] ?? null,
                    'patron_id'        => $this->filters['patron_id'] ?? null,
                    'voucher_type'     => strtoupper($this->type),
                    'valuation_method' => $this->filters['valuation_method'] ?? 'FIFO',
                    'consolidation'    => $this->filters['consolidation'] ?? 'po',
                    'truck_id'           => $this->filters['truck_id'] ?? null,
                    'driver_id'          => $this->filters['driver_id'] ?? null,
                    'sales_executive_id' => $this->filters['sales_executive_id'] ?? null,
                    'grade_id'           => $this->filters['grade_id'] ?? null,
                    'voucher_type_filter' => $this->filters['voucher_type_filter'] ?? null,
                ]);

                $data = $service->generate($params);

                if ($this->format === 'excel') {
                    $excelService = app(\App\Services\Reports\ExcelExportService::class);
                    $spreadsheet = $excelService->generateExcelReport($this->type, $params['start'], $params['end'], $data);
                    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
                    $writer->save($filePath);
                    $spreadsheet->disconnectWorksheets();
                } else {
                    $targetName = method_exists($service, 'targetName') ? $service->targetName($params) : '';
                    $this->generateAndSaveUnifiedPdf($this->type, $targetName, $params, $data, $filePath);
                }
            }

            Cache::put($this->statusCacheKey, [
                'status' => 'completed',
                'progress' => 100,
                'url' => asset('storage/reports/' . $fileName),
                'filename' => $fileName,
                'generated_at' => now()->toDateTimeString()
            ], now()->addHour());

            Log::info("Asynchronous report export completed: {$fileName}");

        } catch (\Exception $e) {
            Log::error("Asynchronous report export failed: " . $e->getMessage(), [
                'type' => $this->type,
                'filters' => $this->filters,
                'format' => $this->format,
                'trace' => $e->getTraceAsString()
            ]);

            Cache::put($this->statusCacheKey, [
                'status' => 'failed',
                'error' => 'An error occurred during export generation: ' . $e->getMessage()
            ], now()->addHour());
            
            throw $e;
        } finally {
            session()->flush();
            session()->replace($previousSession);
        }
    }

    /**
     * Generate and save standard/unified PDF reports.
     */
    private function generateAndSaveUnifiedPdf(string $type, string $targetName, array $params, array $data, string $filePath): void
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
            'PURCHASE'             => 'reports.purchase_report',
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

        $patronId = $params['patron_id'] ?? null;
        $ledgerId = $params['id'] ?? null;

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
                'headers'    => ['Emp Code', 'Employee Name', 'Department', 'Period', 'Payslip No', 'Gross (₹)', 'Deductions (₹)', 'Net Pay (₹)', 'Status'],
                'fields'     => ['employee_code', 'name', 'department', 'period_name', 'payslip_no', 'total_earnings', 'total_deductions', 'net_salary', 'payslip_status'],
                'alignments' => ['center', 'left', 'left', 'center', 'center', 'right', 'right', 'right', 'center']
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

        $orientation = 'portrait';
        if (in_array(strtoupper($type), ['SILO_STOCK_VALUATION', 'GSTR1', 'GSTR3B', 'PRODUCT_CONSOLIDATED', 'CUSTOMER_CONSOLIDATED', 'TRUCK_CONSOLIDATED', 'SITE_CONSOLIDATED', 'PAYMENT_MODE_CONSOLIDATED', 'SALES_EXECUTIVE', 'DRIVER', 'MACHINE_TRACKER'])) {
            $orientation = 'landscape';
        }

        $startLabel = !empty($params['start']) ? (str_contains($params['start'], ':') ? \Carbon\Carbon::parse($params['start'])->format('d-m-Y H:i') : \Carbon\Carbon::parse($params['start'])->format('d-m-Y')) : '';
        $endLabel   = !empty($params['end']) ? (str_contains($params['end'], ':') ? \Carbon\Carbon::parse($params['end'])->format('d-m-Y H:i') : \Carbon\Carbon::parse($params['end'])->format('d-m-Y')) : '';

        $css = $this->getReportCss($type);

        $pdfData = array_merge([
            'type'          => strtoupper($type),
            'target_name'   => $targetName,
            'start'         => $startLabel,
            'end'           => $endLabel,
            'plant'         => $plant,
            'patron'        => $patron,
            'consolidation' => $params['consolidation'] ?? 'po',
            'landscape'     => ($orientation === 'landscape'),
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
        $pdf->save($filePath);
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
}
