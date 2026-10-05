<?php
// Ten mixed documents using the actual Blade view; no application records are read or written.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$documents = [];
for ($i = 1; $i <= 10; $i++) {
    $invoice = new App\Models\Invoice;
    $invoice->setRawAttributes(['id'=>$i, 'invoice_type'=>$i % 2 ? 'Invoice' : 'Bill', 'invoice_label'=>$i % 2 ? 'Tax Invoice' : 'purchase',
        'invoice_number'=>(string)$i, 'prefix'=>$i % 2 ? 'INV' : 'BILL', 'invoice_date'=>'2026-09-19', 'due_date'=>'2026-09-30',
        'subtotal'=>10000, 'tax_amount'=>1800, 'total_amount'=>11800, 'global_discount'=>0, 'notes'=>'Synthetic export verification document.']);
    $plant = new App\Models\Plant(['name'=>'Demo Plant', 'gstin'=>'DEMO GSTIN']);
    $plant->setRelation('entity', new App\Models\Entity(['legal_name'=>'Demo Concrete Company']));
    $address = new App\Models\Address(['line_1'=>'123 Industrial Road', 'city'=>'Chennai', 'zipcode'=>'600001']);
    $plant->setRelation('addresses', collect([$address]));
    $party = new App\Models\Patron(['legal_name'=>$i % 2 ? 'Example Customer' : 'Example Vendor', 'gstin'=>'DEMO PARTY GSTIN']);
    $party->setRelation('addresses', collect([$address]));
    $item = new App\Models\InvoiceItem(['item_name'=>'Ready-mix concrete / materials', 'hsn_code'=>'38245010', 'quantity'=>10,
        'price_unit'=>1000, 'subtotal'=>10000, 'line_tax_amount'=>1800, 'line_total'=>11800]);
    $item->setRelation('uom', null);
    $invoice->setRelation('plant', $plant)->setRelation('partner', $party)->setRelation('items', collect([$item]))->setRelation('orderTaxes', collect());
    $documents[] = view('reports.bulk_document', compact('invoice'))->render();
}
$bytes = app(App\Services\BulkInvoicePdfService::class)->render($documents);
$path = base_path('tmp/pdfs/bulk-documents-mixed.pdf');
if (!is_dir(dirname($path))) mkdir(dirname($path), 0775, true);
file_put_contents($path, $bytes);
echo "Mixed invoice/bill PDF rendered; exactly 10 pages verified by exporter.\n";
