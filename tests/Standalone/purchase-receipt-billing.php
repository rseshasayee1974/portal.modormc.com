<?php

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\PurchaseOrderController;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\InvoiceItem;
use Illuminate\Http\Request;

$order = new PurchaseOrder([
    'plant_id' => 1, 'vendor_id' => 1, 'discount_amount' => 100,
    'shipping_charges' => 1000, 'adjustment' => 10,
]);
$item = new PurchaseOrderItem([
    'product_quantity' => 1000, 'received_quantity' => 10, 'invoiced_quantity' => 0,
    'unit_price' => 100, 'price_subtotal' => 99000,
    'discount_type' => '₹', 'discount_amount' => 1000,
]);
$item->id = 42;
$item->setRelation('product', null);
$item->setRelation('history', new \Illuminate\Database\Eloquent\Collection);
$order->setRelation('items', new \Illuminate\Database\Eloquent\Collection([$item]));
$order->setRelation('bills', new \Illuminate\Database\Eloquent\Collection);
$order->setRelation('billingHistory', new \Illuminate\Database\Eloquent\Collection);
$method = new ReflectionMethod(PurchaseOrderController::class, 'receivedBillData');
$controller = new class extends PurchaseOrderController {
    protected function conversionBillingEnabled(PurchaseOrder $order): bool { return false; }
};
$request = Request::create('/', 'POST', ['invoice_date' => '2026-09-26']);
$check = function ($expected, $actual, $label) {
    if (abs($expected - $actual) > 0.00001) throw new RuntimeException("$label: expected $expected, got $actual");
};
$first = $method->invoke($controller, $request, $order);
$check(10, $first['items'][0]['quantity'], 'First bill quantity');
$check(990, $first['items'][0]['subtotal'], 'First bill amount after line discount');
$check(1, $first['global_discount'], 'Proportional global discount');
$check(10, $first['shipping_charges'], 'Proportional shipping');
$check(42, $first['items'][0]['purchase_order_item_id'], 'PO line link');
$line = new InvoiceItem($first['items'][0]);
$line->compute(18, false);
$check(990, $line->subtotal, 'Recomputation retains proportional fixed discount');
$check(178.2, $line->line_tax_amount, 'Tax on received amount');
$item->invoiced_quantity = 10;
$item->received_quantity = 30;
$second = $method->invoke($controller, $request, $order);
$check(20, $second['items'][0]['quantity'], 'Second bill only new receipt');
$check(1980, $second['items'][0]['subtotal'], 'Second amount');
$check(20, $second['shipping_charges'], 'Second proportional shipping');
$item->invoiced_quantity = 30;
if ($method->invoke($controller, $request, $order) !== []) throw new RuntimeException('Duplicate billing must be blocked');
echo "PASS: 10 MT then 20 MT, amounts, tax, proportional discounts/charges, and duplicate prevention.\n";
