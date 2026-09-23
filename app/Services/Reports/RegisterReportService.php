<?php

namespace App\Services\Reports;

use App\Jobs\QueueReportExportJob;
use App\Repositories\ReportRepository;
use App\Services\PlantContextService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

abstract class RegisterReportService implements ReportServiceInterface
{
    public function __construct(protected ReportRepository $repository) {}

    abstract protected function reportType(): string;
    abstract protected function query(array $filters): Builder;
    abstract protected function mapRow($item): array;

    public function targetName(array $params): string
    {
        return $this->reportType() === 'sales_register' ? 'Sales Register' : 'Purchase Register';
    }

    protected function normalizeFilters(array $filters): array
    {
        $filters['plant_id'] = $filters['plant_id'] ?? app(PlantContextService::class)->requirePlantId();
        $filters['from_date'] = $filters['from_date'] ?? $filters['start_date'] ?? $filters['start'] ?? now()->startOfMonth()->toDateString();
        $filters['to_date'] = $filters['to_date'] ?? $filters['end_date'] ?? $filters['end'] ?? now()->toDateString();
        $filters['customer_id'] = $filters['customer_id'] ?? $filters['patron_id'] ?? null;
        $filters['supplier_id'] = $filters['supplier_id'] ?? $filters['patron_id'] ?? null;
        $filters['register_view'] = $filters['register_view'] ?? 'detail';
        foreach (['register_view', 'document_status', 'gst_type', 'payment_status'] as $key) {
            if (isset($filters[$key])) $filters[$key] = strtolower($filters[$key]);
        }

        return $filters;
    }

    public function generate(array $filters): array
    {
        $filters = $this->normalizeFilters($filters);
        if (in_array($filters['export'] ?? null, ['excel', 'pdf'], true)) {
            $key = 'report_export_'.Str::uuid();
            Cache::put($key, ['status' => 'queued', 'progress' => 0], now()->addHour());
            QueueReportExportJob::dispatchExport($this->reportType(), $filters, $key, $filters['export']);

            return ['status' => true, 'queued' => true, 'status_key' => $key,
                'export' => Cache::get($key), 'message' => 'Report generation has been queued.'];
        }

        return $this->buildReport($filters);
    }

    /**
     * Stream matching lines once. Keep only the requested page in memory while
     * accumulating totals and tax columns across the entire filtered register.
     * Ordering by document makes summary groups safe across database chunks.
     */
    public function buildReport(array $filters, bool $allRows = false): array
    {
        $filters = $this->normalizeFilters($filters);
        return $this->buildReportFromQuery($this->query($filters), $filters, $allRows);
    }

    /** Preserve an existing export query's filters and reuse the register mapping. */
    public function buildReportFromQuery(Builder $query, array $filters = [], bool $allRows = true): array
    {
        return $this->buildFromRows((clone $query)->lazy(500)->map(fn ($item) => $this->mapRow($item)), $filters, $allRows);
    }

    /** Build screen and export columns/totals from the same mapped tax amounts. */
    public function buildFromRows(iterable $items, array $filters = [], bool $allRows = true): array
    {
        $filters['register_view'] = strtolower($filters['register_view'] ?? 'detail');
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(1, (int) ($filters['per_page'] ?? 100));
        $offset = ($page - 1) * $perPage;
        $summary = $filters['register_view'] === 'summary';
        $rows = [];
        $count = 0;
        $group = null;
        $totals = ['qty' => 0.0, 'taxable' => 0.0, 'gst' => 0.0, 'grand_total' => 0.0,
            'cgst' => 0.0, 'sgst' => 0.0, 'utgst' => 0.0, 'igst' => 0.0, 'tcs' => 0.0, 'taxes' => []];
        $fields = ['qty' => 'qty', 'taxable_amount' => 'taxable', 'tax_amount' => 'gst',
            'net_amount' => 'grand_total', 'cgst' => 'cgst', 'sgst' => 'sgst', 'utgst' => 'utgst', 'igst' => 'igst', 'tcs' => 'tcs'];
        $append = function (array $row) use (&$rows, &$count, $offset, $perPage, $allRows) {
            if ($allRows || ($count >= $offset && $count < $offset + $perPage)) {
                $rows[] = $row;
            }
            $count++;
        };

        foreach ($items as $row) {
            $row['taxes'] = $row['taxes'] ?? [];
            $row['tax_amount'] = $row['tax_amount'] ?? array_sum($row['taxes']);
            // Derive component totals from the rate splits, including UTGST on its own.
            foreach (['cgst', 'sgst', 'utgst', 'igst', 'tcs'] as $component) {
                $row[$component] = round(array_sum(array_filter($row['taxes'],
                    fn ($key) => str_starts_with($key, strtoupper($component).'_'), ARRAY_FILTER_USE_KEY)), 2);
            }
            foreach ($fields as $field => $total) {
                $totals[$total] += $row[$field];
            }
            foreach ($row['taxes'] as $key => $amount) {
                $totals['taxes'][$key] = ($totals['taxes'][$key] ?? 0) + $amount;
            }
            if (!$summary) {
                $append($row);
                continue;
            }
            if ($group !== null && $group['document_id'] !== $row['document_id']) {
                $append($group);
                $group = null;
            }
            if ($group === null) {
                $group = $row;
                $group['id'] = $row['document_id'];
            } else {
                foreach ($fields as $field => $total) {
                    $group[$field] = round($group[$field] + $row[$field], 2);
                }
                foreach ($row['taxes'] as $key => $amount) {
                    $group['taxes'][$key] = round(($group['taxes'][$key] ?? 0) + $amount, 2);
                }
            }
        }
        if ($group !== null) {
            $append($group);
        }
        foreach ($totals as $key => $value) {
            $totals[$key] = is_array($value) ? array_map(fn ($amount) => round($amount, 2), $value) : round($value, 2);
        }
        $taxColumns = $this->collectTaxColumns([['taxes' => $totals['taxes']]]);
        $sampleDetail = $this->reportType() === 'sales_register' && !$summary;

        return [
            'status' => true, 'message' => $this->targetName($filters).' generated successfully',
            'register_view' => $filters['register_view'], 'data' => $rows, 'totals' => $totals,
            'tax_columns' => $taxColumns,
            'columns' => RegisterReportColumns::for($this->reportType(), $filters['register_view'], $taxColumns),
            'note' => ($sampleDetail ? 'Gross is the stored item total including tax; Sales GST is the taxable item value. ' : '')
                .'Amounts are based on matching invoice or bill items. Document-level charges, discounts and rounding are excluded.',
            'pagination' => ['total' => $count, 'per_page' => $perPage, 'current_page' => $page,
                'last_page' => max(1, (int) ceil($count / $perPage))],
        ];
    }

    public function collectTaxColumns(array $rows): array
    {
        $keys = [];
        foreach ($rows as $row) {
            foreach ($row['taxes'] ?? [] as $key => $amount) {
                $keys[$key] = true;
            }
        }
        $keys = array_keys($keys);
        usort($keys, function ($a, $b) {
            [$typeA, $rateA] = explode('_', $a, 2);
            [$typeB, $rateB] = explode('_', $b, 2);
            $order = ['CGST' => 0, 'SGST' => 1, 'UTGST' => 2, 'IGST' => 3, 'TCS' => 4];
            return (($order[$typeA] ?? 9) <=> ($order[$typeB] ?? 9))
                ?: strcmp($typeA, $typeB) ?: ((float) $rateA <=> (float) $rateB);
        });

        // Keep the requested GST pairs together, followed by the full IGST rates,
        // for both registers and layouts, including periods with zero tax amounts.
        $columns = [];
        foreach ([5, 12, 18, 28] as $gstRate) {
            foreach (['CGST', 'SGST'] as $type) {
                $rate = $gstRate / 2;
                $key = $type.'_'.number_format($rate, 2, '.', '');
                $columns[$key] = ['key' => $key, 'label' => $type.' '.$rate.'%'];
            }
        }
        foreach ([5, 12, 18, 28] as $rate) {
            $key = 'IGST_'.number_format($rate, 2, '.', '');
            $columns[$key] = ['key' => $key, 'label' => 'IGST '.$rate.'%'];
        }
        // Preserve additional recorded taxes (for example TCS or UTGST) by rate.
        foreach ($keys as $key) {
            [$type, $rate] = explode('_', $key, 2);
            $columns[$key] = ['key' => $key, 'label' => $type.' '.(float) $rate.'%'];
        }
        return array_values($columns);
    }

    public function generateAndSaveReport(string $format, array $filters, string $filePath): void
    {
        $filters = $this->normalizeFilters($filters);
        $report = $this->buildReport($filters, true);
        if ($format === 'excel') {
            app(\App\Exports\RegisterReportExport::class)->export(
                $filePath, $this->targetName($filters), $filters, $report
            );
        } elseif ($format === 'pdf') {
            Pdf::loadView('reports.register_pdf', [
                'title' => $this->targetName($filters), 'report' => $report,
                'filters' => $filters, 'generated_at' => now()->format('d-m-Y H:i:s'),
            ])->setPaper('a4', 'landscape')->save($filePath);
        } else {
            throw new \InvalidArgumentException('Unsupported register export format.');
        }
    }
}
