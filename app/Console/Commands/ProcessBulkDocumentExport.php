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

        $this->info("Processing bulk document export for status key: {$statusKey}");

        try {
            $filters = $this->argument('filtersJson') === 'cached'
                ? Cache::get($statusKey . ':filters')
                : json_decode(base64_decode($this->argument('filtersJson')), true);
            if (!is_array($filters)) {
                throw new \RuntimeException('Export filters are missing or expired. Please start a new export.');
            }
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
        } finally {
            if ($this->argument('filtersJson') === 'cached') Cache::forget($statusKey . ':filters');
        }
    }
}
