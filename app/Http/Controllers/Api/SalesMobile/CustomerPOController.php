<?php
/*
Author: Cipher
Created: 2026-10-21 17:51:57 Asia/Calcutta (UTC+05:30)
*/

namespace App\Http\Controllers\Api\SalesMobile;

use App\Http\Controllers\Controller;
use App\Models\{CustomerPO, Plant, Quotation, SalesOrder};
use App\Services\Mobile\MobileAccess as Access;
use App\Services\Mobile\QuotationCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CustomerPOController extends Controller
{
    public function index(Request $request)
    {
        return app(OrderController::class)->customerPOs($request);
    }

    public function show(int $customer_po)
    {
        $po = Access::customerPOs()->findOrFail($customer_po)->load(['items', 'salesOrders' => fn ($q) => $q->whereIn('id', Access::orders()->select('id'))]);
        return response()->json(['success' => true, 'data' => $po]);
    }

    private function editable(CustomerPO $po): void
    {
        abort_if((int) $po->status === CustomerPO::STATUS_COMPLETED || $po->salesOrders()->exists() || $po->dispatches()->exists(), 422, 'A completed or allocated customer PO cannot be changed.');
    }

    private function save(Request $request, ?int $id = null)
    {
        return DB::transaction(function () use ($request, $id) {
            Plant::whereKey(Access::plantId())->lockForUpdate()->firstOrFail();
            $po = $id === null ? null : Access::customerPOs()->lockForUpdate()->findOrFail($id);
            if ($po) $this->editable($po);
            $exists = fn ($table) => Rule::exists($table, 'id')->where('plant_id', Access::plantId())->whereNull('deleted_at');
            $data = $request->validate([
                'patron_id' => ['required', 'integer', Access::exists(Access::customers())],
                'site_id' => ['required', 'integer', Access::exists(Access::plantSites())],
                'quotation_id' => ['nullable', 'integer', Access::exists(Access::quotations())],
                'customer_po_reference' => 'nullable|string|max:150',
                'order_date' => 'required|date_format:Y-m-d', 'notes' => 'nullable|string|max:10000',
                'is_tax_inclusive' => 'required|boolean',
                'items' => 'required_without:quotation_id|array|min:1|max:100',
                'items.*' => 'required|array:mix_design_id,quantity,rate,tax_id,concrete_pump,pump_rate',
                'items.*.mix_design_id' => ['required', 'integer', $exists('mm_mix_designs')],
                'items.*.quantity' => 'required|numeric|between:0.001,999999',
                'items.*.rate' => 'required|numeric|between:0,99999999',
                'items.*.tax_id' => ['nullable', 'integer', $exists('mm_taxes')->where('tax_type', 'Sales')->where('status', 1)->whereNull('parent_id')],
                'items.*.concrete_pump' => 'nullable|string|max:100',
                'items.*.pump_rate' => 'nullable|numeric|between:0,99999999',
            ]);
            $quote = empty($data['quotation_id']) ? null : Access::quotations()->lockForUpdate()->findOrFail($data['quotation_id']);
            if ($po) abort_unless($po->quotation_id == ($data['quotation_id'] ?? null), 422, 'The linked quotation cannot be changed.');
            if ($quote) {
                abort_unless((int) $quote->status === Quotation::STATUS_ACCEPTED, 422, 'Only approved quotations can become customer POs.');
                abort_if($quote->customerPOs()->when($po, fn ($q) => $q->whereKeyNot($po->id))->exists(), 422, 'Quotation already converted.');
                abort_unless((int) $quote->patron_id === (int) $data['patron_id'] && (int) $quote->site_id === (int) $data['site_id'], 422, 'Customer and site must match the quotation.');
                $data['is_tax_inclusive'] = (bool) $quote->is_tax_inclusive;
                $items = $quote->items->map(fn ($item) => $item->only(['mix_design_id', 'quantity', 'rate', 'tax_id', 'concrete_pump', 'pump_rate', 'untaxed_amount', 'tax_amount', 'amount_total']))->all();
                abort_if(empty($items), 422, 'Quotation has no items.');
                $quote->update(['is_customer_po' => true]);
            } else {
                $items = QuotationCalculator::items($data['items'], $data['is_tax_inclusive']);
            }
            unset($data['items']);
            $data['sales_executive_id'] = Access::commercialOwnerId($po?->sales_executive_id);
            if (!$po) {
                $number = CustomerPO::generateReference(Access::plantId());
                $po = CustomerPO::create(array_merge($data, $number, ['plant_id' => Access::plantId(), 'converted_by_user_id' => $request->user()->id, 'status' => $quote ? CustomerPO::STATUS_CONFIRMED : CustomerPO::STATUS_DRAFT]));
            } else {
                $po->update($data);
                $po->items()->get()->each->delete();
            }
            foreach ($items as $item) {
                unset($item['discount_type'], $item['discount'], $item['discount_amount']);
                $po->items()->create($item);
            }
            $po->updateTotals();
            return response()->json(['success' => true, 'data' => $po->fresh()->load('items')], $id === null ? 201 : 200);
        });
    }

    public function store(Request $request) { return $this->save($request); }
    public function update(Request $request, int $customer_po) { return $this->save($request, $customer_po); }

    public function destroy(int $customer_po)
    {
        return DB::transaction(function () use ($customer_po) {
            Plant::whereKey(Access::plantId())->lockForUpdate()->firstOrFail();
            $po = Access::customerPOs()->lockForUpdate()->findOrFail($customer_po);
            $this->editable($po);
            $quote = $po->quotation_id ? Access::quotations()->lockForUpdate()->findOrFail($po->quotation_id) : null;
            $po->delete();
            if ($quote && !$quote->customerPOs()->exists()) $quote->update(['is_customer_po' => false]);
            return response()->json(['success' => true, 'message' => 'Customer PO deleted.']);
        });
    }

    public function convert(Request $request, int $customer_po)
    {
        return DB::transaction(function () use ($request, $customer_po) {
            Plant::whereKey(Access::plantId())->lockForUpdate()->firstOrFail();
            $po = Access::customerPOs()->lockForUpdate()->findOrFail($customer_po);
            abort_if((int) $po->status === CustomerPO::STATUS_COMPLETED, 422, 'Customer PO is completed.');
            $data = $request->validate([
                'items' => 'required|array|min:1|max:100',
                'items.*.item_id' => ['required', 'integer', 'distinct', Rule::exists('mm_customer_po_items', 'id')->where('customer_po_id', $po->id)->whereNull('deleted_at')],
                'items.*.quantity' => 'required|numeric|between:0.001,999999',
            ]);
            $orders = [];
            foreach ($data['items'] as $line) {
                $item = $po->items()->findOrFail($line['item_id']);
                $ordered = $po->items()->where('mix_design_id', $item->mix_design_id)->sum('quantity');
                $allocated = $po->salesOrders()->where('mix_design_id', $item->mix_design_id)->where('status', '!=', SalesOrder::STATUS_CANCELLED)->sum('total_qty');
                abort_if($allocated + $line['quantity'] > $ordered + 0.00001, 422, 'Quantity exceeds the remaining customer PO quantity.');
                $number = SalesOrder::generateOrderNo(Access::plantId());
                $orders[] = SalesOrder::create(array_merge($item->only(['mix_design_id', 'rate', 'tax_id', 'concrete_pump', 'pump_rate']), [
                    'prefix' => $number['prefix'], 'order_no' => $number['next_number'], 'plant_id' => Access::plantId(),
                    'customer_id' => $po->patron_id, 'site_id' => $po->site_id, 'customer_po_id' => $po->id,
                    'sales_executive_id' => $po->sales_executive_id, 'is_tax_inclusive' => $po->is_tax_inclusive,
                    'total_qty' => $line['quantity'], 'produced_qty' => 0, 'status' => SalesOrder::STATUS_SCHEDULED,
                ]));
            }
            $allocated = $po->salesOrders()->where('status', '!=', SalesOrder::STATUS_CANCELLED)->sum('total_qty');
            $po->update(['status' => $allocated >= $po->items()->sum('quantity') ? CustomerPO::STATUS_COMPLETED : CustomerPO::STATUS_CONFIRMED]);
            return response()->json(['success' => true, 'data' => $orders], 201);
        });
    }
}
