<?php

namespace App\Jobs;

use App\Services\BulkDocumentZipExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class QueueBulkDocumentExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600; // 1 hour timeout for very large exports (e.g. 10,000 documents)
    public int $tries = 1;

    public function __construct(
        public int $plantId,
        public array $filters,
        public string $statusKey
    ) {}

    public function handle(BulkDocumentZipExportService $service): void
    {
        $service->export($this->plantId, $this->filters, $this->statusKey);
    }

    public function middleware(): array
    {
        return [new \App\Jobs\Middleware\UseEntityTimezone($this->plantId)];
    }

    public function failed(?\Throwable $exception): void
    {
        \Illuminate\Support\Facades\Cache::put($this->statusKey, [
            'status' => 'failed',
            'error' => 'Bulk ZIP export failed. Please retry or contact your administrator.',
        ], now()->addHours(2));
    }
}
