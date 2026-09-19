<?php

namespace App\Services\Reports;

use App\Models\MachineTracker;
use App\Services\PlantContextService;
use Carbon\Carbon;

class MachineTrackerReportService implements ReportServiceInterface
{
    public function __construct(private readonly PlantContextService $ctx) {}

    public function generate(array $params): array
    {
        $plantId = $this->ctx->requirePlantId();

        $query = MachineTracker::where('plant_id', $plantId)
            ->whereNull('deleted_at')
            ->with([
                'machine' => fn($q) => $q->whereNull('deleted_at'),
                'operator' => fn($q) => $q->whereNull('deleted_at'),
            ])
            ->latest('opening');

        if (!empty($params['truck_id'])) {
            $query->where('machine_id', $params['truck_id']);
        }

        if (!empty($params['driver_id'])) {
            $query->where('operator_id', $params['driver_id']);
        }

        if (!empty($params['start'])) {
            $query->where(function ($q) use ($params) {
                $q->whereDate('opening', '>=', $params['start'])
                  ->orWhere(function ($q2) use ($params) {
                      $q2->whereNull('opening')->whereDate('created', '>=', $params['start']);
                  });
            });
        }

        if (!empty($params['end'])) {
            $query->where(function ($q) use ($params) {
                $q->whereDate('opening', '<=', $params['end'])
                  ->orWhere(function ($q2) use ($params) {
                      $q2->whereNull('opening')->whereDate('created', '<=', $params['end']);
                  });
            });
        }

        $trackers = $query->get();

        $transactions = $trackers->map(function ($t) {
            $dt = $t->opening ? Carbon::parse($t->opening) : ($t->created ? Carbon::parse($t->created) : null);

            $dateOnly = $dt ? $dt->format('d-m-Y') : '-';
            $timeOnly = $dt ? $dt->format('h:i A') : '';
            $dateFormatted = $dt ? $dt->format('d-m-Y h:i A') : '-';

            $operatorName = $t->operator
                ? trim(($t->operator->first_name ?? '') . ' ' . ($t->operator->last_name ?? ''))
                : 'N/A';
            if ($t->operator && !empty($t->operator->employee_code)) {
                $operatorName .= " ({$t->operator->employee_code})";
            }

            $odoStart = (float)($t->odometer_start ?? 0);
            $odoEnd = (float)($t->odometer_end ?? 0);
            $odoDiff = ($odoEnd >= $odoStart && $odoEnd > 0) ? ($odoEnd - $odoStart) : 0;

            $hmStart = (float)($t->hourmeter_start ?? 0);
            $hmEnd = (float)($t->hourmeter_end ?? 0);
            $hmDiff = ($hmEnd >= $hmStart && $hmEnd > 0) ? ($hmEnd - $hmStart) : 0;

            $ebStart = (float)($t->eb_start ?? 0);
            $ebClose = (float)($t->eb_close ?? 0);
            $ebUnits = ($ebClose >= $ebStart && $ebClose > 0) ? ($ebClose - $ebStart) : 0;

            $shiftLabel = match ((int)$t->shift) {
                1 => 'Shift 1 (Day)',
                2 => 'Shift 2 (Night)',
                3 => 'Shift 3',
                default => 'Shift ' . ($t->shift ?? 1),
            };

            $fuel = (float)($t->fuel ?? 0);
            $odoMileage = ($fuel > 0 && $odoDiff > 0) ? round($odoDiff / $fuel, 2) : 0;
            $hmMileage  = ($fuel > 0 && $hmDiff > 0)  ? round($hmDiff / $fuel, 2)  : 0;
            $hmConsumption = ($hmDiff > 0 && $fuel > 0) ? round($fuel / $hmDiff, 2) : 0;

            $odoDispStr = "{$odoStart} - {$odoEnd} ({$odoDiff} KM" . ($odoMileage > 0 ? " | {$odoMileage} KM/L" : "") . ")";
            $hmDispStr  = "{$hmStart} - {$hmEnd} ({$hmDiff} Hrs" . ($hmMileage > 0 ? " | {$hmMileage} Hrs/L" : "") . ")";

            return [
                'id'                   => $t->id,
                'date'                 => $dateFormatted,
                'date_only'            => $dateOnly,
                'time_only'            => $timeOnly,
                'machine_registration' => $t->machine->registration ?? 'N/A',
                'machine_model'        => $t->machine->vehicle_model ?? 'N/A',
                'operator_name'        => $operatorName ?: 'N/A',
                'shift'                => $t->shift,
                'shift_label'          => $shiftLabel,
                'operation_type'       => $t->operation_type ?? 'N/A',
                'category'             => $t->category ?? 'N/A',
                'odometer_start'       => $odoStart,
                'odometer_end'         => $odoEnd,
                'odometer_diff'        => $odoDiff,
                'odo_mileage'          => $odoMileage,
                'odometer_display'     => $odoDispStr,
                'hourmeter_start'      => $hmStart,
                'hourmeter_end'        => $hmEnd,
                'hourmeter_diff'       => $hmDiff,
                'hm_mileage'           => $hmMileage,
                'hm_consumption'       => $hmConsumption,
                'hourmeter_display'    => $hmDispStr,
                'eb_start'             => $ebStart,
                'eb_close'             => $ebClose,
                'eb_units'             => $ebUnits,
                'eb_display'           => "{$ebStart} - {$ebClose} ({$ebUnits} U)",
                'opening_hsd'          => (float)($t->opening_hsd ?? 0),
                'closing_hsd'          => (float)($t->closing_hsd ?? 0),
                'fuel'                 => $fuel,
                'fuel_amount'          => (float)($t->amount ?? 0),
                'last_fuel_filled_km'  => (float)($t->last_fuel_filled_km ?? 0),
                'fuel_filled_km'       => (float)($t->fuel_filled_km ?? 0),
                'pump_name'            => $t->pump_name ?? '-',
                'notes'                => $t->notes ?? '-',
            ];
        })->values();

        $totalKmRun = $transactions->sum('odometer_diff');
        $totalHoursRun = $transactions->sum('hourmeter_diff');
        $totalEbUnits = $transactions->sum('eb_units');
        $totalFuelLiters = $transactions->sum('fuel');
        $totalFuelAmount = $transactions->sum('fuel_amount');

        $avgKmPerLiter     = $totalFuelLiters > 0 ? round($totalKmRun / $totalFuelLiters, 2) : 0;
        $avgHrsPerLiter    = $totalFuelLiters > 0 ? round($totalHoursRun / $totalFuelLiters, 2) : 0;
        $avgLitersPerHour  = $totalHoursRun > 0 ? round($totalFuelLiters / $totalHoursRun, 2) : 0;

        return [
            'transactions'        => $transactions,
            'total_entries'       => $transactions->count(),
            'total_km_run'        => round($totalKmRun, 2),
            'total_hours_run'     => round($totalHoursRun, 2),
            'total_eb_units'      => round($totalEbUnits, 2),
            'total_fuel_liters'   => round($totalFuelLiters, 2),
            'total_fuel_amount'   => round($totalFuelAmount, 2),
            'avg_km_per_liter'    => $avgKmPerLiter,
            'avg_hrs_per_liter'   => $avgHrsPerLiter,
            'avg_liters_per_hour' => $avgLitersPerHour,
            'opening_balance'     => 0
        ];
    }

    public function targetName(array $params): string
    {
        return 'Machine Tracker Log Sheet Report';
    }
}
