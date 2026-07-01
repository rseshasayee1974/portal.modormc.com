<?php

namespace App\Services\Reports;

use App\Repositories\ReportRepository;
use App\Jobs\QueueReportExportJob;
use App\Exports\MachineSummaryExport;
use App\Exports\VehiclePLExport;
use Illuminate\Support\Facades\Cache;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

class MachineReportService
{
    protected ReportRepository $repository;

    public function __construct(ReportRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Generate Machine Summary report data or export file.
     */
    public function generateMachineSummary(array $filters)
    {
        $export  = $filters['export'] ?? null;
        $refresh = !empty($filters['refresh']);

        if ($export === 'excel') return $this->handleExcelExport('machine_summary', $filters);
        if ($export === 'pdf')   return $this->handlePdfExport('machine_summary', $filters);

        $cacheFilters = array_diff_key($filters, array_flip(['export', 'page', 'per_page', 'refresh', 'queue']));
        $cacheKey     = 'machine_summary_' . md5(json_encode($cacheFilters));
        $perPage      = (int) ($filters['per_page'] ?? 100);
        $page         = (int) ($filters['page'] ?? 1);

        if ($refresh) {
            Cache::forget($cacheKey . '_totals');
        }

        $query = $this->repository->getMachineSummaryQuery($filters);
        $items = $query->paginate($perPage, ['*'], 'page', $page);

        $formattedItems = collect($items->items())->map(function ($item) {
            return $this->mapMachineSummaryRow($item);
        });

        try {
            $totals = Cache::remember($cacheKey . '_totals', now()->addMinutes(10), function () use ($filters) {
                return $this->computeMachineSummaryTotals($filters);
            });
        } catch (\Exception $e) {
            $totals = $this->computeMachineSummaryTotals($filters);
        }

        return [
            'status'     => true,
            'message'    => 'Machine summary generated successfully',
            'data'       => $formattedItems->values(),
            'pagination' => [
                'total'        => $items->total(),
                'per_page'     => $items->perPage(),
                'current_page' => $items->currentPage(),
                'last_page'    => $items->lastPage(),
            ],
            'totals' => $totals,
        ];
    }

    /**
     * Generate Vehicle Wise Profit & Loss report data or export file.
     */
    public function generateVehiclePL(array $filters)
    {
        $export  = $filters['export'] ?? null;
        $refresh = !empty($filters['refresh']);

        if ($export === 'excel') return $this->handleExcelExport('vehicle_pl', $filters);
        if ($export === 'pdf')   return $this->handlePdfExport('vehicle_pl', $filters);

        $cacheFilters = array_diff_key($filters, array_flip(['export', 'page', 'per_page', 'refresh', 'queue']));
        $cacheKey     = 'vehicle_pl_' . md5(json_encode($cacheFilters));
        $perPage      = (int) ($filters['per_page'] ?? 100);
        $page         = (int) ($filters['page'] ?? 1);

        if ($refresh) {
            Cache::forget($cacheKey . '_totals');
        }

        $query = $this->repository->getVehiclePLQuery($filters);
        $items = $query->paginate($perPage, ['*'], 'page', $page);

        $formattedItems = collect($items->items())->map(function ($item) {
            return $this->mapVehiclePLRow($item);
        });

        try {
            $totals = Cache::remember($cacheKey . '_totals', now()->addMinutes(10), function () use ($filters) {
                return $this->computeVehiclePLTotals($filters);
            });
        } catch (\Exception $e) {
            $totals = $this->computeVehiclePLTotals($filters);
        }

        return [
            'status'     => true,
            'message'    => 'Vehicle Wise Profit & Loss generated successfully',
            'data'       => $formattedItems->values(),
            'pagination' => [
                'total'        => $items->total(),
                'per_page'     => $items->perPage(),
                'current_page' => $items->currentPage(),
                'last_page'    => $items->lastPage(),
            ],
            'totals' => $totals,
        ];
    }

    /**
     * Map a single Machine to Machine Summary row.
     */
    public function mapMachineSummaryRow($item): array
    {
        $alerts = [];
        $now = now()->startOfDay();

        if ($item->documents) {
            foreach ($item->documents as $doc) {
                if (empty($doc->expiry_date)) continue;

                $expiry = \Illuminate\Support\Carbon::parse($doc->expiry_date)->startOfDay();
                $type = strtoupper(trim($doc->type ?? ''));

                if ($type === 'FITMENT' || $type === 'INSURANCE') {
                    if ($expiry->isPast()) {
                        $alerts[] = [
                            'type'        => $doc->type,
                            'status'      => 'expired',
                            'expiry_date' => $doc->expiry_date,
                            'message'     => "{$doc->type} expired on " . \Carbon\Carbon::parse($doc->expiry_date)->format('d-m-Y'),
                        ];
                    } elseif ($expiry->diffInDays($now) <= 30) {
                        $alerts[] = [
                            'type'        => $doc->type,
                            'status'      => 'expiring_soon',
                            'expiry_date' => $doc->expiry_date,
                            'message'     => "{$doc->type} expiring in " . $expiry->diffInDays($now) . " days (" . \Carbon\Carbon::parse($doc->expiry_date)->format('d-m-Y') . ")",
                        ];
                    }
                }
            }
        }

        return [
            'id'                => $item->id,
            'registration'      => $item->registration,
            'vehicle_model'     => $item->vehicle_model ?? 'N/A',
            'vehicle_type'      => $item->vehicle_type ?? 'N/A',
            'make_year'         => $item->make_year ?? 'N/A',
            'capacity'          => $item->capacity ?? 'N/A',
            'owner'             => $item->owner->legal_name ?? 'Self/Company Owned',
            'trips_count'       => (int) $item->trips_count,
            'total_qty'         => round((float) $item->total_qty, 2),
            'total_weight_tons' => round((float) $item->total_weight_tons, 2),
            'total_revenue'     => round((float) $item->total_revenue, 2),
            'general_expenses'  => round((float) $item->general_expenses, 2),
            'alerts'            => $alerts,
        ];
    }

    /**
     * Map a single Machine to Vehicle Wise Profit & Loss row.
     */
    public function mapVehiclePLRow($item): array
    {
        $tripRevenue = (float) $item->trip_revenue;
        $tripCost    = (float) $item->trip_cost;
        $fuel        = (float) $item->fuel_expenses;
        $maintenance = (float) $item->maintenance_expenses;
        $other       = (float) $item->other_expenses;

        $totalCost = $tripCost + $fuel + $maintenance + $other;
        $netProfit = $tripRevenue - $totalCost;
        $marginPct = $tripRevenue > 0 ? ($netProfit / $tripRevenue) * 100.0 : 0.0;

        return [
            'id'                   => $item->id,
            'registration'         => $item->registration,
            'vehicle_model'        => $item->vehicle_model ?? 'N/A',
            'vehicle_type'         => $item->vehicle_type ?? 'N/A',
            'trip_revenue'         => round($tripRevenue, 2),
            'trip_cost'            => round($tripCost, 2),
            'fuel_expenses'        => round($fuel, 2),
            'maintenance_expenses' => round($maintenance, 2),
            'other_expenses'       => round($other, 2),
            'total_cost'           => round($totalCost, 2),
            'net_profit'           => round($netProfit, 2),
            'margin_pct'           => round($marginPct, 2),
        ];
    }

    /**
     * Compute totals for Machine Summary report.
     */
    protected function computeMachineSummaryTotals(array $filters): array
    {
        $raw = $this->repository->getMachineSummaryTotals($filters);
        return [
            'trips_count'       => (int) ($raw['total_trips'] ?? 0),
            'total_qty'         => round((float) ($raw['total_qty'] ?? 0), 2),
            'total_weight_tons' => round((float) ($raw['total_weight_tons'] ?? 0), 2),
            'total_revenue'     => round((float) ($raw['total_revenue'] ?? 0), 2),
            'general_expenses'  => round((float) ($raw['total_general_expenses'] ?? 0), 2),
        ];
    }

    /**
     * Compute totals for Vehicle Wise Profit & Loss report.
     */
    protected function computeVehiclePLTotals(array $filters): array
    {
        $raw = $this->repository->getVehiclePLTotals($filters);

        $revenue     = (float) ($raw['total_revenue'] ?? 0);
        $tripCost    = (float) ($raw['total_trip_cost'] ?? 0);
        $fuel        = (float) ($raw['total_fuel_expenses'] ?? 0);
        $maintenance = (float) ($raw['total_maintenance_expenses'] ?? 0);
        $other       = (float) ($raw['total_other_expenses'] ?? 0);

        $totalCost = $tripCost + $fuel + $maintenance + $other;
        $netProfit = $revenue - $totalCost;
        $marginPct = $revenue > 0 ? ($netProfit / $revenue) * 100.0 : 0.0;

        return [
            'trip_revenue'         => round($revenue, 2),
            'trip_cost'            => round($tripCost, 2),
            'fuel_expenses'        => round($fuel, 2),
            'maintenance_expenses' => round($maintenance, 2),
            'other_expenses'       => round($other, 2),
            'total_cost'           => round($totalCost, 2),
            'net_profit'           => round($netProfit, 2),
            'margin_pct'           => round($marginPct, 2),
        ];
    }

    /**
     * Handle Excel exports immediately or queue for large datasets.
     */
    protected function handleExcelExport(string $reportType, array $filters): mixed
    {
        $statusKey = 'report_export_' . Str::uuid();
        Cache::put($statusKey, ['status' => 'queued', 'progress' => 0], now()->addHour());

        $filters['plant_id'] = $filters['plant_id'] ?? session('active_plant_id');

        QueueReportExportJob::dispatchSync($reportType, $filters, $statusKey, 'excel');

        return [
            'status'     => true,
            'queued'     => true,
            'status_key' => $statusKey,
            'message'    => 'Report generation has been queued.',
        ];
    }

    /**
     * Handle PDF export for smaller datasets.
     */
    protected function handlePdfExport(string $reportType, array $filters): mixed
    {
        $statusKey = 'report_export_' . Str::uuid();
        Cache::put($statusKey, ['status' => 'queued', 'progress' => 0], now()->addHour());

        $filters['plant_id'] = $filters['plant_id'] ?? session('active_plant_id');

        QueueReportExportJob::dispatchSync($reportType, $filters, $statusKey, 'pdf');

        return [
            'status'     => true,
            'queued'     => true,
            'status_key' => $statusKey,
            'message'    => 'Report generation has been queued.',
        ];
    }

    /**
     * Generate report and save to physical file path (for queued job).
     */
    public function generateAndSaveReport(string $reportType, string $format, array $filters, string $filePath): void
    {
        if ($reportType === 'machine_summary') {
            $query = $this->repository->getMachineSummaryQuery($filters);
        } else {
            $query = $this->repository->getVehiclePLQuery($filters);
        }

        if ($format === 'excel') {
            if ($reportType === 'machine_summary') {
                $exporter = new MachineSummaryExport($query, $this);
            } else {
                $exporter = new VehiclePLExport($query, $this);
            }
            $exporter->export($filePath);
        } elseif ($format === 'pdf') {
            $plantId = session('active_plant_id');
            $plant   = \App\Models\Plant::with(['addresses.state', 'contacts'])->find($plantId);

            if ($reportType === 'machine_summary') {
                $rows   = $query->get()->map(fn ($item) => $this->mapMachineSummaryRow($item))->values()->all();
                $totals = $this->computeMachineSummaryTotals($filters);
                $view   = 'reports.machine_summary_pdf';
            } else {
                $rows   = $query->get()->map(fn ($item) => $this->mapVehiclePLRow($item))->values()->all();
                $totals = $this->computeVehiclePLTotals($filters);
                $view   = 'reports.vehicle_pl_pdf';
            }

            $pdf = Pdf::loadView($view, [
                'items'        => $rows,
                'totals'       => $totals,
                'filters'      => $filters,
                'plant'        => $plant,
                'generated_at' => now()->format('d-m-Y H:i:s'),
            ])->setPaper('a4', 'landscape');

            $pdf->save($filePath);
        }
    }
}
