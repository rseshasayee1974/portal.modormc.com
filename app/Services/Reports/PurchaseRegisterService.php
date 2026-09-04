<?php

namespace App\Services\Reports;

use App\Repositories\ReportRepository;
use App\Jobs\QueueReportExportJob;
use App\Exports\PurchaseRegisterExport;
use Illuminate\Support\Facades\Cache;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

class PurchaseRegisterService
{
    protected ReportRepository $repository;

    public function __construct(ReportRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Generate Purchase Register report data or export file.
     *
     * Time Complexity:
     * - Best Case: O(1) from Redis Cache (totals).
     * - Average Case: O(log n) database index seek + O(p) paginated rows.
     * - Export Retrieval: O(n) streaming.
     *
     * @param array $filters
     * @return mixed
     */
    public function generate(array $filters)
    {
        $export  = $filters['export'] ?? null;
        $refresh = !empty($filters['refresh']);

        // Handle File Exporting (bypass cache)
        if ($export === 'excel') {
            return $this->handleExcelExport($filters);
        }

        if ($export === 'pdf') {
            return $this->handlePdfExport($filters);
        }

        // Build cache key from filters (exclude pagination + transient keys)
        $cacheFilters = array_diff_key($filters, array_flip(['export', 'page', 'per_page', 'refresh', 'queue']));
        $cacheKey     = 'purchase_register_' . md5(json_encode($cacheFilters));
        $perPage      = (int) ($filters['per_page'] ?? 100);
        $page         = (int) ($filters['page'] ?? 1);

        if ($refresh) {
            Cache::forget($cacheKey . '_totals');
        }

        $query = $this->repository->getPurchaseRegisterQuery($filters);

        // Paginate dynamically — not cached
        $items = $query->paginate($perPage, ['*'], 'page', $page);

        // Map records for response
        $formattedItems = collect($items->items())->map(function ($item) {
            return $this->mapPurchaseRow($item);
        });

        // Collect all unique tax columns from this page for dynamic header rendering
        $taxColumns = $this->collectTaxColumns($formattedItems->all());

        // Cache only totals (they don't change with page)
        // Wrapped in try/catch to gracefully handle Redis unavailability
        try {
            $totals = Cache::remember($cacheKey . '_totals', now()->addMinutes(10), function () use ($filters) {
                $raw = $this->repository->getPurchaseTotals($filters);
                return [
                    'qty'         => round((float) ($raw['total_qty'] ?? 0), 2),
                    'taxable'     => round((float) ($raw['total_taxable'] ?? 0), 2),
                    'gst'         => round((float) ($raw['total_gst'] ?? 0), 2),
                    'grand_total' => round((float) ($raw['grand_total'] ?? 0), 2),
                    'cgst'        => round((float) ($raw['total_cgst'] ?? 0), 2),
                    'sgst'        => round((float) ($raw['total_sgst'] ?? 0), 2),
                    'igst'        => round((float) ($raw['total_igst'] ?? 0), 2),
                ];
            });
        } catch (\Exception $e) {
            // Redis unavailable — compute totals directly from DB
            $raw    = $this->repository->getPurchaseTotals($filters);
            $totals = [
                'qty'         => round((float) ($raw['total_qty'] ?? 0), 2),
                'taxable'     => round((float) ($raw['total_taxable'] ?? 0), 2),
                'gst'         => round((float) ($raw['total_gst'] ?? 0), 2),
                'grand_total' => round((float) ($raw['grand_total'] ?? 0), 2),
                'cgst'        => round((float) ($raw['total_cgst'] ?? 0), 2),
                'sgst'        => round((float) ($raw['total_sgst'] ?? 0), 2),
                'igst'        => round((float) ($raw['total_igst'] ?? 0), 2),
            ];
        }

        return [
            'status'      => true,
            'message'     => 'Purchase register generated successfully',
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
     * Map a single PurchaseOrderItem to the report row array.
     * Returns rate-wise tax breakdown in 'taxes' key.
     */
    public function mapPurchaseRow($item): array
    {
        $order  = $item->order;
        $vendor = $order?->vendor;
        $plant  = $order?->plant;

        // Build rate-wise tax breakdown
        $taxes = [];
        $cgst  = 0.0;
        $sgst  = 0.0;
        $igst  = 0.0;

        // Dynamic tax calculation
        $taxModel = $item->tax;
        $taxRate  = $taxModel ? (float) $taxModel->tax_rate : 0.0;
        $priceTax = (float) $item->price_tax;

        if ($taxRate <= 0 && $priceTax > 0 && (float) $item->price_subtotal > 0) {
            $taxRate = round(($priceTax / (float) $item->price_subtotal) * 100, 2);
        }
        $taxGroup = $taxModel ? strtoupper(trim($taxModel->tax_group ?? '')) : '';

        if ($taxRate > 0 && $priceTax > 0) {
            // Determine Intra vs Inter
            $isIntra = true; // Default
            $plantGstin = $plant ? trim($plant->gstin ?? '') : '';
            if (empty($plantGstin)) {
                $plantId = session('active_plant_id');
                if ($plantId) {
                    $plantGstin = \Illuminate\Support\Facades\DB::table('mm_plants')->where('id', $plantId)->value('gstin') ?? '';
                }
            }
            $plantState = strlen($plantGstin) >= 2 ? substr($plantGstin, 0, 2) : '33';

            $vendorGstin = $vendor ? trim($vendor->gstin ?? '') : '';
            $vendorState = strlen($vendorGstin) >= 2 ? substr($vendorGstin, 0, 2) : '';

            if ($vendorState !== '' && $plantState !== $vendorState) {
                $isIntra = false;
            }

            if ($isIntra) {
                if ($taxGroup === 'GST' || empty($taxGroup)) {
                    $halfRate = round($taxRate / 2, 2);
                    $halfAmount = round($priceTax / 2, 2);

                    $cgst = $halfAmount;
                    $sgst = $halfAmount;

                    $taxes['CGST_' . number_format($halfRate, 2, '.', '')] = $halfAmount;
                    $taxes['SGST_' . number_format($halfRate, 2, '.', '')] = $halfAmount;
                } elseif (str_contains($taxGroup, 'CGST')) {
                    $cgst = $priceTax;
                    $taxes['CGST_' . number_format($taxRate, 2, '.', '')] = $priceTax;
                } elseif (str_contains($taxGroup, 'SGST') || str_contains($taxGroup, 'UTGST')) {
                    $sgst = $priceTax;
                    $type = str_contains($taxGroup, 'UTGST') ? 'UTGST' : 'SGST';
                    $taxes[$type . '_' . number_format($taxRate, 2, '.', '')] = $priceTax;
                } elseif (str_contains($taxGroup, 'IGST')) {
                    $igst = $priceTax;
                    $taxes['IGST_' . number_format($taxRate, 2, '.', '')] = $priceTax;
                } else {
                    $halfRate = round($taxRate / 2, 2);
                    $halfAmount = round($priceTax / 2, 2);

                    $cgst = $halfAmount;
                    $sgst = $halfAmount;

                    $taxes['CGST_' . number_format($halfRate, 2, '.', '')] = $halfAmount;
                    $taxes['SGST_' . number_format($halfRate, 2, '.', '')] = $halfAmount;
                }
            } else {
                if ($taxGroup === 'GST' || empty($taxGroup)) {
                    $igst = $priceTax;
                    $taxes['IGST_' . number_format($taxRate, 2, '.', '')] = $priceTax;
                } elseif (str_contains($taxGroup, 'CGST')) {
                    $cgst = $priceTax;
                    $taxes['CGST_' . number_format($taxRate, 2, '.', '')] = $priceTax;
                } elseif (str_contains($taxGroup, 'SGST') || str_contains($taxGroup, 'UTGST')) {
                    $sgst = $priceTax;
                    $type = str_contains($taxGroup, 'UTGST') ? 'UTGST' : 'SGST';
                    $taxes[$type . '_' . number_format($taxRate, 2, '.', '')] = $priceTax;
                } elseif (str_contains($taxGroup, 'IGST')) {
                    $igst = $priceTax;
                    $taxes['IGST_' . number_format($taxRate, 2, '.', '')] = $priceTax;
                } else {
                    $igst = $priceTax;
                    $taxes['IGST_' . number_format($taxRate, 2, '.', '')] = $priceTax;
                }
            }
        }

        $billNo = $order ? ($order->bill_number ?: $order->po_number) : '';
        $billDate = $order
            ? ($order->billed_date
                ? $order->billed_date->toDateString()
                : ($order->date_order ? $order->date_order->toDateString() : ''))
            : '';

        return [
            'id'             => $item->id,
            'bill_no'        => $billNo,
            'bill_date'      => $billDate,
            'supplier_name'  => $vendor ? $vendor->legal_name : 'N/A',
            'gst_number'     => $vendor ? ($vendor->gstin ?? '') : '',
            'product_name'   => $item->product ? $item->product->title : ($item->description ?? 'N/A'),
            'qty'            => (float) $item->product_quantity,
            'purchase_rate'  => (float) $item->unit_price,
            'taxable_amount' => (float) $item->price_subtotal,
            'cgst'           => round($cgst, 2),
            'sgst'           => round($sgst, 2),
            'igst'           => round($igst, 2),
            'taxes'          => $taxes,
            'net_amount'     => (float) $item->price_total,
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
            foreach ($row['taxes'] ?? [] as $colKey => $amount) {
                if ((float)$amount > 0.001) {
                    $seen[$colKey] = true;
                }
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
     * Handle Excel exports immediately or queue for large datasets.
     */
    protected function handleExcelExport(array $filters): mixed
    {
        $statusKey = 'report_export_' . Str::uuid();
        Cache::put(
            $statusKey,
            ['status' => 'queued', 'progress' => 0],
            now()->addHour()
        );

        $filters['plant_id'] = $filters['plant_id'] ?? session('active_plant_id');

        QueueReportExportJob::dispatchSync('purchase_register', $filters, $statusKey, 'excel');

        return [
            'status'     => true,
            'queued'     => true,
            'status_key' => $statusKey,
            'export'     => Cache::get($statusKey),
            'message'    => 'Report generation has been queued. You will be notified when the export completes.',
        ];
    }

    /**
     * Handle PDF export for smaller datasets.
     */
    protected function handlePdfExport(array $filters): mixed
    {
        $statusKey = 'report_export_' . Str::uuid();
        Cache::put(
            $statusKey,
            ['status' => 'queued', 'progress' => 0],
            now()->addHour()
        );

        $filters['plant_id'] = $filters['plant_id'] ?? session('active_plant_id');

        QueueReportExportJob::dispatchSync('purchase_register', $filters, $statusKey, 'pdf');

        return [
            'status'     => true,
            'queued'     => true,
            'status_key' => $statusKey,
            'export'     => Cache::get($statusKey),
            'message'    => 'Report generation has been queued. You will be notified when the export completes.',
        ];
    }

    /**
     * Generate report and save to physical file path (for queued job).
     */
    public function generateAndSaveReport(string $format, array $filters, string $filePath): void
    {
        $query = $this->repository->getPurchaseRegisterQuery($filters);
        
        if ($format === 'excel') {
            $start = $filters['start_date'] ?? $filters['start'] ?? '';
            $end = $filters['end_date'] ?? $filters['end'] ?? '';
            $period = ($start && $end) ? "Period: $start to $end" : ($start ?: $end ?: 'All Dates');
            $exporter = new PurchaseRegisterExport($query);
            $exporter->export($filePath, $period);
        } elseif ($format === 'pdf') {
            $rows = $query->get()->map(fn ($item) => $this->mapPurchaseRow($item))->values()->all();
            $taxColumns = $this->collectTaxColumns($rows);

            // Fetch totals
            $raw = $this->repository->getPurchaseTotals($filters);
            $totals = [
                'qty'         => round((float) ($raw['total_qty'] ?? 0), 2),
                'taxable'     => round((float) ($raw['total_taxable'] ?? 0), 2),
                'gst'         => round((float) ($raw['total_gst'] ?? 0), 2),
                'grand_total' => round((float) ($raw['grand_total'] ?? 0), 2),
                'cgst'        => round((float) ($raw['total_cgst'] ?? 0), 2),
                'sgst'        => round((float) ($raw['total_sgst'] ?? 0), 2),
                'igst'        => round((float) ($raw['total_igst'] ?? 0), 2),
            ];

            $pdf = Pdf::loadView('reports.purchase_register_pdf', [
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
