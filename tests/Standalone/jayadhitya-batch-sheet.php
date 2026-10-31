<?php

/*
 * Author: ragul-onemodo
 * Created: 2026-10-31 09:25:59 Asia/Calcutta (UTC+05:30)
 */

use App\Models\Batch;
use App\Models\BatchMaterial;
use App\Models\Dispatch;
use App\Models\Machine;
use App\Models\MixDesign;
use App\Models\MixDesignItem;
use App\Models\Patron;
use App\Models\Personnel;
use App\Models\Plant;
use App\Models\SalesOrder;
use App\Models\Site;
use App\Services\BatchSheetBrowserPdf;
use App\Services\JayadhityaBatchSheet;
use Illuminate\Contracts\Console\Kernel;

// Render the real Blade template with in-memory models; no database records are changed.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Isolate settings/logo checks from the application's live database.
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
Illuminate\Support\Facades\DB::purge('sqlite');
Illuminate\Support\Facades\Schema::create('mm_custom_settings', function ($table) {
    $table->id();
    $table->unsignedBigInteger('plant_id');
    $table->string('module_name');
    $table->text('settings');
    $table->softDeletes();
});
Illuminate\Support\Facades\Storage::fake('public');
$logoPath = 'batch-sheet-logos/739/logo.png';
$logoBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
if ($previewLogo = getenv('BATCH_SHEET_TEST_LOGO')) {
    $logoBytes = file_get_contents($previewLogo);
}
Illuminate\Support\Facades\Storage::disk('public')->put($logoPath, $logoBytes);
Illuminate\Support\Facades\DB::table('mm_custom_settings')->insert([
    'plant_id' => 739, 'module_name' => 'batching',
    'settings' => json_encode(['batch_sheet_logo_path' => $logoPath]),
]);


$check = function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};
$plant = new Plant(['name' => 'JAYADHITYA GROUPS', 'code' => '739', 'plant_type' => 'M1 C', 'mixer_capacity' => 1]);
$plant->id = 739;
$plant->setRelation('entity', null)->setRelation('addresses', collect());
$mix = new MixDesign(['design_name' => 'M35 MARS', 'design_code' => 'M35 MARS']);
$order = new SalesOrder(['order_no' => 'MARS RMC', 'total_qty' => 100]);
$order->setRelation('plant', $plant)->setRelation('mixDesign', $mix)
    ->setRelation('customer', new Patron(['legal_name' => 'MARS RMC']))
    ->setRelation('site', new Site(['name' => 'ALAMATHI']));
$dispatch = new Dispatch;
$dispatch->setRelation('truck', new Machine(['registration' => 'TN12AC8311']))
    ->setRelation('driver', new Personnel(['first_name' => 'Kali']));
$batch = new Batch(['batch_no' => '7227', 'batch_size' => 2, 'start_time' => '2026-10-27 23:11:17', 'end_time' => '2026-10-27 23:15:13']);
$batch->setRelation('plant', $plant)->setRelation('salesOrder', $order)->setRelation('workOrder', $order)
    ->setRelation('dispatches', collect([$dispatch]));
$materials = collect();
$recipe = collect();
foreach ([
    ['MSAND 1', 744, 739, 350], ['MSAND 2', 850, 853, 400], ['20MM', 1254, 1259, 630],
    ['12MM', 1094, 1092, 550], ['CEMENT 1', 640, 645, 320], ['CEMENT 3', 100, 103, 50],
    ['WATER', 256, 254, 160], ['ADMIX 1', 4, 4.02, 2],
] as $i => [$name, $target, $actual, $quantity]) {
    $material = new BatchMaterial(['product_id' => $i + 1, 'material_name' => $name, 'target_qty' => $target, 'actual_qty' => $actual]);
    $product = new App\Models\Product(['title' => 'Full product title '.$i, 'alias' => $i === 0 ? 'M-SAND' : ' ']);
    $product->setRelation('category', null);
    $materials->push($material->setRelation('product', $product));
    $recipe->push((new MixDesignItem(['product_id' => $i + 1, 'actual_quantity' => $quantity]))->setRelation('product', null));
}
$batch->setRelation('materials', $materials);
$mix->setRelation('items', $recipe);
$report = JayadhityaBatchSheet::data($batch);
$check(JayadhityaBatchSheet::matches($batch), 'Plant template not selected.');
$check(abs($report['total_target'] - 4942) < 0.001, 'Set weight mismatch.');
$check(abs($report['total_actual'] - 4949.02) < 0.001, 'Actual weight mismatch.');
$check(abs(array_sum($report['recipe']) - 2462) < 0.001, 'Recipe mass must differ from total set mass.');
$check(abs($report['difference'] - 0.142047) < 0.00001, 'Percentage must be calculated from weights.');
$html = view('pdfs.batches.batch_sheet', ['batch' => $batch, 'isPreview' => false])->render();
foreach (['JAYADHITYA GROUPS', 'TN12AC8311', '4949.02', '2462.00', 'Difference in Percentage'] as $expected) {
    $check(str_contains($html, $expected), 'Missing rendered value: '.$expected);
}
$layout = JayadhityaBatchSheet::layoutData($batch);
$check($layout['labels']['MSAND 1'] === 'M-SAND', 'Column heading must use the product alias.');
$check($layout['labels']['MSAND 2'] === 'MSAND 2', 'Blank product aliases must fall back to the stored material name.');
$check(str_contains($html, '>M-SAND</th>'), 'The product alias must appear in the rendered heading.');
$check(! str_contains($html, 'Full product title'), 'Product titles must not replace available aliases.');
$check(count($layout['columns']) === 8, 'Columns must follow stored materials, without unused fixed slots.');
$check(count($layout['recipe_rows']) === 2, 'Both recipe rows are required.');
$check(! isset($layout['tolerance_rows']), 'Unknown tolerance readings must not be fabricated.');
$check(count($layout['runs']) === 2, 'Legacy totals must use the same capacity split as reconciliation.');
$check($layout['runs'][0]['MSAND 1'] === 369.5, 'Legacy actual must be split using run volume.');
$check(substr_count($html, 'data:image/png;base64,'.base64_encode($logoBytes)) === 3, 'Configured logo must appear in both headers and footer.');
$check(! str_contains($html, 'SCHWING'), 'Fixed manufacturer branding must be replaced.');
$check($layout['recipe_rows'][1]['MSAND 1'] === 372.0, 'Second recipe row must use target weight per batch volume.');
$doc = new DOMDocument;
@$doc->loadHTML($html);
$xpath = new DOMXPath($doc);
$check($xpath->query('//table[@id="tbl"]//thead/tr')->length === 2, 'Material group and column headers are required.');
$fixture = base_path('tmp/pdfs/exact-batch-sheet.html');
if (! is_dir(dirname($fixture))) {
    mkdir(dirname($fixture), 0775, true);
}
file_put_contents($fixture, $html);
$pdf = in_array('--html-only', $argv, true) ? null : app(BatchSheetBrowserPdf::class)->render($html);
$output = base_path('output/pdf/jayadhitya-groups-batch-sheet.pdf');
if (! is_dir(dirname($output))) {
    mkdir(dirname($output), 0775, true);
}
if ($pdf !== null) file_put_contents($output, $pdf);

// Duplicate runs aggregate by material while retaining each actual run.
$materials->push(new BatchMaterial(['material_name' => 'MSAND 1 - Run 2', 'target_qty' => 100, 'actual_qty' => 101]));
$materials->push(new BatchMaterial(['material_name' => 'SPECIAL FILLER', 'target_qty' => 10, 'actual_qty' => 11]));
$report = JayadhityaBatchSheet::data($batch);
$check($report['actuals']['MSAND 1'] === 840.0, 'Repeated material not summed.');
$check($report['runs'][2]['MSAND 1'] === 101.0, 'Stored run missing.');
$check(in_array('SPECIAL FILLER', $report['columns'], true), 'Unmapped material was dropped.');
$check(abs($report['total_actual'] - 5061.02) < 0.001, 'Unmapped material excluded from total.');
$layout = JayadhityaBatchSheet::layoutData($batch);
$check(count($layout['columns']) === 9, 'Extra materials must add their own columns.');
$check(in_array('SPECIAL FILLER', $layout['columns'], true), 'An unknown material must remain identified in a free slot.');
$check(abs(array_sum($layout['actuals']) - 5061.02) < 0.001, 'Dynamic columns must conserve every stored weight.');
// More materials than the old fixed Aggregate capacity must all remain visible.
foreach (range(1, 8) as $index) {
    $materials->push(new BatchMaterial(['material_name' => 'FILLER '.$index, 'target_qty' => 1, 'actual_qty' => 2]));
}
$layout = JayadhityaBatchSheet::layoutData($batch);
$check(count($layout['columns']) === 17, 'Dynamic layout must not reject extra consumption columns.');
$check(abs(array_sum($layout['actuals']) - 5077.02) < 0.001, 'All extra consumption weights must be retained.');
$plant->id = 740;
$plant->logo_path = $logoPath;
session(['active_plant_id' => 739]);
$plant->name = 'Other Plant';
$plant->code = '121';
$check(! JayadhityaBatchSheet::matches($batch), 'Other plant selected this template.');
$html = view('pdfs.batches.batch_sheet', ['batch' => $batch, 'isPreview' => true])->render();
$check(str_contains($html, 'data:image/png;base64,'.base64_encode($logoBytes)), 'Missing custom setting must fall back to plant logo.');
$plant->logo_path = null;
$plant->setRelation('entity', new App\Models\Entity(['logo_file' => $logoPath]));
$entityHtml = view('pdfs.batches.batch_sheet', ['batch' => $batch, 'isPreview' => true])->render();
$check(str_contains($entityHtml, 'data:image/png;base64,'.base64_encode($logoBytes)), 'Missing plant logo must fall back to entity logo.');
$plant->setRelation('entity', null);
$htmlWithoutLogo = view('pdfs.batches.batch_sheet', ['batch' => $batch, 'isPreview' => true])->render();
$check(! str_contains($htmlWithoutLogo, 'data:image/png;base64'), 'Another active plant must not supply its custom logo.');
$check(str_contains($html, 'Other Plant'), 'Default template must use the selected batch plant without a template parameter.');
$batch->unsetRelation('salesOrder');
$html = view('pdfs.batches.jayadhitya_groups', ['batch' => $batch, 'isPreview' => true, 'token' => 'sample-public-link'])->render();
$check(str_contains($html, 'Other Plant'), 'Public work-order relation was not used.');
$check(! str_contains($html, '>Back to batches</a>'), 'Public preview must not show private batch navigation.');
// Persisted totals must exclude other plants, orders, cancelled loads and later loads.
Illuminate\Support\Facades\Schema::create('mm_batches', function ($table) {
    $table->id();
    $table->unsignedBigInteger('plant_id');
    $table->unsignedBigInteger('sales_order_id');
    $table->integer('status');
    $table->decimal('batch_size', 10, 2);
    $table->softDeletes();
});
foreach ([[1, 739, 10, 4, 3], [2, 739, 10, 3, 2], [3, 740, 10, 4, 100],
    [4, 739, 11, 4, 100], [5, 739, 10, 5, 100], [6, 739, 10, 1, 100], [8, 739, 10, 4, 100]] as $row) {
    Illuminate\Support\Facades\DB::table('mm_batches')->insert(array_combine(['id', 'plant_id', 'sales_order_id', 'status', 'batch_size'], $row));
}
$batch->id = 7;
$batch->plant_id = 739;
$batch->sales_order_id = 10;
$batch->exists = true;
session(['active_plant_id' => 740]);
$check((float) JayadhityaBatchSheet::layoutData($batch)['with_this_load'] === 5.0, 'Cumulative load quantity must come from the batch plant and order.');
$batch->setRelation('materials', collect());
$emptyHtml = view('pdfs.batches.batch_sheet', ['batch' => $batch])->render();
$check(str_contains($emptyHtml, 'No consumption records stored'), 'Empty consumption must be explicit.');
// A 6 m3 legacy load with the editor's 1.25 m3 default must show five matching runs.
$legacy = new Batch(['batch_size' => 6]);
$legacy->setRelation('plant', (new Plant)->setRelation('entity', null));
$legacy->setRelation('salesOrder', null);
$legacy->setRelation('materials', collect([(new BatchMaterial(['material_name' => 'Fine Sand', 'target_qty' => 4260, 'actual_qty' => 4260]))->setRelation('product', null)]));
$legacyReport = JayadhityaBatchSheet::layoutData($legacy);
$check(count($legacyReport['runs']) === 5, 'Print must show all five reconciliation runs.');
$check(array_column($legacyReport['runs'], 'Fine Sand') === [887.5, 887.5, 887.5, 887.5, 710.0], 'Print actual rows must equal the editor values.');
Illuminate\Support\Facades\Schema::create('mm_products', function ($table) {
    $table->id(); $table->unsignedBigInteger('plant_id'); $table->string('title'); $table->softDeletes();
});
Illuminate\Support\Facades\Schema::create('mm_batch_materials', function ($table) {
    $table->id(); $table->unsignedBigInteger('plant_id'); $table->unsignedBigInteger('batch_id');
    $table->unsignedBigInteger('product_id'); $table->string('material_name');
    $table->decimal('target_qty', 12, 3); $table->decimal('actual_qty', 12, 3); $table->decimal('deviation_quantity', 12, 3);
    $table->unsignedBigInteger('uom_id')->nullable(); $table->timestamps(); $table->softDeletes();
});
Illuminate\Support\Facades\DB::table('mm_products')->insert(['id' => 1, 'plant_id' => 739, 'title' => 'Cement']);
session(['active_plant_id' => 739]);
$sync = new ReflectionMethod(App\Http\Controllers\BatchController::class, 'syncMaterials');
BatchMaterial::withoutEvents(fn () => $sync->invoke(app(App\Http\Controllers\BatchController::class), $batch, [
    ['product_id' => 1, 'material_name' => 'CEMENT 1', 'target_qty' => 30, 'actual_qty' => 33, 'runs' => [10, 11, 12], 'uom_id' => null],
]));
$saved = $batch->materials()->orderBy('id')->get();
$check($saved->count() === 3 && (float) $saved->sum('actual_qty') === 33.0 && (float) $saved->sum('target_qty') === 30.0, 'Saving must preserve each run and both totals.');
$check($saved->pluck('material_name')->all() === ['CEMENT 1 - Run 1', 'CEMENT 1 - Run 2', 'CEMENT 1 - Run 3'], 'Saved runs need stable identifiers.');
echo "PASS: print/editor legacy runs, saved run persistence, dynamic materials, totals, branding and layout.\n";
echo ($pdf !== null ? $output : $fixture).PHP_EOL;
