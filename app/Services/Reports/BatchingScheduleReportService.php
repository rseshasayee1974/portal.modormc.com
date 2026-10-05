<?php

namespace App\Services\Reports;

use App\Models\ConcreteBatchingSchedule;
use App\Services\PlantContextService;
use Carbon\Carbon;

class BatchingScheduleReportService implements ReportServiceInterface
{
    public const COLUMNS = [
        'date' => 'Schedule Date',
        'pour_reference' => 'Pour Reference',
        'site' => 'Unloading Site',
        'truck' => 'Truck',
        'pump' => 'Pump',
        'pump_type' => 'Placement Type',
        'mix_design' => 'Mix Design',
        'quantity' => 'Volume (m³)',
        'batching_time' => 'Batching Time',
        'dispatch_time' => 'Dispatch Time',
        'eta_site' => 'Site ETA',
        'unloading_start' => 'Unloading Start',
        'unloading_end' => 'Unloading End',
        'status' => 'Status',
    ];

    public function __construct(private readonly PlantContextService $ctx) {}

    public function generate(array $params): array
    {
        $query = ConcreteBatchingSchedule::query()
            ->where('plant_id', $this->ctx->requirePlantId())
            ->whereBetween('schedule_date', [Carbon::parse($params['start'])->toDateString(), Carbon::parse($params['end'])->toDateString()])
            ->with(['site:id,name', 'vehicle:id,registration', 'pumpVehicle:id,registration', 'mixDesign:id,design_name'])
            ->orderBy('schedule_date')->orderBy('batching_time')->orderBy('id');

        foreach (['site_id' => 'site_id', 'truck_id' => 'vehicle_id', 'pump_vehicle_id' => 'pump_vehicle_id', 'mix_design_id' => 'mix_design_id'] as $param => $column) {
            if (!empty($params[$param])) {
                $query->where($column, $params[$param]);
            }
        }

        $schedules = $query->get();
        return [
            'transactions' => $schedules->map(fn ($s) => [
                'date' => $s->schedule_date->toDateString(),
                'pour_reference' => $s->pour_reference,
                'site' => $s->site?->name ?? 'Unassigned',
                'truck' => $s->vehicle?->registration ?? 'Unassigned',
                'pump' => $s->pumpVehicle?->registration ?? 'Unassigned',
                'pump_type' => ucwords(str_replace('_', ' ', $s->pump_type ?? '')),
                'mix_design' => $s->mixDesign?->design_name ?? 'Unassigned',
                'quantity' => (float) $s->qty_m3,
                'batching_time' => $s->batching_time?->format('h:i A') ?? '-',
                'dispatch_time' => $s->dispatch_time?->format('h:i A') ?? '-',
                'eta_site' => $s->eta_site?->format('h:i A') ?? '-',
                'unloading_start' => $s->unloading_start?->format('h:i A') ?? '-',
                'unloading_end' => $s->unloading_end?->format('h:i A') ?? '-',
                'status' => ucwords(str_replace('_', ' ', $s->status)),
            ])->values(),
            'columns' => self::COLUMNS,
            'total_schedules' => $schedules->count(),
            // Cancelled slots remain visible for audit but do not contribute volume.
            'total_quantity' => (float) $schedules->where('status', '!=', 'cancelled')->sum('qty_m3'),
            'opening_balance' => 0,
        ];
    }

    public function targetName(array $params): string
    {
        return 'Batching Schedule Report';
    }

    public static function pdfColumns(array $data): array
    {
        return [
            'headers' => array_values(self::COLUMNS),
            'fields' => array_keys(self::COLUMNS),
            'alignments' => array_map(fn ($field) => $field === 'quantity' ? 'right' : 'left', array_keys(self::COLUMNS)),
            'totals' => ['quantity' => $data['total_quantity'] ?? 0],
        ];
    }
}
