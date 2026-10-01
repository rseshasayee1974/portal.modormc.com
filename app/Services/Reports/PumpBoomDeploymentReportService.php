<?php

namespace App\Services\Reports;

use App\Models\PumpBoomDeploymentSchedule;
use App\Services\PlantContextService;
use Carbon\Carbon;

class PumpBoomDeploymentReportService implements ReportServiceInterface
{
    public const COLUMNS = [
        'date' => 'Schedule Date',
        'site' => 'Site Name',
        'customer' => 'Customer',
        'mix_design' => 'Mix Design',
        'quantity' => 'Planned Vol (m³)',
        'pump' => 'Pump No',
        'operator' => 'Operator',
        'boom_length' => 'Boom Length (m)',
        'arrival_time' => 'Arrival Time',
        'setup_start' => 'Setup Start',
        'setup_end' => 'Setup End',
        'pour_start' => 'Pour Start',
        'pour_end' => 'Pour End',
        'status' => 'Status',
        'setup_duration' => 'Setup (mins)',
        'pump_duration' => 'Pump (mins)',
        'delay' => 'Delay (mins)',
    ];

    public function __construct(private readonly PlantContextService $ctx) {}

    public function generate(array $params): array
    {
        $query = PumpBoomDeploymentSchedule::query()
            ->where('plant_id', $this->ctx->requirePlantId())
            ->whereBetween('schedule_date', [Carbon::parse($params['start'])->toDateString(), Carbon::parse($params['end'])->toDateString()])
            ->with(['site:id,name', 'mixDesign:id,design_name', 'pumpMachine:id,registration', 'operator:id,first_name,last_name', 'salesOrder.customer'])
            ->orderBy('schedule_date')->orderBy('pump_arrival_time')->orderBy('id');

        foreach (['site_id' => 'site_id', 'pump_vehicle_id' => 'pump_vehicle_id', 'mix_design_id' => 'mix_design_id'] as $param => $column) {
            if (!empty($params[$param])) {
                $query->where($column, $params[$param]);
            }
        }

        $schedules = $query->get();
        return [
            'transactions' => $schedules->map(fn ($s) => [
                'date' => $s->schedule_date->toDateString(),
                'site' => $s->site_name ?? $s->site?->name ?? 'Unassigned',
                'customer' => $s->billing_name ?? $s->salesOrder?->customer?->legal_name ?? 'Unassigned',
                'mix_design' => $s->grade ?? $s->mixDesign?->design_name ?? 'Unassigned',
                'quantity' => (float) $s->planned_qty_m3,
                'pump' => $s->pump_no ?? $s->pumpMachine?->registration ?? 'Unassigned',
                'operator' => $s->operator_name ?? ($s->operator ? trim($s->operator->first_name . ' ' . $s->operator->last_name) : 'Unassigned'),
                'boom_length' => (float) $s->boom_length_m,
                'arrival_time' => $s->pump_arrival_time?->format('h:i A') ?? '-',
                'setup_start' => $s->setup_start_time?->format('h:i A') ?? '-',
                'setup_end' => $s->setup_end_time?->format('h:i A') ?? '-',
                'pour_start' => $s->pour_start_time?->format('h:i A') ?? '-',
                'pour_end' => ($s->actual_end_time ?? $s->planned_end_time)?->format('h:i A') ?? '-',
                'status' => ucwords(str_replace('_', ' ', $s->status ?? '')),
                'setup_duration' => $s->setup_duration_minutes ?? '-',
                'pump_duration' => $s->pumping_duration_minutes ?? '-',
                'delay' => $s->delay_minutes ?: '-',
            ])->values(),
            'columns' => self::COLUMNS,
            'total_schedules' => $schedules->count(),
            'total_quantity' => (float) $schedules->where('status', '!=', 'cancelled')->sum('planned_qty_m3'),
            'opening_balance' => 0,
        ];
    }

    public function targetName(array $params): string
    {
        return 'Pump & Boom Deployment Report';
    }

    public static function pdfColumns(array $data): array
    {
        return [
            'headers' => array_values(self::COLUMNS),
            'fields' => array_keys(self::COLUMNS),
            'alignments' => array_map(fn ($field) => in_array($field, ['quantity', 'boom_length', 'setup_duration', 'pump_duration', 'delay']) ? 'right' : 'left', array_keys(self::COLUMNS)),
            'totals' => ['quantity' => $data['total_quantity'] ?? 0],
        ];
    }
}
