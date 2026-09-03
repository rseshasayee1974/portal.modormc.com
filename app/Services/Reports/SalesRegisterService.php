<?php

namespace App\Services\Reports;

use App\Repositories\ReportRepository;
use App\Jobs\QueueReportExportJob;
use App\Exports\SalesRegisterExport;
use Illuminate\Support\Facades\Cache;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

class SalesRegisterService
{
    protected ReportRepository $repository;

    public function __construct(ReportRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Generate Sales Register report data or export file.
     *
     * Time Complexity:
     * - Best Case:  O(1) from Redis Cache (totals).
     * - Average:    O(log n) index seek + O(p) paginated rows.
     * - Export:     O(n) chunked streaming.
     */
    public function generate(array $filters)
    {
        $export  = $filters['export'] ?? null;
        $refresh = !empty($filters['refresh']);

        if ($export === 'excel') return $this->handleExcelExport($filters);
        if ($export === 'pdf')   return $this->handlePdfExport($filters);

        $cacheFilters = array_diff_key($filters, array_flip(['export', 'page', 'per_page', 'refresh', 'queue']));
        $cacheKey     = 'sales_register_' . md5(json_encode($cacheFilters));
        $perPage      = (int) ($filters['per_page'] ?? 100);
        $page         = (int) ($filters['page'] ?? 1);

        if ($refresh) {
            Cache::forget($cacheKey . '_totals');
        }

        $query = $this->repository->getSalesRegisterQuery($filters);
        $items = $query->paginate($perPage, ['*'], 'page', $page);

        $formattedItems = collect($items->items())->map(function ($item) {
            return $this->mapSalesRow($item);
        });

        // Collect all unique tax columns from this page for dynamic header rendering
        $taxColumns = $this->collectTaxColumns($formattedItems->all());

        // Cache only totals (expensive aggregate — stable within 10 minutes)
        try {
            $totals = Cache::remember($cacheKey . '_totals', now()->addMinutes(10), function () use ($filters) {
                return $this->computeTotals($filters);
            });
        } catch (\Exception $e) {
            $totals = $this->computeTotals($filters);
        }

        return [
            'status'      => true,
            'message'     => 'Sales register generated successfully',
            'data'        => $formattedItems->values(),
            'tax_columns' => $taxColumns,
            'pagination'  => [
                'total'        => $items->total(),
                'per_page'     => $items->perPage(),
                'current_page' => $items->currentPage(),
                'last_page'    => $items->lastPage(),
            ],
            'totals' => $totals,
        ];
    }

    /**
     * Map a single InvoiceItem to the report row array.
     * Returns rate-wise tax breakdown in 'taxes' key.
     */
    public function mapSalesRow($item): array
    {
        $invoice = $item->invoice;
        $partner = $invoice?->partner;

        if (!$partner && $invoice && $invoice->invoice_label === 'Dispatch' && $invoice->ref_id) {
            $dispatch = \Illuminate\Support\Facades\DB::table('mm_dispatches')->where('id', $invoice->ref_id)->first();
            if ($dispatch) {
                $customerId = $dispatch->customer_id;
                if (!$customerId && !empty($dispatch->sales_order_id)) {
                    $so = \Illuminate\Support\Facades\DB::table('mm_sales_orders')->where('id', $dispatch->sales_order_id)->first();
                    $customerId = $so?->customer_id;
                }
                if ($customerId) {
                    $partner = \App\Models\Patron::withoutGlobalScopes()->find($customerId);
                }
            }
        }

        $invoiceNo = $invoice
            ? (($invoice->prefix ?? '') . ($invoice->invoice_number ?? ''))
            : '';

        // Build rate-wise tax breakdown: ['CGST_9.00' => 450.00, 'SGST_9.00' => 450.00, ...]
        $taxes = [];
        $cgst  = 0.0;
        $sgst  = 0.0;
        $igst  = 0.0;

        foreach ($item->itemTaxes as $tax) {
            $rawName = strtoupper(trim($tax->name ?? ''));
            $rate    = round((float) $tax->rate, 2);
            $amount  = (float) $tax->amount;

            if (str_contains($rawName, 'CGST')) {
                $baseType = 'CGST';
                $cgst += $amount;
            } elseif (str_contains($rawName, 'UTGST') || str_contains($rawName, 'UGST')) {
                $baseType = 'UTGST';
                $sgst += $amount;
            } elseif (str_contains($rawName, 'SGST')) {
                $baseType = 'SGST';
                $sgst += $amount;
            } elseif (str_contains($rawName, 'IGST')) {
                $baseType = 'IGST';
                $igst += $amount;
            } else {
                $baseType = $rawName ?: 'TAX';
            }

            $colKey = $baseType . '_' . number_format($rate, 2, '.', '');
            $taxes[$colKey] = round(($taxes[$colKey] ?? 0) + $amount, 2);
        }

        $paymentStatus = 'Unpaid';
        if ($invoice) {
            if ($invoice->status === 'paid' || $invoice->balance_amount <= 0) {
                $paymentStatus = 'Paid';
            } elseif ($invoice->paid_amount > 0 && $invoice->balance_amount > 0) {
                $paymentStatus = 'Partial';
            }
        }

        return [
            'id'             => $item->id,
            'invoice_no'     => $invoiceNo,
            'invoice_date'   => $invoice ? $invoice->invoice_date->toDateString() : '',
            'customer_name'  => $partner ? $partner->legal_name : 'N/A',
            'gst_number'     => $partner ? ($partner->gstin ?? '') : '',
            'product_name'   => $item->item_name ?? 'N/A',
            'qty'            => (float) $item->quantity,
            'rate'           => (float) $item->price_unit,
            'taxable_amount' => (float) $item->subtotal,
            'cgst'           => round($cgst, 2),
            'sgst'           => round($sgst, 2),
            'igst'           => round($igst, 2),
            'taxes'          => $taxes,          // rate-wise: {CGST_9.00: 450, SGST_9.00: 450}
            'net_amount'     => (float) $item->line_total,
            'payment_status' => $paymentStatus,
        ];
    }

    /**
     * Collect unique tax columns from formatted rows, sorted: CGST → SGST/UTGST → IGST → OTHER.
     * Returns: [['key' => 'CGST_9.00', 'label' => 'CGST 9%'], ...]
     */
    public function collectTaxColumns(array $rows): array
    {
        $seen = [];
        foreach ($rows as $row) {
            foreach (array_keys($row['taxes'] ?? []) as $colKey) {
                $seen[$colKey] = true;
            }
        }

        $keys = array_keys($seen);

        usort($keys, function ($a, $b) {
            $order = ['CGST' => 0, 'SGST' => 1, 'UTGST' => 2, 'IGST' => 3];
            $typeA = explode('_', $a)[0];
            $typeB = explode('_', $b)[0];
            $oA = $order[$typeA] ?? 9;
            $oB = $order[$typeB] ?? 9;
            if ($oA !== $oB) return $oA - $oB;
            return strcmp($a, $b); // sort by rate within type
        });

        return array_map(function ($key) {
            [$type, $rate] = array_pad(explode('_', $key, 2), 2, '0.00');
            $rateFloat  = (float) $rate;
            $rateLabel  = ($rateFloat == floor($rateFloat))
                ? (int) $rateFloat . '%'
                : $rateFloat . '%';
            return ['key' => $key, 'label' => $type . ' ' . $rateLabel];
        }, $keys);
    }

    /**
     * Compute aggregate totals from the DB.
     */
    protected function computeTotals(array $filters): array
    {
        $raw = $this->repository->getSalesTotals($filters);
        return [
            'qty'         => round((float) ($raw['total_qty'] ?? 0), 2),
            'taxable'     => round((float) ($raw['total_taxable'] ?? 0), 2),
            'gst'         => round((float) ($raw['total_gst'] ?? 0), 2),
            'grand_total' => round((float) ($raw['grand_total'] ?? 0), 2),
            'cgst'        => round((float) ($raw['total_cgst'] ?? 0), 2),
            'sgst'        => round((float) ($raw['total_sgst'] ?? 0), 2),
            'igst'        => round((float) ($raw['total_igst'] ?? 0), 2),
        ];
    }

    /**
     * Handle Excel exports immediately or queue for large datasets.
     */
    protected function handleExcelExport(array $filters): mixed
    {
        $statusKey = 'report_export_' . Str::uuid();
        Cache::put($statusKey, ['status' => 'queued', 'progress' => 0], now()->addHour());

        $filters['plant_id'] = $filters['plant_id'] ?? session('active_plant_id');

        QueueReportExportJob::dispatchSync('sales_register', $filters, $statusKey, 'excel');

        return [
            'status'     => true,
            'queued'     => true,
            'status_key' => $statusKey,
            'export'     => Cache::get($statusKey),
            'message'    => 'Report generation has been queued.',
        ];
    }

    /**
     * Handle PDF export for smaller datasets.
     */
    protected function handlePdfExport(array $filters): mixed
    {
        $statusKey = 'report_export_' . Str::uuid();
        Cache::put($statusKey, ['status' => 'queued', 'progress' => 0], now()->addHour());

        $filters['plant_id'] = $filters['plant_id'] ?? session('active_plant_id');

        QueueReportExportJob::dispatchSync('sales_register', $filters, $statusKey, 'pdf');

        return [
            'status'     => true,
            'queued'     => true,
            'status_key' => $statusKey,
            'export'     => Cache::get($statusKey),
            'message'    => 'Report generation has been queued.',
        ];
    }

    /**
     * Generate report and save to physical file path (for queued job).
     */
    public function generateAndSaveReport(string $format, array $filters, string $filePath): void
    {
        $query = $this->repository->getSalesRegisterQuery($filters);
        
        if ($format === 'excel') {
            $exporter = new SalesRegisterExport($query);
            $exporter->export($filePath);
        } elseif ($format === 'pdf') {
            $rows       = $query->get()->map(fn ($item) => $this->mapSalesRow($item))->values()->all();
            $taxColumns = $this->collectTaxColumns($rows);
            $totals     = $this->computeTotals($filters);

            $pdf = Pdf::loadView('reports.sales_register_pdf', [
                'items'        => $rows,
                'tax_columns'  => $taxColumns,
                'totals'       => $totals,
                'filters'      => $filters,
                'generated_at' => now()->format('d-m-Y H:i:s'),
            ])->setPaper('a4', 'landscape');

            $pdf->save($filePath);
        }
    }
}
