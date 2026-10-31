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

$check = function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};
$plant = new Plant(['name' => 'JAYADHITYA GROUPS', 'code' => '739', 'plant_type' => 'M1 C', 'mixer_capacity' => 1]);
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
    $materials->push(new BatchMaterial(['product_id' => $i + 1, 'material_name' => $name, 'target_qty' => $target, 'actual_qty' => $actual]));
    $recipe->push(new MixDesignItem(['product_id' => $i + 1, 'actual_quantity' => $quantity]));
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
$check(count($layout['columns']) === 20, 'Exact template must keep 20 columns.');
$check(count($layout['recipe_rows']) === 2, 'Both recipe rows are required.');
$check(count($layout['tolerance_rows']) === 2, 'Both tolerance rows are required.');
$check(count($layout['runs']) === 2 && $layout['runs'][1] === null, 'Missing second run must retain its row without invented values.');
$check($layout['recipe_rows'][1]['MSAND 1'] === 372.0, 'Second recipe row must use target weight per batch volume.');
$doc = new DOMDocument;
@$doc->loadHTML($html);
$xpath = new DOMXPath($doc);
$check($xpath->query('//table[@id="tbl"]//tr')->length === 18, 'The complete source table row structure must be retained.');
$fixture = base_path('tmp/pdfs/exact-batch-sheet.html');
if (! is_dir(dirname($fixture))) {
    mkdir(dirname($fixture), 0775, true);
}
file_put_contents($fixture, $html);
$pdf = app(BatchSheetBrowserPdf::class)->render($html);
$output = base_path('output/pdf/jayadhitya-groups-batch-sheet.pdf');
if (! is_dir(dirname($output))) {
    mkdir(dirname($output), 0775, true);
}
file_put_contents($output, $pdf);

// Duplicate runs aggregate by material while retaining each actual run.
$materials->push(new BatchMaterial(['material_name' => 'MSAND 1 - Run 2', 'target_qty' => 100, 'actual_qty' => 101]));
$materials->push(new BatchMaterial(['material_name' => 'SPECIAL FILLER', 'target_qty' => 10, 'actual_qty' => 11]));
$report = JayadhityaBatchSheet::data($batch);
$check($report['actuals']['MSAND 1'] === 840.0, 'Repeated material not summed.');
$check($report['runs'][2]['MSAND 1'] === 101.0, 'Stored run missing.');
$check(in_array('SPECIAL FILLER', $report['columns'], true), 'Unmapped material was dropped.');
$check(abs($report['total_actual'] - 5061.02) < 0.001, 'Unmapped material excluded from total.');
$layout = JayadhityaBatchSheet::layoutData($batch);
$check(count($layout['columns']) === 20, 'Extra materials must use existing slots, never add columns.');
$check(in_array('SPECIAL FILLER', $layout['labels'], true), 'An unknown material must remain identified in a free slot.');
$check(abs(array_sum($layout['actuals']) - 5061.02) < 0.001, 'Fixed column mapping must conserve every stored weight.');
$plant->name = 'Other Plant';
$plant->code = '121';
$check(! JayadhityaBatchSheet::matches($batch), 'Other plant selected this template.');
$html = view('pdfs.batches.batch_sheet', ['batch' => $batch, 'isPreview' => true])->render();
$check(str_contains($html, 'Other Plant'), 'Default template must use the selected batch plant without a template parameter.');
$batch->unsetRelation('salesOrder');
$html = view('pdfs.batches.jayadhitya_groups', ['batch' => $batch, 'isPreview' => true, 'token' => 'sample-public-link'])->render();
$check(str_contains($html, 'Other Plant'), 'Public work-order relation was not used.');
$check(! str_contains($html, '>Back to batches</a>'), 'Public preview must not show private batch navigation.');
echo "PASS: selection, stored weights, recipe, percentages, run grouping, unmapped materials and real Blade/PDF render.\n";
echo $output.PHP_EOL;
