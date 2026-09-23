<?php

namespace App\Exports;

use App\Services\Reports\SalesRegisterService;
use Illuminate\Database\Eloquent\Builder;

class SalesRegisterExport
{
    public function __construct(protected Builder $query) {}

    public function export(string $filePath, string $period = 'All Dates'): void
    {
        $service = app(SalesRegisterService::class);
        $report = $service->buildReportFromQuery($this->query);
        app(RegisterReportExport::class)->export(
            $filePath, 'Sales Register', ['period_label' => $period], $report
        );
    }
}
