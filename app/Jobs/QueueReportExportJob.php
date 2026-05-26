<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Repositories\ReportRepository;
use App\Exports\SalesRegisterExport;
use App\Exports\PurchaseRegisterExport;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class QueueReportExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $type;
    protected array $filters;
    protected string $statusCacheKey;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 600; // 10 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(string $type, array $filters, string $statusCacheKey)
    {
        $this->type = $type;
        $this->filters = $filters;
        $this->statusCacheKey = $statusCacheKey;
    }

    /**
     * Execute the job.
     */
    public function handle(ReportRepository $repository): void
    {
        try {
            Cache::put($this->statusCacheKey, ['status' => 'processing', 'progress' => 20], now()->addHour());

            $fileName = 'Report_' . ucfirst($this->type) . '_' . date('Ymd_His') . '.xlsx';
            
            $tempDir = storage_path('app/public/reports');
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0775, true);
            }
            
            $filePath = $tempDir . '/' . $fileName;

            Cache::put($this->statusCacheKey, ['status' => 'processing', 'progress' => 50], now()->addHour());

            if ($this->type === 'sales') {
                $query = $repository->getSalesRegisterQuery($this->filters);
                $exporter = new SalesRegisterExport($query);
                $exporter->export($filePath);
            } elseif ($this->type === 'purchase') {
                $query = $repository->getPurchaseRegisterQuery($this->filters);
                $exporter = new PurchaseRegisterExport($query);
                $exporter->export($filePath);
            } elseif ($this->type === 'machine_summary') {
                $query = $repository->getMachineSummaryQuery($this->filters);
                $service = app(\App\Services\Reports\MachineReportService::class);
                $exporter = new \App\Exports\MachineSummaryExport($query, $service);
                $exporter->export($filePath);
            } elseif ($this->type === 'vehicle_pl') {
                $query = $repository->getVehiclePLQuery($this->filters);
                $service = app(\App\Services\Reports\MachineReportService::class);
                $exporter = new \App\Exports\VehiclePLExport($query, $service);
                $exporter->export($filePath);
            }

            Cache::put($this->statusCacheKey, [
                'status' => 'completed',
                'progress' => 100,
                'url' => asset('storage/reports/' . $fileName),
                'filename' => $fileName,
                'generated_at' => now()->toDateTimeString()
            ], now()->addHour());

            Log::info("Asynchronous report export completed: {$fileName}");

        } catch (\Exception $e) {
            Log::error("Asynchronous report export failed: " . $e->getMessage(), [
                'type' => $this->type,
                'filters' => $this->filters,
                'trace' => $e->getTraceAsString()
            ]);

            Cache::put($this->statusCacheKey, [
                'status' => 'failed',
                'error' => 'An error occurred during export generation: ' . $e->getMessage()
            ], now()->addHour());
            
            throw $e;
        }
    }
}
