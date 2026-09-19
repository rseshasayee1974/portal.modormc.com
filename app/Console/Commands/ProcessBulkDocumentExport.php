<?php

namespace App\Console\Commands;

use App\Services\BulkDocumentZipExportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ProcessBulkDocumentExport extends Command
{
    protected $signature = 'reports:export-bulk-documents {statusKey} {plantId} {filtersJson}';
    protected $description = 'Process bulk documents ZIP export in the background';

    public function handle(): int
    {
        $statusKey = $this->argument('statusKey');
        $plantId = (int) $this->argument('plantId');
        $filters = json_decode(base64_decode($this->argument('filtersJson')), true) ?: [];

        $this->info("Processing bulk document export for status key: {$statusKey}");

        try {
            // Resolve inside the try so startup failures also reach the status endpoint.
            $service = app(BulkDocumentZipExportService::class);
            \App\Helpers\DateTimeHelper::inTimezone(
                \App\Helpers\DateTimeHelper::timezoneForPlant($plantId),
                fn () => $service->export($plantId, $filters, $statusKey)
            );
            $this->info("Export completed successfully.");
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            report($e);
            Cache::put($statusKey, [
                'status' => 'failed',
                'error' => 'Bulk ZIP export failed. Please retry or contact your administrator.',
            ], now()->addHours(2));
            $this->error("Export failed: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
