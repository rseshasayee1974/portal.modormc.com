<?php

namespace App\Services;

use App\Services\Reports\BulkDocumentQuery;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class BulkDocumentZipExportService
{
    public function __construct(
        protected BulkDocumentQuery $query
    ) {}

    /**
     * Execute chunked background ZIP export for bulk documents.
     */
    public function export(int $plantId, array $filters, string $statusKey): void
    {
        try {
            // Increase PHP execution time and memory limits for large batches
            @set_time_limit(0);
            @ini_set('memory_limit', '1024M');

            $matches = $this->query->build($plantId, $filters);
            $total = (clone $matches)->count();

            if ($total === 0) {
                Cache::put($statusKey, [
                    'status' => 'failed',
                    'error' => 'No documents matched the selected filters.',
                ], now()->addHours(2));
                return;
            }

            Cache::put($statusKey, [
                'status' => 'processing',
                'progress' => 0,
                'processed' => 0,
                'total' => $total,
                'message' => "Starting export of {$total} documents...",
            ], now()->addHours(2));

            // Ensure storage directories exist
            $reportsDir = storage_path('app/public/reports');
            if (!file_exists($reportsDir)) {
                mkdir($reportsDir, 0775, true);
            }

            $tempDir = storage_path("app/reports/temp_{$statusKey}");
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0775, true);
            }

            $zipFilename = 'Bulk_Documents_' . $statusKey . '.zip';
            $zipPath = $reportsDir . '/' . $zipFilename;

            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException("Unable to create zip file at: {$zipPath}");
            }

            $processed = 0;
            $chunkSize = 50;

            // Use order by ID for consistent chunking
            $queryBuilder = $matches->reorder('mm_invoices.id', 'asc');

            $queryBuilder->chunk($chunkSize, function ($documents) use (
                &$processed,
                $total,
                $statusKey,
                $tempDir,
                $zip
            ) {
                // Eager-load invoice relations needed for rendering
                $documents->load([
                    'plant.entity.bankAccounts',
                    'plant.addresses.state',
                    'partner.addresses.state',
                    'items.tax',
                    'items.uom',
                    'orderTaxes',
                    'einvoiceRelation',
                ]);

                foreach ($documents as $invoice) {
                    try {
                        // Render individual document HTML using the official tax_invoice template
                        $html = view('pdfs.invoices.tax_invoice', [
                            'invoice' => $invoice,
                            'copy_type' => 'ORIGINAL',
                        ])->render();

                        $sanitizedNumber = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $invoice->full_number);
                        $partnerName = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $invoice->partner?->legal_name ?? 'party');
                        $docType = strtolower($invoice->invoice_type) === 'sales' ? 'INV' : 'BILL';

                        $pdfFileName = "{$docType}_{$sanitizedNumber}_{$partnerName}_{$invoice->id}.pdf";
                        $tempPdfPath = $tempDir . '/' . $pdfFileName;

                        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait');
                        file_put_contents($tempPdfPath, $pdf->output());
                        unset($pdf);

                        // Add to zip file
                        if (!$zip->addFile($tempPdfPath, $pdfFileName)) {
                            throw new \RuntimeException('Unable to add document to ZIP.');
                        }
                    } catch (\Throwable $e) {
                        Log::warning("Failed to render invoice #{$invoice->id} in bulk zip export: " . $e->getMessage());
                        throw $e;
                    }
                    $processed++;
                    Cache::put($statusKey, [
                        'status' => 'processing',
                        'progress' => min(98, round(($processed / $total) * 100)),
                        'processed' => $processed,
                        'total' => $total,
                        'message' => "Exported {$processed} of {$total} documents...",
                    ], now()->addHours(2));
                }

                // Periodic garbage collection to ensure memory is consistently freed
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }

            });

            if (!$zip->close()) {
                throw new \RuntimeException('Unable to finish ZIP archive.');
            }

            // Clean up temporary individual PDF files
            $this->cleanupDirectory($tempDir);

            $fileSizeBytes = file_exists($zipPath) ? filesize($zipPath) : 0;
            $humanSize = $this->formatBytes($fileSizeBytes);

            Cache::put($statusKey, [
                'status' => 'completed',
                'progress' => 100,
                'processed' => $processed,
                'total' => $total,
                'url' => asset('storage/reports/' . $zipFilename),
                'filename' => $zipFilename,
                'file_size' => $humanSize,
                'generated_at' => now()->toDateTimeString(),
            ], now()->addHours(2));

            Log::info("Bulk document ZIP export completed: {$zipFilename} ({$humanSize}, {$processed} documents)");

        } catch (\Throwable $e) {
            Log::error("Bulk document ZIP export failed: " . $e->getMessage(), [
                'status_key' => $statusKey,
                'filters' => $filters,
                'trace' => $e->getTraceAsString(),
            ]);

            Cache::put($statusKey, [
                'status' => 'failed',
                'error' => 'An error occurred during ZIP export: ' . $e->getMessage(),
            ], now()->addHours(2));

            if (isset($tempDir) && file_exists($tempDir)) {
                $this->cleanupDirectory($tempDir);
            }

            throw $e;
        }
    }

    protected function cleanupDirectory(string $dir): void
    {
        if (!file_exists($dir)) return;
        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $file) {
            $path = "$dir/$file";
            is_dir($path) ? $this->cleanupDirectory($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
