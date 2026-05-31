<?php

namespace App\Services\Reports;

use App\Services\PlantContextService;

/**
 * Contract every legacy report data service must implement.
 *
 * The `generate()` method returns the normalised data array that
 * ReportController passes to exportExcel / exportPdf / json.
 */
interface ReportServiceInterface
{
    /**
     * @param  array  $params  Keys: start, end, id, patron_id, + report-specific extras
     * @return array  Normalised report data (always contains 'transactions' key)
     */
    public function generate(array $params): array;

    /**
     * Human-readable name for PDF titles, file names, etc.
     */
    public function targetName(array $params): string;
}
