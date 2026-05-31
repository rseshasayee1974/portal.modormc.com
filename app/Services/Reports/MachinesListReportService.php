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

        $machines = Machine::where('plant_id', $plantId)
            ->with(['owner'])
            ->get();

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
