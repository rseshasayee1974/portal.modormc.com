<?php

namespace App\Exports;

use App\Services\Reports\PurchaseRegisterService;
use Illuminate\Database\Eloquent\Builder;

class PurchaseRegisterExport
{
    public function __construct(protected Builder $query) {}

    public function export(string $filePath, string $period = 'All Dates'): void
    {
        $service = app(PurchaseRegisterService::class);
        $report = $service->buildReportFromQuery($this->query);
        app(RegisterReportExport::class)->export(
            $filePath, 'Purchase Register', ['period_label' => $period], $report
        );
    }
}
