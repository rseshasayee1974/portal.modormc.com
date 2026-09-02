<?php

namespace App\Services\Reports;

use App\Models\Machine;
use App\Services\PlantContextService;

class MachinesListReportService implements ReportServiceInterface
{
    public function __construct(private readonly PlantContextService $ctx) {}

    public function generate(array $params): array
    {
        $plantId = $this->ctx->requirePlantId();

        $query = Machine::where('plant_id', $plantId)
            ->whereNull('deleted_at')
            ->with(['owner' => fn($q) => $q->whereNull('deleted_at')]);

        if (!empty($params['truck_id'])) {
            $query->where('id', $params['truck_id']);
        }

        if (!empty($params['start'])) {
            $query->whereDate('created_at', '>=', $params['start']);
        }

        if (!empty($params['end'])) {
            $query->whereDate('created_at', '<=', $params['end']);
        }

        $machines = $query->get();

        return [
            'transactions' => $machines->map(fn($m) => [
                'registration'  => $m->registration,
                'vehicle_model' => $m->vehicle_model ?? 'N/A',
                'vehicle_type'  => $m->vehicle_type ?? 'N/A',
                'make_year'     => $m->make_year ?? 'N/A',
                'capacity'      => $m->capacity ?? 'N/A',
                'owner'         => $m->owner->legal_name ?? 'Self/Company Owned',
            ])->values(),
            'opening_balance' => 0
        ];
    }

    public function targetName(array $params): string
    {
        return 'Fleet & Machine Inventory List';
    }
}
