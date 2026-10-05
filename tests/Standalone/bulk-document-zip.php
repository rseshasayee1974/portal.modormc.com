<?php
// Reuse synthetic invoice fixtures; no application records are read or written.
require __DIR__.'/bulk-document-pdf.php';

use App\Services\BulkDocumentZipExportService;
use App\Services\Reports\BulkDocumentQuery;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

config(['cache.default' => 'array']);
app(BulkDocumentZipExportService::class); // Regression: the original namespace could not resolve.
$fixtures = [];
for ($i = 1; $i <= 13; $i++) {
    $copy = clone $invoice;
    $copy->id = $i;
    $fixtures[] = $copy; // Intentionally duplicate invoice numbers: archive names must stay unique.
}
$collection = new class($fixtures) extends Illuminate\Database\Eloquent\Collection {
    public function load($relations) { return $this; } // Fixtures already have all relations.
};
$builder = new class(App\Models\Invoice::query()->getQuery()) extends Illuminate\Database\Eloquent\Builder {
    public $fixtures;
    public function count($columns = '*') { return $this->fixtures->count(); }
    public function chunk($count, callable $callback) { $callback($this->fixtures); return true; }
};
$builder->fixtures = $collection;
$query = new class($builder) extends BulkDocumentQuery {
    public function __construct(private $builder) {}
    public function build(int $plantId, array $filters): Illuminate\Database\Eloquent\Builder { return $this->builder; }
};
$key = 'bulk_doc_export_test_'.bin2hex(random_bytes(8));
$started = microtime(true);
(new BulkDocumentZipExportService($query))->export(1, [], $key);
$status = Cache::get($key);
if ($status['status'] !== 'completed' || $status['processed'] !== 13) {
    throw new RuntimeException('ZIP export did not complete all documents.');
}
$path = storage_path('app/private/reports/'.$status['filename']);
$zip = new ZipArchive;
if ($zip->open($path) !== true || $zip->numFiles !== 13) {
    throw new RuntimeException('ZIP must contain all 13 PDFs.');
}
for ($i = 0; $i < 13; $i++) {
    if (!str_starts_with($zip->getFromIndex($i), '%PDF-')) throw new RuntimeException('Invalid PDF entry.');
}
$zip->close();
unlink($path);
echo '13-document ZIP verified in '.round(microtime(true) - $started, 2)." seconds.\n";

// A dependency-resolution failure must transition out of queued, even before export() runs.
app()->bind(BulkDocumentZipExportService::class, function () {
    throw new RuntimeException('Synthetic startup failure');
});
$code = Artisan::call('reports:export-bulk-documents', [
    'statusKey' => $key, 'plantId' => 1, 'filtersJson' => base64_encode('{}'),
]);
if ($code !== 1 || Cache::get($key)['status'] !== 'failed') {
    throw new RuntimeException('Startup failure was not reported.');
}
Cache::forget($key);
echo "Startup failure status verified.\n";
