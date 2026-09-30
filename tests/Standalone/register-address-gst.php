<?php

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $error) { fwrite(STDERR, (string) $error); exit(1); });

use App\Models\Patron;
use App\Models\Plant;
use App\Services\Reports\ReportPermissions;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

// These fixtures never write application data.
config(['database.default' => 'register_format_test', 'database.connections.register_format_test' => [
    'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
], 'cache.default' => 'array', 'session.driver' => 'array', 'logging.default' => 'null']);
session(['active_plant_id' => 1]);
function checkRegister(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}
function checkStandardSales(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array $report): void
{
    $headers = $sheet->rangeToArray('A4:'.$sheet->getHighestColumn().'4')[0];
    $expected = ['Address_1' => '244/1, Mangalam Road',
        'Address_2' => 'Lakshmi Garden Poomalur, Pallipalayam, Samalapuram', 'City' => 'Tiruppur', 'Zipcode' => '00641664',
        'Shipping Address_1' => 'Delivery site street', 'Shipping Address_2' => 'Site area',
        'Shipping Zipcode' => '000789', 'Truck' => 'TN10BF9876'];
    foreach ($expected as $label => $value) {
        checkRegister(count(array_keys($headers, $label, true)) === 1, "Standard sales missing/duplicate $label");
        $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(array_search($label, $headers, true) + 1);
        foreach ($report['data'] as $index => $row) {
            $cell = $sheet->getCell($letter.($index + 5));
            checkRegister($cell->getValue() === $value && $cell->getDataType() === 's', "Standard sales $label missing or not text");
        }
    }
    $baseHeaders = array_column($report['columns'], 'label');
    checkRegister(array_values(array_filter($headers, fn ($header) => in_array($header, $baseHeaders, true))) === $baseHeaders,
        'Standard sales reordered/replaced existing GST or document columns');
    checkRegister($sheet->getHighestRow() === count($report['data']) + 5, 'Standard sales changed the selected grouping');
    checkRegister($sheet->getCell('A3')->getValue() === $report['note'], 'Standard sales rounding semantics changed');
    foreach ($report['columns'] as $column) {
        if ($column['format'] !== 'number') continue;
        $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(array_search($column['label'], $headers, true) + 1);
        foreach ($report['data'] as $index => $row) {
            checkRegister(abs($sheet->getCell($letter.($index + 5))->getValue() - (float) \App\Services\Reports\RegisterReportColumns::value($row, $column['key'])) < 0.0001,
                'Standard sales amount changed: '.$column['label']);
        }
        if ($column['total']) {
            checkRegister(abs($sheet->getCell($letter.$sheet->getHighestRow())->getValue() - (float) \App\Services\Reports\RegisterReportColumns::value($report['totals'], $column['total'])) < 0.0001,
                'Standard sales total changed: '.$column['label']);
        }
    }
    checkRegister($sheet->getStyle('C5')->getAlignment()->getWrapText() && $sheet->getRowDimension(5)->getRowHeight() > 22,
        'Standard sales address lines are clipped');
    checkRegister($sheet->getFreezePane() === 'C5', 'Standard sales freezes too many wide address columns');
}
foreach ([
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
] as $table => $fields) DB::statement("CREATE TABLE $table ($fields)");
DB::table('mm_plants')->insert(['id' => 1, 'gstin' => '33TEST']);
DB::table('mm_patrons')->insert([
    ['id' => 1, 'plant_id' => 1, 'legal_name' => '=SUM(1,2)', 'gstin' => '33VENDOR'],
    ['id' => 2, 'plant_id' => 1, 'legal_name' => 'Sample Construction Company', 'gstin' => '33CUSTOMER'],
]);
DB::table('mm_users')->insert(['id' => 1, 'username' => 'Test', 'email' => 'test@example.test']);
DB::table('mm_product_units')->insert(['id' => 1, 'unit_name' => 'Cubic metre', 'unit_code' => 'M3']);
DB::table('mm_products')->insert(['id' => 1, 'plant_id' => 1, 'title' => 'Concrete']);
DB::table('mm_taxes')->insert(['id' => 1, 'plant_id' => 1, 'tax_name' => 'GST 18%', 'tax_rate' => 18, 'tax_group' => 'GST']);
DB::table('mm_sales_orders')->insert(['id' => 900, 'plant_id' => 1, 'customer_id' => 2]);
DB::table('mm_sites')->insert(['id' => 900, 'plant_id' => 1, 'name' => 'PARTY SITE']);
DB::table('mm_machines')->insert(['id' => 900, 'plant_id' => 1, 'registration' => 'TN10BF9876']);
DB::table('mm_dispatches')->insert(['id' => 900, 'plant_id' => 1, 'sales_order_id' => 900, 'unload_site_id' => 900, 'truck_id' => 900, 'payment_mode' => 'cash']);
DB::table('mm_invoices')->insert(['id' => 900, 'plant_id' => 1, 'prefix' => 'INV/', 'invoice_number' => '900', 'invoice_date' => '2026-09-04',
    'status' => 'Approved', 'invoice_label' => 'Dispatch', 'invoice_type' => 'sales', 'ref_id' => 900, 'created_by' => 1]);
DB::table('mm_invoice_items')->insert(['id' => 900, 'invoice_id' => 900, 'item_id' => 1, 'uom_id' => 1, 'item_name' => 'Concrete',
    'hsn_code' => '003824', 'quantity' => 2, 'price_unit' => 50, 'subtotal' => 100, 'line_tax_amount' => 18, 'line_total' => 118]);
foreach (['CGST', 'SGST'] as $index => $tax) DB::table('mm_order_taxes')->insert(['id' => 9000 + $index, 'order_id' => 900,
    'order_items_id' => 900, 'order_type' => 'Invoice', 'name' => $tax, 'rate' => 9, 'amount' => 9]);
foreach ([1, 2] as $id) {
    DB::table('mm_purchase_orders')->insert(['id' => $id, 'plant_id' => 1, 'po_number' => 'PO/'.$id, 'bill_number' => '00'.$id,
        'date_order' => '2026-09-01', 'billed_date' => '2026-09-01', 'vendor_id' => 1, 'created_by' => 1, 'state' => 'approved']);
    DB::table('mm_purchase_order_items')->insert(['id' => $id, 'order_id' => $id, 'product_id' => 1, 'product_uom' => 1, 'tax_id' => 1,
        'hsn_code' => '003824', 'description' => 'Concrete', 'product_quantity' => 2, 'unit_price' => 50, 'price_subtotal' => 100, 'price_tax' => 18, 'price_total' => 118]);
}
$sales = app(App\Services\Reports\SalesRegisterService::class);
$purchase = app(App\Services\Reports\PurchaseRegisterService::class);
$controller = new class extends App\Http\Controllers\ReportController {
    protected function authorizeReport(string $type, string $action = 'view', array $params = []): void {}
};
$directory = sys_get_temp_dir().'/register-address-gst-tests-'.bin2hex(random_bytes(4));
mkdir($directory);

DB::statement('ALTER TABLE mm_invoices ADD COLUMN round_off REAL DEFAULT 0');
DB::statement('ALTER TABLE mm_purchase_orders ADD COLUMN rounding_value REAL DEFAULT 0');
foreach (['site_address_1', 'site_address_2', 'city', 'zipcode'] as $field) DB::statement("ALTER TABLE mm_sites ADD COLUMN $field TEXT");
foreach ([
    'mm_contacts' => 'id INTEGER, patron_id INTEGER, plant_id INTEGER, is_primary INTEGER, deleted_at TEXT',
    'mm_addresses' => 'id INTEGER, plant_id INTEGER, contact_id INTEGER, address_type_id INTEGER, line_1 TEXT, line_2 TEXT, city TEXT, state_id INTEGER, state_code TEXT, zipcode TEXT, is_primary INTEGER, deleted_at TEXT',
    'mm_address_types' => 'id INTEGER, type TEXT, deleted_at TEXT',
    'mm_address_relation' => 'address_id INTEGER, addressable_id INTEGER, addressable_type TEXT',
    'mm_state_codes' => 'id INTEGER, state_name TEXT, deleted_at TEXT',
    'mm_purchase_order_history' => 'id INTEGER, plant_id INTEGER, order_id INTEGER, order_item_id INTEGER, truck_id INTEGER, deleted_at TEXT',
] as $table => $fields) DB::statement("CREATE TABLE $table ($fields)");

DB::table('mm_state_codes')->insert(['id' => 1, 'state_name' => 'Tamil Nadu']);
DB::table('mm_address_types')->insert([['id' => 1, 'type' => 'Billing'], ['id' => 2, 'type' => 'Shipping']]);
DB::table('mm_contacts')->insert([
    ['id' => 1, 'patron_id' => 2, 'plant_id' => 1, 'is_primary' => 1],
    ['id' => 2, 'patron_id' => 2, 'plant_id' => 2, 'is_primary' => 1],
]);
foreach ([
    [1, 1, 1, 1, '244/1, Mangalam Road', 'Lakshmi Garden Poomalur, Pallipalayam, Samalapuram', '00641664'],
    [2, 1, 1, 2, 'Secondary shipping address', '', '111111'],
    [3, 2, 2, 1, 'Foreign contact address', '', '999999'],
    [4, 1, null, 1, 'Supplier street', 'Supplier area', '000123'],
    [5, 1, null, 1, 'Receiving plant street', 'Plant area', '000456'],
    [6, 2, 1, 1, 'Foreign address', '', '999998'],
] as [$id, $plant, $contact, $type, $line1, $line2, $zip]) {
    DB::table('mm_addresses')->insert(['id' => $id, 'plant_id' => $plant, 'contact_id' => $contact, 'address_type_id' => $type,
        'line_1' => $line1, 'line_2' => $line2, 'city' => 'Tiruppur', 'state_id' => 1, 'zipcode' => $zip, 'is_primary' => 1]);
}
DB::table('mm_address_relation')->insert([
    ['address_id' => 4, 'addressable_id' => 1, 'addressable_type' => (new Patron)->getMorphClass()],
    ['address_id' => 5, 'addressable_id' => 1, 'addressable_type' => (new Plant)->getMorphClass()],
]);
DB::table('mm_sites')->where('id', 900)->update(['site_address_1' => 'Delivery site street', 'site_address_2' => 'Site area', 'city' => 'Tiruppur', 'zipcode' => '000789']);
DB::table('mm_invoices')->where('id', 900)->update(['round_off' => -0.01]);
DB::table('mm_purchase_orders')->where('id', 1)->update(['rounding_value' => 0.02]);
DB::table('mm_invoice_items')->insert(['id' => 910, 'invoice_id' => 900, 'item_id' => 1, 'uom_id' => 1,
    'item_name' => 'Second product', 'hsn_code' => '003824', 'quantity' => 3.8, 'price_unit' => 4571.43,
    'subtotal' => 17371.43, 'line_tax_amount' => 868.58, 'line_total' => 18240.01]);
foreach (['CGST', 'SGST'] as $index => $tax) {
    DB::table('mm_order_taxes')->insert(['id' => 9100 + $index, 'order_id' => 900, 'order_items_id' => 910,
        'order_type' => 'Invoice', 'name' => $tax, 'rate' => 2.5, 'amount' => 434.29]);
}
DB::table('mm_purchase_order_items')->insert(['id' => 910, 'order_id' => 1, 'product_id' => 1, 'product_uom' => 1,
    'tax_id' => 1, 'description' => 'Second product', 'hsn_code' => '003824', 'product_quantity' => 1,
    'unit_price' => 100, 'price_subtotal' => 100, 'price_tax' => 18, 'price_total' => 118]);
DB::table('mm_machines')->insert(['id' => 950, 'plant_id' => 2, 'registration' => 'FOREIGN-TRUCK']);
DB::table('mm_purchase_order_history')->insert([
    ['id' => 1, 'plant_id' => 1, 'order_id' => 1, 'order_item_id' => 1, 'truck_id' => 900, 'deleted_at' => null],
    ['id' => 2, 'plant_id' => 1, 'order_id' => 1, 'order_item_id' => 1, 'truck_id' => 900, 'deleted_at' => null],
    ['id' => 3, 'plant_id' => 1, 'order_id' => 1, 'order_item_id' => 1, 'truck_id' => 950, 'deleted_at' => null],
    ['id' => 4, 'plant_id' => 1, 'order_id' => 1, 'order_item_id' => 910, 'truck_id' => 900, 'deleted_at' => '2026-09-02'],
]);

$headers = ['DATE', 'PARTY', 'ADDRESS_1', 'ADDRESS_2', 'CITY', 'STATE', 'ZIPCODE', 'SHIPPING ADDRESS', 'SHIPPING ZIPCODE',
    'TYPE', 'INVOICE NO', 'TRUCK', 'GSTIN', 'PRODUCT', 'HSN/SAC', 'QUANTITY', 'UNIT', 'RATE', 'GROSS', 'SALES GST',
    'TAX NAME', 'TAX AMOUNT', 'CGST', 'SGST', 'IGST', 'ROUNDOFF'];
checkRegister(ReportPermissions::reportId('sales_register', ['register_view' => 'summary', 'excel_format' => 'address_gst']) === 'detailed_sales_register', 'New item export bypasses detail permission');

foreach (['sales' => $sales, 'purchase' => $purchase] as $kind => $service) {
    $formatFilters = ['from_date' => $kind === 'sales' ? '2026-09-04' : '2026-09-01',
        'to_date' => $kind === 'sales' ? '2026-09-04' : '2026-09-01', 'plant_id' => 1,
        'register_view' => 'summary', 'excel_format' => 'address_gst'];
    if ($kind === 'sales') $formatFilters['customer_id'] = 2;
    $service->generateAndSaveReport('excel', array_replace($formatFilters, ['excel_format' => 'standard']), "$directory/$kind-standard.xlsx");
    $standardBook = IOFactory::load("$directory/$kind-standard.xlsx");
    $standardSheet = $standardBook->getActiveSheet();
    $standardHeaders = $standardSheet->rangeToArray('A4:'.$standardSheet->getHighestColumn().'4')[0];
    checkRegister(in_array('CGST 2.5%', $standardHeaders, true) && in_array('IGST 28%', $standardHeaders, true), "$kind standard GST layout was replaced");
    checkRegister($standardSheet->getHighestRow() === ($kind === 'sales' ? 6 : 7), "$kind standard summary grouping changed");
    if ($kind === 'sales') {
        $standardFilters = array_replace($formatFilters, ['excel_format' => 'standard']);
        $baseReport = $sales->buildReport($standardFilters, true);
        checkRegister(!in_array('address_1', array_column($baseReport['columns'], 'key'), true), 'Address columns widened screen/PDF layout');
        checkStandardSales($standardSheet, $baseReport);
        $detailFilters = array_replace($standardFilters, ['register_view' => 'detail']);
        $detailReport = $sales->buildReport($detailFilters, true);
        $sales->generateAndSaveReport('excel', $detailFilters, "$directory/sales-standard-detail.xlsx");
        $detailBook = IOFactory::load("$directory/sales-standard-detail.xlsx");
        checkStandardSales($detailBook->getActiveSheet(), $detailReport);
        $detailBook->disconnectWorksheets();
        $genericBook = app(\App\Services\Reports\ExcelExportService::class)->generateExcelReport('sales_register', '2026-09-04', '2026-09-04', $baseReport);
        checkStandardSales($genericBook->getActiveSheet(), $baseReport);
        $genericBook->disconnectWorksheets();
        (new \App\Exports\SalesRegisterExport(app(\App\Repositories\ReportRepository::class)->getSalesRegisterQuery($detailFilters)))
            ->export("$directory/sales-standard-legacy.xlsx");
        $legacyBook = IOFactory::load("$directory/sales-standard-legacy.xlsx");
        checkStandardSales($legacyBook->getActiveSheet(), $detailReport);
        $legacyBook->disconnectWorksheets();
    } else {
        checkRegister(!in_array('Address_1', $standardHeaders, true), 'Purchase standard columns changed');
    }
    $standardBook->disconnectWorksheets();
    $service->generateAndSaveReport('excel', $formatFilters, "$directory/$kind-address-gst.xlsx");
    $book = IOFactory::load("$directory/$kind-address-gst.xlsx");
    $sheet = $book->getActiveSheet();
    checkRegister($sheet->rangeToArray('A4:Z4')[0] === $headers, "$kind sample column order mismatch");
    checkRegister($sheet->getHighestColumn() === 'Z', "$kind unexpected extra columns");
    checkRegister($sheet->getCell('A5')->getDataType() === 'n' && $sheet->getStyle('A5')->getNumberFormat()->getFormatCode() === 'dd-mm-yyyy', "$kind date type/format incorrect");
    checkRegister($sheet->getCell('O5')->getDataType() === 's' && $sheet->getCell('O5')->getValue() === '003824', "$kind HSN leading zeros lost");
    checkRegister($sheet->getCell('V5')->getDataType() === 'n' && $sheet->getStyle('V5')->getNumberFormat()->getFormatCode() === '#,##0.00', "$kind TAX AMOUNT not numeric to two decimals");
    checkRegister($sheet->getCell('F5')->getValue() === 'Tamil Nadu', "$kind state name absent");
    checkRegister($sheet->getFreezePane() === 'C5', "$kind identifying columns not frozen");
    if ($kind === 'sales') {
        checkRegister($sheet->getHighestRow() === 7, 'New format collapsed item rows from summary request');
        checkRegister($sheet->getCell('C5')->getValue() === '244/1, Mangalam Road' && $sheet->getCell('D5')->getValue() === 'Lakshmi Garden Poomalur, Pallipalayam, Samalapuram', 'Primary billing address incorrect');
        checkRegister($sheet->getCell('G5')->getValue() === '00641664' && $sheet->getCell('G5')->getDataType() === 's', 'ZIP is not preserved text');
        checkRegister($sheet->getCell('H5')->getValue() === 'Delivery site street, Site area, Tiruppur' && $sheet->getCell('I5')->getValue() === '000789', 'Dispatch shipping details wrong');
        checkRegister($sheet->getCell('J5')->getValue() === 'Cash' && $sheet->getCell('L5')->getValue() === 'TN10BF9876', 'Dispatch type/truck lost');
        checkRegister($sheet->getCell('Z5')->getValue() === -0.01 && $sheet->getCell('Z6')->getValue() == 0 && $sheet->getCell('Z7')->getValue() === -0.01, 'Sales roundoff duplicated or lost');
        checkRegister($sheet->getCell('V6')->getValue() === 868.58 && $sheet->getCell('W6')->getValue() === 434.29 && $sheet->getCell('X6')->getValue() === 434.29, 'Tax paise or split changed');
    } else {
        checkRegister($sheet->getCell('B5')->getDataType() === 's' && $sheet->getCell('B5')->getValue() === '=SUM(1,2)', 'Supplier name became a formula');
        checkRegister($sheet->getCell('C5')->getValue() === 'Supplier street', 'Supplier address missing');
        checkRegister($sheet->getCell('H5')->getValue() === 'Receiving plant street, Plant area, Tiruppur' && $sheet->getCell('I5')->getValue() === '000456', 'Purchase receiving address missing');
        checkRegister(in_array($sheet->getCell('J5')->getValue(), ['', null], true), 'Missing purchase payment type was invented');
        checkRegister($sheet->getCell('L5')->getValue() === 'TN10BF9876' && in_array($sheet->getCell('L6')->getValue(), ['', null], true), 'Inward trucks duplicated or foreign/deleted trucks leaked');
        checkRegister($sheet->getCell('U5')->getValue() === 'GST 18%', 'Purchase tax name missing');
        checkRegister($sheet->getCell('Z5')->getValue() === 0.02 && $sheet->getCell('Z6')->getValue() == 0 && $sheet->getCell('Z8')->getValue() === 0.02, 'Purchase roundoff duplicated');
    }
    $book->disconnectWorksheets();
}

// The new selection survives endpoint validation and cannot silently accept an unknown format.
$capturingService = new class(app(App\Repositories\ReportRepository::class)) extends App\Services\Reports\SalesRegisterService {
    public function generate(array $filters): array { return $filters; }
};
$requestFilters = ['from_date' => '2026-09-01', 'to_date' => '2026-09-30', 'excel_format' => 'address_gst', 'export' => 'excel', 'register_view' => 'detail'];
$response = $controller->salesRegister(Illuminate\Http\Request::create('/', 'GET', $requestFilters), $capturingService)->getData(true);
checkRegister($response['excel_format'] === 'address_gst' && $response['plant_id'] === 1, 'Endpoint discarded format or plant');
try {
    $controller->salesRegister(Illuminate\Http\Request::create('/', 'GET', array_replace($requestFilters, ['excel_format' => 'invalid'])), $capturingService);
    throw new RuntimeException('Unknown Excel format accepted');
} catch (Illuminate\Validation\ValidationException $e) {}

DB::table('mm_contacts')->where('id', 1)->update(['deleted_at' => '2026-09-30']);
DB::table('mm_sites')->where('id', 900)->update(['plant_id' => 2]);
$scopedReport = app(App\Services\Reports\RegisterAddressGstFormat::class)->prepare(
    $sales->buildReport(['from_date' => '2026-09-04', 'to_date' => '2026-09-04', 'plant_id' => 1], true), 'sales_register', 1);
checkRegister($scopedReport['data'][0]['address_1'] === '' && $scopedReport['data'][0]['shipping_address'] === ''
    && $scopedReport['data'][0]['shipping_zipcode'] === '', 'Foreign contacts/sites or deleted contact leaked into export');
$standardScoped = $sales->prepareExcelReport($sales->buildReport(['from_date' => '2026-09-04', 'to_date' => '2026-09-04', 'plant_id' => 1], true), ['plant_id' => 1]);
foreach (['address_1', 'address_2', 'city', 'zipcode', 'shipping_address_1', 'shipping_address_2', 'shipping_zipcode'] as $field) {
    checkRegister($standardScoped['data'][0][$field] === '', "Standard sales leaked foreign/deleted $field");
}
DB::table('mm_invoices')->where('id', 900)->update(['partner_id' => 1, 'invoice_label' => 'Manual']);
$manualReport = $sales->prepareExcelReport($sales->buildReport(['from_date' => '2026-09-04', 'to_date' => '2026-09-04', 'plant_id' => 1], true), ['plant_id' => 1]);
checkRegister($manualReport['data'][0]['address_1'] === 'Supplier street' && $manualReport['data'][0]['zipcode'] === '000123', 'Standard sales linked party address fallback missing');
checkRegister($manualReport['data'][0]['shipping_address_1'] === '' && $manualReport['data'][0]['truck'] === '', 'Manual invoice used unrelated dispatch');

$empty = app(App\Services\Reports\RegisterAddressGstFormat::class)->prepare(
    $sales->buildReport(['from_date' => '2025-01-01', 'to_date' => '2025-01-01', 'plant_id' => 1], true), 'sales_register', 1);
checkRegister($empty['data'] === [] && count($empty['columns']) === 26 && $empty['totals']['roundoff'] === 0.0, 'Empty format lost columns or created rows');
$emptyStandard = $sales->prepareExcelReport($sales->buildReport(['from_date' => '2025-01-01', 'to_date' => '2025-01-01', 'plant_id' => 1], true), ['plant_id' => 1]);
checkRegister($emptyStandard['data'] === [] && in_array('shipping_address_2', array_column($emptyStandard['columns'], 'key'), true), 'Empty standard sales lost address columns');

echo "Standard sales summary/detail/legacy addresses, shipping, truck, GST totals and cell types; Address & GST layout, scoping, tax precision, roundoff and endpoint validation passed.\nQA files: $directory\n";
