<?php

namespace App\Services\Reports;

use App\Models\Batch;
use App\Services\PlantContextService;

class ProductionBatchReportService implements ReportServiceInterface
{
    public function __construct(private readonly PlantContextService $ctx) {}

    public function generate(array $params): array
    {
        $plantId = $this->ctx->requirePlantId();
        $start   = $params['start'];
        $end     = $params['end'];

        $batches = Batch::where('plant_id', $plantId)
            ->with(['operator', 'salesOrder.mixDesign', 'materials.product'])
            ->whereBetween('start_time', [$start . ' 00:00:00', $end . ' 23:59:59'])
            ->get();

        $materialSummary = [];
        foreach ($batches as $batch) {
            foreach ($batch->materials as $mat) {
                $matName = $mat->material_name ?: ($mat->product?->title ?? 'Unknown Material');
                if (!isset($materialSummary[$matName])) {
                    $materialSummary[$matName] = [
                        'material_name' => $matName,
                        'target_qty'    => 0.0,
                        'actual_qty'    => 0.0,
                    ];
                }
                $materialSummary[$matName]['target_qty'] += (float)$mat->target_qty;
                $materialSummary[$matName]['actual_qty'] += (float)$mat->actual_qty;
            }
        }

        return [
            'transactions'     => $batches->map(fn($b) => [
                'date'       => $b->start_time?->toDateString() ?? 'N/A',
                'batch_no'   => 'B'.$b->batch_no,
                'sales_order' => $b->salesOrder ? ($b->salesOrder->prefix . $b->salesOrder->order_no) : 'N/A',
                'mix_design' => $b->salesOrder?->mixDesign?->design_name ?? 'N/A',
                'batch_size' => (float)$b->batch_size,
                'operator'   => $b->operator?->full_name ?? 'N/A',
                'status'     => Batch::statusLabel($b->status),
            ])->values(),
            'material_summary' => array_values($materialSummary),
            'total_batch_size' => (float)$batches->sum('batch_size'),
            'opening_balance'  => 0
        ];
    }

    public function targetName(array $params): string
    {
        return 'Batch Production Consumption Report';
    }
}
