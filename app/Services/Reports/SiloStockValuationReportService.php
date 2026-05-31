<?php

namespace App\Services\Reports;

use App\Services\PlantContextService;
use App\Services\SCM\InventoryValuationService;

class SiloStockValuationReportService implements ReportServiceInterface
{
    public function __construct(
        private readonly PlantContextService $ctx,
        private readonly InventoryValuationService $valuationService
    ) {}

    public function generate(array $params): array
    {
        $plantId = $this->ctx->requirePlantId();
        $start   = $params['start'];
        $end     = $params['end'];
        $method  = $params['valuation_method'] ?? 'FIFO';

        $result = $this->valuationService->calculate($plantId, $start, $end, $method);

        $formattedProducts = [];
        $totalOpeningVal   = 0.0;
        $totalInwardVal    = 0.0;
        $totalConsumedVal  = 0.0;
        $totalEndingVal    = 0.0;

        foreach ($result['products'] as $p) {
            $totalOpeningVal  += $p['opening_value'];
            $totalInwardVal   += $p['inward_value'];
            $totalConsumedVal += $p['consumed_value'];
            $totalEndingVal   += $p['ending_value'];

            $p['opening_value_formatted']  = '₹ ' . number_format($p['opening_value'], 2);
            $p['inward_value_formatted']   = '₹ ' . number_format($p['inward_value'], 2);
            $p['consumed_value_formatted'] = '₹ ' . number_format($p['consumed_value'], 2);
            $p['ending_value_formatted']   = '₹ ' . number_format($p['ending_value'], 2);
            $p['avg_unit_cost_formatted']  = '₹ ' . number_format($p['avg_unit_cost'], 2);

            $formattedProducts[] = $p;
        }

        return [
            'transactions'                    => $formattedProducts,
            'products'                        => $formattedProducts,
            'total_opening_value_formatted'   => '₹ ' . number_format($totalOpeningVal, 2),
            'total_inward_value_formatted'    => '₹ ' . number_format($totalInwardVal, 2),
            'total_consumed_value_formatted'  => '₹ ' . number_format($totalConsumedVal, 2),
            'total_ending_value_formatted'    => '₹ ' . number_format($totalEndingVal, 2),
            'opening_balance'                 => 0
        ];
    }

    public function targetName(array $params): string
    {
        return 'Silo Stock Valuation Report';
    }
}
