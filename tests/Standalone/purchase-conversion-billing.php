<?php

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\PurchaseOrderController;
use App\Models\{Invoice, InvoiceItem, PurchaseOrder, PurchaseOrderHistory, PurchaseOrderItem};
use App\Services\PurchaseReceiptBilling;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

$check = function ($expected, $actual, $label) {
    if (abs($expected - $actual) > 0.00001) throw new RuntimeException("$label: expected $expected, got $actual");
};
$order = new PurchaseOrder(['plant_id' => 1, 'product_quantity' => 1000, 'shipping_charges' => 100]);
$item = new PurchaseOrderItem(['product_quantity' => 1000, 'product_uom' => 1, 'received_quantity' => 100,
    'invoiced_quantity' => 0, 'unit_price' => 50, 'price_subtotal' => 50000,
    'discount_type' => '₹', 'discount_amount' => 10]);
$item->id = 42;
$item->setRelation('product', null);
$receipt = new PurchaseOrderHistory(['received_qty' => 100, 'conversion_quantity' => 2.1234, 'conversion_uom_id' => 2]);
$receipt->id = 1;
$item->setRelation('history', new Collection([$receipt]));
$order->setRelation('items', new Collection([$item]));
$order->setRelation('bills', new Collection);
$order->setRelation('billingHistory', new Collection);
$controller = new class extends PurchaseOrderController {
    public bool $converted = true;
    protected function conversionBillingEnabled(PurchaseOrder $order): bool { return $this->converted; }
};
$method = new ReflectionMethod(PurchaseOrderController::class, 'receivedBillData');
$request = Request::create('/', 'POST', ['items' => [['order_item_id' => 42, 'unit_price' => 200]]]);
$data = $method->invoke($controller, $request, $order);
$line = new InvoiceItem($data['items'][0]);
$check(2.1234, $line->quantity, 'Converted quantity keeps four decimals');
$check(2, $line->uom_id, 'Converted UOM');
if (array_key_exists('purchase_received_quantity', $data['items'][0]) || array_key_exists('purchase_receipt_quantities', $data['items'][0])) {
    throw new RuntimeException('Redundant invoice columns must not be written');
}
$check(423.68, round($line->subtotal, 2), 'Converted rate less proportional fixed discount');
$check(10, $data['shipping_charges'], 'Shipping uses original receipt share');
$line->compute(18, false);
$check(423.68, $line->subtotal, 'Invoice recomputation');
$check(76.26, $line->line_tax_amount, 'Tax on converted amount');

$bill = new Invoice;
$bill->id = 1;
$bill->created_at = '2026-09-26 10:00:00';
$bill->setRelation('items', new Collection([$line]));
$order->setRelation('bills', new Collection([$bill]));
$order->setRelation('billingHistory', new Collection([$bill]));
$item->invoiced_quantity = 100;
$secondReceipt = new PurchaseOrderHistory(['received_qty' => 200, 'conversion_quantity' => 5, 'conversion_uom_id' => 2]);
$secondReceipt->id = 2;
$item->history->push($secondReceipt);
$item->received_quantity = 300;
$data = $method->invoke($controller, $request, $order);
$check(5, $data['items'][0]['quantity'], 'Next bill uses only new receipt conversion');
$secondLine = new InvoiceItem($data['items'][0]);
$secondBill = new Invoice;
$secondBill->id = 2;
$secondBill->created_at = '2026-09-26 11:00:00';
$secondBill->setRelation('items', new Collection([$secondLine]));
$order->setRelation('bills', new Collection([$bill, $secondBill]));
$order->setRelation('billingHistory', new Collection([$bill, $secondBill]));
$item->invoiced_quantity = 300;
if ($method->invoke($controller, $request, $order) !== []) throw new RuntimeException('Duplicate bill allowed');

// Void the first bill while the second remains: its original receipt becomes available again.
$service = new PurchaseReceiptBilling;
$bill->deleted_at = '2026-09-26 12:00:00';
$released = $service->originalQuantity($order, $item, $bill, $line);
$check(100, $released, 'Original quantity derived on void');
$item->invoiced_quantity -= $released;
$order->setRelation('bills', new Collection([$secondBill]));
$data = $method->invoke($controller, $request, $order);
$check(2.1234, $data['items'][0]['quantity'], 'Out-of-order void restores the correct conversion');
$controller->converted = false;
$data = $method->invoke($controller, $request, $order);
$check(100, $data['items'][0]['quantity'], 'Setting disabled uses original quantity');
$check(1, $data['items'][0]['uom_id'], 'Setting disabled uses original UOM');

// Rebill the released receipt, then void that bill without disturbing the later receipt.
$controller->converted = true;
$rebillData = $method->invoke($controller, $request, $order);
$rebillLine = new InvoiceItem($rebillData['items'][0]);
$rebill = new Invoice;
$rebill->id = 3;
$rebill->created_at = '2026-09-26 13:00:00';
$rebill->setRelation('items', new Collection([$rebillLine]));
$order->setRelation('billingHistory', new Collection([$bill, $secondBill, $rebill]));
$item->invoiced_quantity = 300;
$check(100, $service->originalQuantity($order, $item, $rebill, $rebillLine), 'Rebilled original quantity');
$rebill->deleted_at = '2026-09-26 14:00:00';
$item->invoiced_quantity = 200;
$check(2.1234, $service->quantity($order, $item, true)['quantity'], 'Repeated void and rebill');

$service = new PurchaseReceiptBilling;
$order->setRelation('bills', new Collection);
$order->setRelation('billingHistory', new Collection);
$item->invoiced_quantity = 100;
$check(5, $service->quantity($order, $item, true)['quantity'], 'Legacy billed quantity allocated oldest first');
$item->invoiced_quantity = 0;
$secondReceipt->conversion_uom_id = 3;
try { $service->quantity($order, $item, true); throw new RuntimeException('Mixed UOMs accepted'); }
catch (ValidationException $e) {}
$secondReceipt->conversion_uom_id = 2;
$secondReceipt->conversion_quantity = 0;
try { $service->quantity($order, $item, true); throw new RuntimeException('Missing conversion accepted'); }
catch (ValidationException $e) {}
echo "PASS: converted quantity/UOM, precision, rates, tax, charges, partial receipts, duplicate prevention, void/rebill, legacy bills and settings.\n";
