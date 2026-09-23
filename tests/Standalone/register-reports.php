<?php

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $error) { fwrite(STDERR, (string) $error); exit(1); });

use App\Services\Reports\SalesRegisterService;
use App\Services\Reports\PurchaseRegisterService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;

// No application data is written: every fixture lives in an isolated SQLite DB.
config(['database.default' => 'register_test', 'database.connections.register_test' => [
    'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
], 'cache.default' => 'array', 'session.driver' => 'array', 'logging.default' => 'null']);
session(['active_plant_id' => 1]);

function checkRegister(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$tables = [
    'mm_plants' => 'id INTEGER, gstin TEXT, deleted_at TEXT',
    'mm_patrons' => 'id INTEGER, plant_id INTEGER, legal_name TEXT, gstin TEXT, patron_type TEXT, deleted_at TEXT',
    'mm_dispatches' => 'id INTEGER, plant_id INTEGER, customer_id INTEGER, sales_order_id INTEGER, truck_id INTEGER, unload_site_id INTEGER, payment_mode TEXT, deleted_at TEXT',
    'mm_sales_orders' => 'id INTEGER, plant_id INTEGER, customer_id INTEGER, deleted_at TEXT',
    'mm_sites' => 'id INTEGER, plant_id INTEGER, name TEXT, deleted_at TEXT',
    'mm_machines' => 'id INTEGER, plant_id INTEGER, registration TEXT, deleted_at TEXT',
    'mm_users' => 'id INTEGER, username TEXT, email TEXT, deleted_at TEXT',
    'mm_product_units' => 'id INTEGER, unit_name TEXT, unit_code TEXT, deleted_at TEXT',
    'mm_products' => 'id INTEGER, plant_id INTEGER, title TEXT, deleted_at TEXT',
    'mm_taxes' => 'id INTEGER, plant_id INTEGER, tax_name TEXT, tax_rate REAL, tax_group TEXT, deleted_at TEXT',
    'mm_invoices' => 'id INTEGER, plant_id INTEGER, prefix TEXT, invoice_number TEXT, invoice_date TEXT, partner_id INTEGER, status TEXT, paid_amount REAL, balance_amount REAL, created_by INTEGER, invoice_label TEXT, invoice_type TEXT, ref_id INTEGER, deleted_at TEXT',
    'mm_invoice_items' => 'id INTEGER, invoice_id INTEGER, item_id INTEGER, uom_id INTEGER, item_name TEXT, hsn_code TEXT, quantity REAL, price_unit REAL, subtotal REAL, line_tax_amount REAL, line_total REAL, deleted_at TEXT',
    'mm_order_taxes' => 'id INTEGER, order_id INTEGER, order_items_id INTEGER, order_type TEXT, name TEXT, rate REAL, amount REAL, deleted_at TEXT',
    'mm_einvoice_invoice_rel' => 'id INTEGER, invoice_id INTEGER, einv_irn TEXT, einv_ack_date TEXT, einv_cancel_at TEXT, einv_status TEXT',
    'mm_purchase_orders' => 'id INTEGER, plant_id INTEGER, po_number TEXT, bill_number TEXT, date_order TEXT, billed_date TEXT, created_at TEXT, vendor_id INTEGER, created_by INTEGER, state TEXT, deleted_at TEXT',
    'mm_purchase_order_items' => 'id INTEGER, order_id INTEGER, product_id INTEGER, product_uom INTEGER, tax_id INTEGER, hsn_code TEXT, description TEXT, product_quantity REAL, unit_price REAL, price_subtotal REAL, price_tax REAL, price_total REAL, deleted_at TEXT',
];
foreach ($tables as $table => $columns) DB::statement("CREATE TABLE $table ($columns)");
DB::table('mm_plants')->insert([['id' => 1, 'gstin' => '33TEST'], ['id' => 2, 'gstin' => '29TEST']]);
DB::table('mm_patrons')->insert([
    ['id' => 1, 'plant_id' => 1, 'legal_name' => '=SUM(1,2)', 'gstin' => '33VENDOR'],
    ['id' => 2, 'plant_id' => 1, 'legal_name' => 'Interstate Party', 'gstin' => '29VENDOR'],
]);
DB::table('mm_users')->insert(['id' => 1, 'username' => 'Test', 'email' => 'test@example.test']);
DB::table('mm_product_units')->insert(['id' => 1, 'unit_name' => 'Cubic metre', 'unit_code' => 'M3']);
DB::table('mm_products')->insert(['id' => 1, 'plant_id' => 1, 'title' => 'Concrete']);
DB::table('mm_taxes')->insert([
    ['id' => 1, 'plant_id' => 1, 'tax_name' => 'GST 18%', 'tax_rate' => 18, 'tax_group' => 'GST'],
    ['id' => 2, 'plant_id' => 1, 'tax_name' => 'IGST 5%', 'tax_rate' => 5, 'tax_group' => 'IGST'],
]);
foreach ([1, 2, 3, 4, 5] as $id) {
    DB::table('mm_invoices')->insert([
        'id' => $id, 'plant_id' => $id === 4 ? 2 : 1, 'prefix' => 'INV/',
        'invoice_number' => $id === 1 ? 'INV/001' : '00'.$id,
        'invoice_date' => '2026-09-01', 'partner_id' => 1,
        'status' => $id === 3 ? 'Cancelled' : ($id === 2 ? 'Paid' : 'Approved'),
        'paid_amount' => $id === 2 ? 105 : 0, 'balance_amount' => $id === 2 ? 0 : 118,
        'created_by' => 1, 'invoice_label' => 'Sales', 'invoice_type' => 'sales',
        'deleted_at' => $id === 5 ? '2026-09-02' : null,
    ]);
}
DB::table('mm_einvoice_invoice_rel')->insert(['id' => 1, 'invoice_id' => 1, 'einv_irn' => str_repeat('a', 64), 'einv_ack_date' => '2026-09-01 10:00:00']);
foreach ([1 => 1, 2 => 1, 3 => 2, 4 => 3, 5 => 4, 6 => 5] as $id => $invoice) {
    $tax = $invoice === 2 ? 5 : 18;
    DB::table('mm_invoice_items')->insert([
        'id' => $id, 'invoice_id' => $invoice, 'item_id' => 1, 'uom_id' => 1,
        'item_name' => 'Concrete', 'hsn_code' => '003824', 'quantity' => 2, 'price_unit' => 50,
        'subtotal' => 100, 'line_tax_amount' => $tax, 'line_total' => 100 + $tax,
    ]);
    foreach ($invoice === 2 ? ['IGST' => 5] : ['CGST' => 9, 'SGST' => 9] as $name => $amount) {
        DB::table('mm_order_taxes')->insert(['id' => $id * 10 + ($name === 'SGST' ? 1 : 0),
            'order_id' => $invoice, 'order_items_id' => $id, 'order_type' => 'Invoice',
            'name' => $name, 'rate' => $amount, 'amount' => $amount]);
    }
}
foreach ([1, 2, 3, 4, 5] as $id) {
    DB::table('mm_purchase_orders')->insert([
        'id' => $id, 'plant_id' => $id === 5 ? 2 : 1, 'po_number' => 'PO/'.$id, 'bill_number' => '00'.$id,
        'date_order' => '2026-09-01', 'billed_date' => $id === 3 ? '2026-08-31' : ($id === 4 ? null : '2026-09-01'),
        'created_at' => '2026-09-02', 'vendor_id' => 1, 'created_by' => 1, 'state' => $id === 4 ? 'cancel' : 'approved',
    ]);
    DB::table('mm_purchase_order_items')->insert([
        'id' => $id, 'order_id' => $id, 'product_id' => 1, 'product_uom' => 1, 'tax_id' => $id === 2 ? 2 : 1,
        'hsn_code' => '003824', 'description' => 'Concrete', 'product_quantity' => 2, 'unit_price' => 50,
        'price_subtotal' => 100, 'price_tax' => $id === 2 ? 5 : 18.01, 'price_total' => $id === 2 ? 105 : 118.01,
    ]);
}
$sales = app(SalesRegisterService::class);
$purchase = app(PurchaseRegisterService::class);
$filters = ['from_date' => '2026-09-01', 'to_date' => '2026-09-01', 'per_page' => 1];
$first = $sales->generate($filters);
$second = $sales->generate($filters + ['page' => 2]);
checkRegister($first['pagination']['total'] === 3 && $second['data'][0]['id'] === 2, 'Pagination or plant/deletion/status scoping failed');
checkRegister($first['data'][0]['invoice_no'] === 'INV/001', 'Duplicated invoice prefix');
checkRegister($first['data'][0]['hsn_code'] === '003824' && $first['data'][0]['unit'] === 'M3', 'Item details missing');
checkRegister($first['totals']['grand_total'] === 341.0, 'Sales totals wrong');
checkRegister($first['totals']['taxes'] === $second['totals']['taxes'], 'Totals change between pages');
$standardTaxLabels = ['CGST 2.5%', 'SGST 2.5%', 'CGST 6%', 'SGST 6%', 'CGST 9%', 'SGST 9%',
    'CGST 14%', 'SGST 14%', 'IGST 5%', 'IGST 12%', 'IGST 18%', 'IGST 28%'];
checkRegister(array_column($first['tax_columns'], 'label') === $standardTaxLabels, 'GST pairs or full IGST rate columns missing/out of order');
$summary = $sales->generate($filters + ['register_view' => 'summary']);
checkRegister($summary['pagination']['total'] === 2 && $summary['data'][0]['net_amount'] === 236.0, 'Summary duplicates multi-item invoices');
checkRegister($summary['data'][0]['taxes']['CGST_9.00'] === 18.0, 'Summary tax rates not aggregated');
checkRegister($sales->generate($filters + ['payment_status' => 'paid'])['pagination']['total'] === 1, 'Paid filter wrong');
checkRegister($sales->generate($filters + ['gst_type' => 'inter'])['pagination']['total'] === 1, 'Sales GST filter wrong');
checkRegister($sales->generate($filters + ['document_status' => 'cancelled'])['pagination']['total'] === 1, 'Cancelled filter wrong');
checkRegister($sales->generate($filters + ['customer_id' => 2])['pagination']['total'] === 0, 'Customer filter ignored');
$bills = $purchase->generate($filters);
checkRegister($bills['pagination']['total'] === 2 && $bills['totals']['grand_total'] === 223.01, 'Bill date or purchase totals wrong');
checkRegister(round(array_sum($bills['totals']['taxes']), 2) === 23.01, 'Odd-paisa split does not reconcile');
checkRegister($purchase->generate($filters + ['gst_type' => 'inter'])['data'][0]['bill_no'] === '002', 'Explicit IGST group ignored by filter');
checkRegister($purchase->generate($filters + ['gst_type' => 'intra'])['pagination']['total'] === 1, 'Intra filter wrong');
checkRegister($purchase->generate($filters + ['supplier_id' => 2])['pagination']['total'] === 0, 'Supplier filter ignored');
DB::table('mm_users')->where('id', 1)->update(['email' => null]);
checkRegister($sales->generate($filters)['data'][0]['created_by'] === 'Test', 'Sales creator username fallback missing');
checkRegister($purchase->generate($filters)['data'][0]['created_by'] === 'Test', 'Purchase creator username fallback missing');
DB::table('mm_users')->where('id', 1)->update(['email' => 'test@example.test']);
session(['active_plant_id' => 2]);
checkRegister($sales->generate($filters)['totals']['grand_total'] === 118.0, 'Another plant reused totals');
session(['active_plant_id' => 1]);
$empty = $sales->generate(['from_date' => '2025-01-01', 'to_date' => '2025-01-01']);
checkRegister($empty['pagination']['total'] === 0 && $empty['totals']['grand_total'] === 0.0, 'Empty period returns unrelated data');
foreach ([$sales, $purchase] as $service) {
    foreach (['summary', 'detail'] as $view) {
        $schema = $service->buildReport(['from_date' => '2025-01-01', 'to_date' => '2025-01-01', 'register_view' => $view]);
        checkRegister(array_column($schema['tax_columns'], 'label') === $standardTaxLabels, 'Empty report lost standard GST columns');
        checkRegister(!array_intersect(['cgst', 'sgst', 'utgst', 'igst'], array_column($schema['columns'], 'key')), 'Screen includes unwanted component total columns');
    }
}

// Endpoint validation must keep the requested page and reject a foreign plant.
$controller = new class extends App\Http\Controllers\ReportController {
    protected function authorizeReport(string $type, string $action = 'view', array $params = []): void {}
};
foreach (['salesRegister' => $sales, 'purchaseRegister' => $purchase] as $method => $service) {
    $response = $controller->$method(Request::create('/', 'GET', $filters + ['page' => 2]), $service)->getData(true);
    checkRegister($response['pagination']['current_page'] === 2, 'Controller discarded page');
    try {
        $controller->$method(Request::create('/', 'GET', $filters + ['plant_id' => 2]), $service);
        throw new RuntimeException('Cross-plant request accepted');
    } catch (Symfony\Component\HttpKernel\Exception\HttpException $e) {
        checkRegister($e->getStatusCode() === 403, 'Wrong plant rejection');
    }
    try {
        $controller->$method(Request::create('/', 'GET', ['from_date' => '2026-09-02', 'to_date' => '2026-09-01']), $service);
        throw new RuntimeException('Reversed date range accepted');
    } catch (Illuminate\Validation\ValidationException $e) {}
}

$directory = sys_get_temp_dir().'/register-report-tests-'.bin2hex(random_bytes(4));
mkdir($directory);
foreach (['sales' => $sales, 'purchase' => $purchase] as $kind => $service) {
    foreach (['summary', 'detail'] as $view) {
        $file = "$directory/$kind-$view.xlsx";
        $service->generateAndSaveReport('excel', $filters + ['register_view' => $view], $file);
        $sheet = IOFactory::load($file)->getActiveSheet();
        checkRegister(str_contains($sheet->getCell('A2')->getValue(), '2026-09-01 to 2026-09-01'), 'Excel period lost');
        checkRegister($sheet->getCell('B5')->getDataType() === 's' && $sheet->getCell('B5')->getValue() === '=SUM(1,2)', 'Party name became a formula');
        checkRegister($sheet->getCell('A5')->getDataType() === 'n', 'Excel date is not typed');
        checkRegister($sheet->getCell('E5')->getDataType() === 's', 'Document identifier lost text format');
        $expectedRows = $kind === 'sales' && $view === 'detail' ? 3 : 2;
        checkRegister($sheet->getHighestRow() === $expectedRows + 5, 'Export only contains the first page');
        $headings = $sheet->rangeToArray('A4:'.$sheet->getHighestColumn().'4')[0];
        checkRegister(in_array('CGST 9%', $headings) && in_array('IGST 5%', $headings), 'Excel tax columns missing');
        $service->generateAndSaveReport('pdf', $filters + ['register_view' => $view], "$directory/$kind-$view.pdf");
        checkRegister(str_starts_with(file_get_contents("$directory/$kind-$view.pdf"), '%PDF-'), 'Invalid PDF');
    }
}
// A single document spanning multiple chunks must remain a single summary row.
for ($id = 100; $id < 605; $id++) {
    DB::table('mm_invoice_items')->insert(['id' => $id, 'invoice_id' => 1, 'item_id' => 1, 'uom_id' => 1,
        'item_name' => 'Extra item', 'quantity' => 1, 'price_unit' => 1, 'subtotal' => 1, 'line_tax_amount' => 0, 'line_total' => 1]);
}
$chunked = $sales->generate($filters + ['register_view' => 'summary']);
checkRegister($chunked['pagination']['total'] === 2 && $chunked['data'][0]['net_amount'] === 741.0, 'Document split at chunk boundary');
DB::statement('CREATE TABLE mm_menus (id INTEGER PRIMARY KEY AUTOINCREMENT, menutype INTEGER, title TEXT, alias TEXT, link TEXT, icon TEXT, published INTEGER, parent_id INTEGER, level INTEGER, ordering INTEGER, permission_name TEXT, created_at TEXT, updated_at TEXT, deleted_at TEXT)');
DB::table('mm_menus')->insert(['id' => 10, 'menutype' => 1, 'alias' => 'report', 'title' => 'Report']);
$migration = require __DIR__.'/../../database/migrations/2026_09_23_120000_add_register_report_menus.php';
$migration->up();
$migration->up();
checkRegister(DB::table('mm_menus')->where('parent_id', 10)->count() === 2, 'Menu migration missing or duplicated register entries');
checkRegister(DB::table('mm_menus')->where('permission_name', 'REPORT.VIEW')->count() === 2, 'Register menu permission incorrect');
$detailMenu = require __DIR__.'/../../database/migrations/2026_09_23_130000_add_detailed_sales_register_menu.php';
$detailMenu->up();
$detailMenu->up();
checkRegister(DB::table('mm_menus')->where('parent_id', 10)->count() === 3, 'Detailed report menu missing or duplicated');
checkRegister(DB::table('mm_menus')->where('alias', 'detailed-sales-register-report')->value('link') === 'reports/report?type=sales_register&register_view=detail', 'Menu does not open detailed layout');
$detailMenu->down();
$migration->down();
checkRegister(DB::table('mm_menus')->count() === 1, 'Menu rollback changed unrelated menu entries');
$workbook = app(App\Services\Reports\ExcelExportService::class)->generateExcelReport('sales_register', '2026-09-01', '2026-09-01', $summary);
checkRegister($workbook->getActiveSheet()->getCell('A1')->getValue() === 'Sales Register - Summary', 'Scheduled export format not integrated');
$workbook->disconnectWorksheets();

// Exercise every GST family and multiple rates through both new and legacy exports.
$splits = [
    ['group' => 'GST', 'rate' => 5, 'taxes' => ['CGST_2.50' => 2.5, 'SGST_2.50' => 2.5]],
    ['group' => 'GST', 'rate' => 12, 'taxes' => ['CGST_6.00' => 6, 'SGST_6.00' => 6]],
    ['group' => 'GST', 'rate' => 18, 'taxes' => ['CGST_9.00' => 9, 'SGST_9.00' => 9]],
    ['group' => 'GST', 'rate' => 28, 'taxes' => ['CGST_14.00' => 14, 'SGST_14.00' => 14]],
    ['group' => 'UTGST', 'rate' => 9, 'taxes' => ['UTGST_9.00' => 9]],
    ['group' => 'IGST', 'rate' => 18, 'taxes' => ['IGST_18.00' => 18]],
    ['group' => 'IGST', 'rate' => 5, 'taxes' => ['IGST_5.00' => 5]],
    ['group' => 'IGST', 'rate' => 12, 'taxes' => ['IGST_12.00' => 12]],
    ['group' => 'IGST', 'rate' => 28, 'taxes' => ['IGST_28.00' => 28]],
    ['group' => 'IGST', 'rate' => 12, 'taxes' => ['IGST_12.00' => -12]],
];
$expectedTaxTotals = [];
foreach ($splits as $i => $split) {
    $id = 800 + $i;
    $amount = array_sum($split['taxes']);
    $taxable = $amount < 0 ? -100 : 100;
    DB::table('mm_taxes')->insert(['id' => $id, 'plant_id' => 1, 'tax_name' => $split['group'].' '.$split['rate'].'%', 'tax_rate' => $split['rate'], 'tax_group' => $split['group']]);
    DB::table('mm_invoices')->insert(['id' => $id, 'plant_id' => 1, 'invoice_number' => 'GST-'.$id, 'invoice_date' => '2026-09-03', 'partner_id' => 1, 'status' => 'Approved', 'invoice_label' => 'Sales', 'invoice_type' => 'sales']);
    DB::table('mm_invoice_items')->insert(['id' => $id, 'invoice_id' => $id, 'item_id' => 1, 'uom_id' => 1,
        'item_name' => 'Multi-rate test', 'quantity' => 1, 'price_unit' => $taxable, 'subtotal' => $taxable,
        'line_tax_amount' => $amount, 'line_total' => $taxable + $amount]);
    foreach ($split['taxes'] as $key => $value) {
        [$name, $rate] = explode('_', $key);
        DB::table('mm_order_taxes')->insert(['id' => $id * 10 + ($name === 'SGST' ? 1 : 0),
            'order_id' => $id, 'order_items_id' => $id, 'order_type' => 'Invoice', 'name' => $name, 'rate' => $rate, 'amount' => $value]);
        $expectedTaxTotals[$key] = ($expectedTaxTotals[$key] ?? 0) + $value;
    }
    DB::table('mm_purchase_orders')->insert(['id' => $id, 'plant_id' => 1, 'bill_number' => 'BILL-'.$id,
        'date_order' => '2026-09-03', 'billed_date' => '2026-09-03', 'vendor_id' => 1, 'state' => 'approved']);
    DB::table('mm_purchase_order_items')->insert(['id' => $id, 'order_id' => $id, 'product_id' => 1, 'product_uom' => 1,
        'tax_id' => $id, 'product_quantity' => 1, 'unit_price' => $taxable, 'price_subtotal' => $taxable,
        'price_tax' => $amount, 'price_total' => $taxable + $amount]);
}
$gstFilters = ['from_date' => '2026-09-03', 'to_date' => '2026-09-03', 'per_page' => 1];
$parser = new Smalot\PdfParser\Parser;
foreach (['sales' => $sales, 'purchase' => $purchase] as $kind => $service) {
    $report = $service->buildReport($gstFilters, true);
    foreach ($expectedTaxTotals as $key => $amount) {
        checkRegister(($report['totals']['taxes'][$key] ?? null) === (float) $amount, "$kind dropped rate $key");
    }
    checkRegister($report['totals']['cgst'] === 31.5 && $report['totals']['sgst'] === 31.5
        && $report['totals']['utgst'] === 9.0 && $report['totals']['igst'] === 51.0, "$kind component totals don't reconcile");
    foreach ($report['data'] as $row) {
        $expected = $splits[$row['id'] - 800]['taxes'];
        checkRegister($row['taxes'] == $expected, "$kind GST split or IGST rate changed on item ".$row['id']);
    }
    foreach (['summary', 'detail', 'legacy'] as $view) {
        $prefix = "$directory/$kind-gst-$view";
        if ($view === 'legacy') {
            $repository = app(App\Repositories\ReportRepository::class);
            $query = $kind === 'sales' ? $repository->getSalesRegisterQuery($gstFilters) : $repository->getPurchaseRegisterQuery($gstFilters);
            $exporter = $kind === 'sales' ? new App\Exports\SalesRegisterExport($query) : new App\Exports\PurchaseRegisterExport($query);
            $exporter->export($prefix.'.xlsx', 'GST test period');
            Barryvdh\DomPDF\Facade\Pdf::loadView('reports.'.$kind.'_register_pdf', [
                'items' => $report['data'], 'filters' => $gstFilters,
            ])->setPaper('a3', 'landscape')->save($prefix.'.pdf');
        } else {
            $service->generateAndSaveReport('excel', $gstFilters + ['register_view' => $view], $prefix.'.xlsx');
            $service->generateAndSaveReport('pdf', $gstFilters + ['register_view' => $view], $prefix.'.pdf');
        }
        $book = IOFactory::load($prefix.'.xlsx');
        $sheet = $book->getActiveSheet();
        $headers = $sheet->rangeToArray('A4:'.$sheet->getHighestColumn().'4')[0];
        $pdfText = preg_replace('/\s+/', ' ', $parser->parseFile($prefix.'.pdf')->getText());
        $rateHeaders = array_values(array_filter($headers, fn ($label) => in_array($label, $standardTaxLabels, true)));
        checkRegister($rateHeaders === $standardTaxLabels, "$kind $view Excel GST columns out of order");
        foreach ($expectedTaxTotals as $key => $amount) {
            [$type, $rate] = explode('_', $key);
            $label = $type.' '.(float) $rate.'%';
            $column = array_search($label, $headers, true);
            checkRegister($column !== false, "$kind $view Excel missing $label");
            $letter = PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($column + 1);
            checkRegister((float) $sheet->getCell($letter.$sheet->getHighestRow())->getValue() === (float) $amount, "$kind $view Excel total wrong for $label");
            $sum = 0;
            for ($r = 5; $r < $sheet->getHighestRow(); $r++) $sum += (float) $sheet->getCell($letter.$r)->getValue();
            checkRegister(round($sum, 2) === (float) $amount, "$kind $view Excel row splits wrong for $label");
            checkRegister(str_contains($pdfText, $label), "$kind $view PDF missing $label");
        }
        foreach (['CGST Total', 'SGST Total', 'UTGST Total', 'IGST Total'] as $label) {
            checkRegister(!in_array($label, $headers, true) && !str_contains($pdfText, $label), "$kind $view still includes unwanted $label");
        }
        checkRegister(str_contains($pdfText, 'GST splits by rate (3/3)'), "$kind $view wide GST continuation missing");
        checkRegister(str_contains($pdfText, '-12.00'), "$kind $view PDF lost negative tax split");
        $book->disconnectWorksheets();
    }
}
// The attached detailed sales layout: dispatch data, historical tax names, TCS and audit metadata.
DB::table('mm_patrons')->where('id', 2)->update(['patron_type' => '["Customer"]']);
DB::table('mm_sales_orders')->insert(['id' => 900, 'plant_id' => 1, 'customer_id' => 2]);
DB::table('mm_sites')->insert(['id' => 900, 'plant_id' => 1, 'name' => 'PARTY SITE']);
DB::table('mm_machines')->insert(['id' => 900, 'plant_id' => 1, 'registration' => 'TN10BF9876']);
DB::table('mm_dispatches')->insert(['id' => 900, 'plant_id' => 1, 'sales_order_id' => 900, 'unload_site_id' => 900, 'truck_id' => 900, 'payment_mode' => 'cash']);
DB::table('mm_dispatches')->insert(['id' => 901, 'plant_id' => 2, 'payment_mode' => 'credit']);
DB::table('mm_dispatches')->insert(['id' => 903, 'plant_id' => 1, 'payment_mode' => 'cash', 'deleted_at' => '2026-09-04']);
foreach ([900, 901, 902, 903] as $id) {
    DB::table('mm_invoices')->insert(['id' => $id, 'plant_id' => 1, 'prefix' => 'INV/', 'invoice_number' => (string) $id,
        'invoice_date' => '2026-09-04', 'status' => 'Approved', 'invoice_label' => $id === 902 ? 'Sales' : 'Dispatch',
        'invoice_type' => 'sales', 'ref_id' => $id === 902 ? 900 : $id, 'created_by' => 1]);
    DB::table('mm_invoice_items')->insert(['id' => $id, 'invoice_id' => $id, 'item_id' => 1, 'uom_id' => 1,
        'item_name' => 'Concrete', 'hsn_code' => '003824', 'quantity' => 2, 'price_unit' => 50,
        'subtotal' => 100, 'line_tax_amount' => 19, 'line_total' => 119]);
    foreach (['CGST' => 9, 'SGST' => 9, 'TCS' => 1] as $name => $amount) {
        DB::table('mm_order_taxes')->insert(['id' => $id * 10 + ['CGST' => 0, 'SGST' => 1, 'TCS' => 2][$name],
            'order_id' => $id, 'order_items_id' => $id, 'order_type' => 'Invoice', 'name' => $name, 'rate' => $amount, 'amount' => $amount]);
    }
}
DB::table('mm_einvoice_invoice_rel')->insert(['id' => 900, 'invoice_id' => 900, 'einv_irn' => str_repeat('b', 64),
    'einv_ack_date' => '2026-09-04 10:00:00', 'einv_cancel_at' => '2026-09-04 11:00:00', 'einv_status' => 'CNL']);
$detailFilters = ['from_date' => '2026-09-04', 'to_date' => '2026-09-04', 'register_view' => 'detail'];
$detailed = $sales->buildReport($detailFilters, true);
$sampleRow = $detailed['data'][0];
checkRegister($sampleRow['customer_name'] === 'Interstate Party' && $sampleRow['party_type'] === 'Customer', 'Legacy dispatch customer/party type lost');
checkRegister($sampleRow['payment_mode'] === 'Cash' && $sampleRow['unloading'] === 'PARTY SITE' && $sampleRow['truck'] === 'TN10BF9876', 'Dispatch detail mapping wrong');
checkRegister($sampleRow['description'] === 'INV/900,Concrete,2M3' && $sampleRow['tax_name'] === 'GST 18% + TCS 1%', 'Description or historical tax name wrong');
checkRegister($sampleRow['tcs'] === 1.0 && $detailed['totals']['tcs'] === 4.0, 'TCS total wrong');
foreach (array_slice($detailed['data'], 1) as $row) {
    checkRegister($row['payment_mode'] === '' && $row['unloading'] === '' && $row['truck'] === '', 'Unrelated, deleted or foreign-plant dispatch leaked');
}
checkRegister($sales->buildReport($detailFilters + ['customer_id' => 2])['pagination']['total'] === 1, 'Legacy dispatch customer filter failed');
// A truck/site in another plant must not be exposed even if referenced by this dispatch.
DB::table('mm_sites')->where('id', 900)->update(['plant_id' => 2]);
DB::table('mm_machines')->where('id', 900)->update(['plant_id' => 2]);
$scoped = $sales->buildReport($detailFilters)['data'][0];
checkRegister($scoped['unloading'] === '' && $scoped['truck'] === '', 'Foreign-plant truck/site leaked');
DB::table('mm_sites')->where('id', 900)->update(['plant_id' => 1]);
DB::table('mm_machines')->where('id', 900)->update(['plant_id' => 1]);
foreach (['excel' => 'xlsx', 'pdf' => 'pdf'] as $format => $extension) {
    $sales->generateAndSaveReport($format, $detailFilters + ['customer_id' => 2], "$directory/sales-sample-detail.$extension");
}
$sampleSheet = IOFactory::load("$directory/sales-sample-detail.xlsx")->getActiveSheet();
$sampleHeaders = $sampleSheet->rangeToArray('A4:'.$sampleSheet->getHighestColumn().'4')[0];
foreach (['Payment Type' => 'Cash', 'Product Rate' => 50, 'Gross' => 119, 'Tax Name' => 'GST 18% + TCS 1%',
    'Sales GST (Taxable)' => 100, 'CGST 2.5%' => 0, 'SGST 14%' => 0, 'TCS' => 1, 'Unloading' => 'PARTY SITE',
    'Truck' => 'TN10BF9876', 'Description' => 'INV/900,Concrete,2M3', 'E-Invoice Status' => 'CNL',
    'Cancel At' => '04-09-2026 11:00', 'ACK Date' => '04-09-2026 10:00', 'Party Type' => 'Customer', 'Created By' => 'test@example.test'] as $label => $expected) {
    $index = array_search($label, $sampleHeaders, true);
    checkRegister($index !== false, "Sample Excel missing $label");
    $cell = PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1).'5';
    checkRegister($sampleSheet->getCell($cell)->getValue() == $expected, "Sample Excel value wrong for $label");
}
$samplePdf = preg_replace('/\s+/', ' ', $parser->parseFile("$directory/sales-sample-detail.pdf")->getText());
foreach (['Cash', 'PARTY SITE', 'TN10BF9876', 'INV/900,Concrete,2M3', 'GST 18% + TCS 1%', 'CNL', 'Customer', 'test@example.test', 'CGST 2.5%', 'SGST 14%', 'TCS 1%'] as $text) {
    checkRegister(str_contains($samplePdf, $text), "Sample PDF missing $text");
}
echo "Register filters, pagination, tax totals, plant isolation, detailed sample fields, menus and Excel/PDF exports passed.\nQA files: $directory\n";
