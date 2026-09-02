<?php

namespace App\Services\Reports;

use App\Models\Personnel;
use App\Services\PlantContextService;
use Carbon\Carbon;

class PayrollPersonnelReportService implements ReportServiceInterface
{
    public function __construct(private readonly PlantContextService $ctx) {}

    public function generate(array $params): array
    {
        $plantId = $this->ctx->requirePlantId();

        $personnel = Personnel::where('plant_id', $plantId)
            ->whereNull('deleted_at')
            ->with(['user', 'contacts'])
            ->get();

        return [
            'transactions' => $personnel->map(fn($p) => [
                'name'          => trim(($p->first_name ?? '') . ' ' . ($p->last_name ?? '')),
                'employee_type' => $p->employment_type ?? 'N/A',
                'joining_date'  => $p->joining_date ? Carbon::parse($p->joining_date)->toDateString() : 'N/A',
                'status'        => $p->status ? 'Active' : 'Inactive',
                'email'         => $p->user->email ?? 'N/A',
                'phone'         => $p->contacts->first()?->contact_value ?? 'N/A',
            ])->values(),
            'opening_balance' => 0
        ];
    }

    public function targetName(array $params): string
    {
        return 'Personnel & Payroll Directory';
    }
}
