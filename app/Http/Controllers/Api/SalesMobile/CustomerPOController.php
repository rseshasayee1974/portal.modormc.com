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
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class CustomerPOController extends Controller
{
    public function callAction($method, $parameters)
    {
        try {
            return parent::callAction($method, $parameters);
        } catch (ModelNotFoundException $exception) {
            if ($exception->getModel() === Quotation::class) {
                return response()->json(['message' => 'Quotation not found or unavailable in the selected plant.'], 404);
            }
            if ($exception->getModel() !== CustomerPO::class) throw $exception;

            return response()->json(['message' => 'Customer PO not found or unavailable in the selected plant.'], 404);
        } catch (ValidationException | AuthenticationException | AuthorizationException | HttpResponseException | HttpExceptionInterface $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $reference = (string) Str::uuid();
            $errorCode = $exception instanceof QueryException
                ? (in_array((string) ($exception->errorInfo[0] ?? $exception->getCode()), ['42S22', '42S02'], true)
                    ? 'PO_SCHEMA_MISMATCH' : 'PO_DATABASE_ERROR')
                : ($exception instanceof \Error ? 'PO_RUNTIME_ERROR' : 'PO_UNEXPECTED_ERROR');
            Log::error('Customer PO API request failed.', [
                'error_reference' => $reference,
                'error_code' => $errorCode,
                'operation' => $method,
                'plant_id' => Access::plantId(),
                'exception' => $exception,
            ]);
            $operation = match ($method) {
                'index', 'show' => 'load the customer PO',
                'store' => 'create the customer PO',
                'update' => 'update the customer PO',
                'destroy' => 'delete the customer PO',
                'convert' => 'convert the customer PO to sales orders',
                'convertQuotation' => 'convert the quotation to a customer PO',
                default => 'process the customer PO',
            };

            return response()->json([
                'message' => 'Unable to '.$operation.'. Please try again or contact support with the error reference.',
                'error_reference' => $reference,
                'error_code' => $errorCode,
            ], 500);
        }
    }

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
        abort_if(
            (int) $po->status === CustomerPO::STATUS_COMPLETED
                || $po->salesOrders()->exists()
                || $po->dispatches()->withoutGlobalScope('plant_id')->where('mm_dispatches.plant_id', Access::plantId())->exists(),
            422,
            'A completed or allocated customer PO cannot be changed.'
        );
    }

    private function save(Request $request)
    {
        return DB::transaction(function () use ($request) {
            Plant::whereKey(Access::plantId())->lockForUpdate()->firstOrFail();
            $exists = fn ($table) => Rule::exists($table, 'id')->where('plant_id', Access::plantId())->whereNull('deleted_at');
            $data = $request->validate([
                'patron_id' => ['required', 'integer', Access::exists(Access::customers())],
                'site_id' => ['required', 'integer', Access::exists(Access::plantSites())],
                'quotation_id' => ['nullable', 'integer', Access::exists(Access::customerPOQuotations())],
                'customer_po_reference' => 'nullable|string|max:150',
                'order_date' => 'required|date_format:Y-m-d', 'notes' => 'nullable|string|max:10000',
                'due_date' => 'nullable|date_format:Y-m-d',
                'concrete_pump' => 'nullable|string|max:191',
                'pump_rate' => 'nullable|numeric|between:0,99999999',
                'adjustment' => 'sometimes|required|numeric|between:-99999999,99999999',
                'sales_executive_id' => ['nullable', 'integer', $exists('mm_personnels')],
                'is_tax_inclusive' => 'required|boolean',
                'items' => 'required_without:quotation_id|array|min:1|max:100',
                'items.*' => 'required|array:mix_design_id,quantity,rate,tax_id,concrete_pump,pump_rate',
                'items.*.mix_design_id' => ['required', 'integer', $exists('mm_mix_designs')],
                'items.*.quantity' => 'required|numeric|between:0.001,999999',
                'items.*.rate' => 'required|numeric|between:0,99999999',
                'items.*.tax_id' => ['nullable', 'integer', $exists('mm_taxes')->where('tax_type', 'Sales')->where('status', 1)->whereNull('parent_id')],
                'items.*.concrete_pump' => 'nullable|string|max:100',
                'items.*.pump_rate' => 'nullable|numeric|between:0,99999999',
            ], [
                'required' => 'The :attribute field is required.',
                'required_without' => 'The :attribute field is required when :values is not provided.',
                'items.min' => 'Add at least one customer PO item.',
                'items.array' => 'The items field must be an array.',
                'order_date.date_format' => 'The order date must use YYYY-MM-DD format.',
                'is_tax_inclusive.boolean' => 'The tax inclusive field must be true or false.',
                'patron_id.exists' => 'The selected customer is unavailable in the selected plant.',
                'site_id.exists' => 'The selected site is unavailable in the selected plant.',
            ]);
            $quote = empty($data['quotation_id']) ? null : Access::customerPOQuotations()->lockForUpdate()->findOrFail($data['quotation_id']);
            if ($quote) {
                abort_unless((int) $quote->status === Quotation::STATUS_ACCEPTED, 422, 'Only approved quotations can become customer POs.');
                abort_if($quote->customerPOs()->exists(), 422, 'Quotation already converted.');
                abort_unless((int) $quote->patron_id === (int) $data['patron_id'] && (int) $quote->site_id === (int) $data['site_id'], 422, 'Customer and site must match the quotation.');
                $data['is_tax_inclusive'] = (bool) $quote->is_tax_inclusive;
                $data['adjustment'] = $quote->adjustment ?? 0;
                $items = $quote->items->map(fn ($item) => $item->only(['mix_design_id', 'quantity', 'rate', 'tax_id', 'concrete_pump', 'pump_rate', 'untaxed_amount', 'tax_amount', 'amount_total']))->all();
                abort_if(empty($items), 422, 'Quotation has no items.');
                $quote->update(['is_customer_po' => true]);
            } else {
                $items = QuotationCalculator::items($data['items'], $data['is_tax_inclusive']);
            }
            unset($data['items']);
            // Only main-app administrators may assign an owner. Sales users remain self-owned.
            $data['sales_executive_id'] = $quote?->sales_executive_id ?? Access::orderOwnerId(
                requestedOwner: $data['sales_executive_id'] ?? null,
                hasRequested: array_key_exists('sales_executive_id', $data),
            );
            $number = CustomerPO::generateReference(Access::plantId());
            $po = CustomerPO::create(array_merge(['adjustment' => 0], $data, $number, ['plant_id' => Access::plantId(), 'converted_by_user_id' => $request->user()->id, 'is_sales_order' => 0, 'status' => $quote ? CustomerPO::STATUS_CONFIRMED : CustomerPO::STATUS_DRAFT]));
            foreach ($items as $item) {
                unset($item['discount_type'], $item['discount'], $item['discount_amount']);
                $po->items()->create($item);
            }
            $po->updateTotals();
            return response()->json(['success' => true, 'data' => $po->fresh()->load('items')], 201);
        });
    }

    public function store(Request $request) { return $this->save($request); }
    public function update(Request $request, int $customer_po)
    {
        return DB::transaction(function () use ($request, $customer_po) {
            Plant::whereKey(Access::plantId())->lockForUpdate()->firstOrFail();
            $po = Access::customerPOs()->lockForUpdate()->findOrFail($customer_po);
            $this->editable($po);
            $exists = fn ($table) => Rule::exists($table, 'id')->where('plant_id', Access::plantId())->whereNull('deleted_at');
            $data = $request->validate([
                'prefix' => 'sometimes|nullable|string|max:20',
                'reference' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('mm_customer_pos', 'reference')->where('plant_id', Access::plantId())->whereNull('deleted_at')->ignore($po->id)],
                'patron_id' => ['sometimes', 'required', 'integer', Access::exists(Access::customers())],
                'site_id' => ['sometimes', 'required', 'integer', Access::exists(Access::plantSites())],
                'quotation_id' => ['nullable', 'integer', Access::exists(Access::customerPOQuotations())],
                'customer_po_reference' => 'nullable|string|max:150',
                'order_date' => 'sometimes|required|date_format:Y-m-d', 'notes' => 'nullable|string|max:10000',
                'due_date' => 'nullable|date_format:Y-m-d',
                'concrete_pump' => 'nullable|string|max:191',
                'pump_rate' => 'nullable|numeric|between:0,99999999',
                'adjustment' => 'sometimes|required|numeric|between:-99999999,99999999',
                'sales_executive_id' => ['nullable', 'integer', $exists('mm_personnels')],
                'is_tax_inclusive' => 'sometimes|required|boolean',
                'status' => ['sometimes', 'required', 'integer', Rule::in([CustomerPO::STATUS_DRAFT, CustomerPO::STATUS_CONFIRMED, CustomerPO::STATUS_COMPLETED])],
                'total_qty' => 'sometimes|required|numeric|between:0.001,99999900',
                'items' => 'sometimes|required|array|min:1|max:100',
                'items.*' => 'required|array:id,mix_design_id,quantity,rate,tax_id,concrete_pump,pump_rate,untaxed_amount,tax_amount,amount_total',
                'items.*.id' => ['sometimes', 'required', 'integer', 'distinct', Rule::exists('mm_customer_po_items', 'id')->where('customer_po_id', $po->id)->whereNull('deleted_at')],
                'items.*.mix_design_id' => ['required', 'integer', $exists('mm_mix_designs')],
                'items.*.quantity' => 'required|numeric|between:0.001,999999',
                'items.*.rate' => 'required|numeric|between:0,99999999',
                'items.*.tax_id' => ['nullable', 'integer', $exists('mm_taxes')->where('tax_type', 'Sales')->where('status', 1)->whereNull('parent_id')],
                'items.*.concrete_pump' => 'nullable|string|max:100',
                'items.*.pump_rate' => 'nullable|numeric|between:0,99999999',
                'items.*.untaxed_amount' => 'sometimes|nullable|numeric|min:0',
                'items.*.tax_amount' => 'sometimes|nullable|numeric|min:0',
                'items.*.amount_total' => 'sometimes|nullable|numeric|min:0',
            ], [
                'status.in' => 'Status must be 0 (Draft), 1 (Confirmed), or 2 (Completed).',
                'reference.unique' => 'This customer PO reference is already used in the selected plant.',
                'items.*.id.exists' => 'The selected item does not belong to this customer PO or has been deleted.',
                'items.*.id.distinct' => 'Each customer PO item ID must appear only once.',
                'required' => 'The :attribute field is required.',
                'required_without' => 'The :attribute field is required when :values is not provided.',
                'items.min' => 'Add at least one customer PO item.',
                'items.array' => 'The items field must be an array.',
                'order_date.date_format' => 'The order date must use YYYY-MM-DD format.',
                'is_tax_inclusive.boolean' => 'The tax inclusive field must be true or false.',
                'patron_id.exists' => 'The selected customer is unavailable in the selected plant.',
                'site_id.exists' => 'The selected site is unavailable in the selected plant.',
            ]);
            $replaceItems = array_key_exists('items', $data)
                || (array_key_exists('is_tax_inclusive', $data) && (bool) $data['is_tax_inclusive'] !== (bool) $po->is_tax_inclusive);
            // Omitted fields retain their saved values; explicit nulls still undergo validation.
            $data = array_replace([
                'patron_id' => $po->patron_id,
                'site_id' => $po->site_id,
                'quotation_id' => $po->quotation_id,
                'order_date' => $po->order_date?->format('Y-m-d'),
                'is_tax_inclusive' => (bool) $po->is_tax_inclusive,
            ], $data);
            if ($replaceItems && !array_key_exists('items', $data)) {
                $data['items'] = $po->items()->get()->map(fn ($item) => $item->only([
                    'id', 'mix_design_id', 'quantity', 'rate', 'tax_id', 'concrete_pump', 'pump_rate',
                ]))->all();
            }
            $quote = empty($data['quotation_id']) ? null : Access::customerPOQuotations()->lockForUpdate()->findOrFail($data['quotation_id']);
            abort_unless($po->quotation_id == ($data['quotation_id'] ?? null), 422, 'The linked quotation cannot be changed.');
            if ($quote) {
                abort_unless((int) $quote->status === Quotation::STATUS_ACCEPTED, 422, 'Only approved quotations can become customer POs.');
                abort_if($quote->customerPOs()->whereKeyNot($po->id)->exists(), 422, 'Quotation already converted.');
                abort_unless((int) $quote->patron_id === (int) $data['patron_id'] && (int) $quote->site_id === (int) $data['site_id'], 422, 'Customer and site must match the quotation.');
                $data['is_tax_inclusive'] = (bool) $quote->is_tax_inclusive;
                $data['adjustment'] = $quote->adjustment ?? 0;
                $items = $quote->items->map(fn ($item) => $item->only(['mix_design_id', 'quantity', 'rate', 'tax_id', 'concrete_pump', 'pump_rate', 'untaxed_amount', 'tax_amount', 'amount_total']))->all();
                abort_if(empty($items), 422, 'Quotation has no items.');
                $quote->update(['is_customer_po' => true]);
            } else {
                $items = $replaceItems ? QuotationCalculator::items($data['items'], $data['is_tax_inclusive']) : [];
            }
            $totalQuantity = $replaceItems ? array_sum(array_column($items, 'quantity')) : (float) $po->items()->sum('quantity');
            if (array_key_exists('total_qty', $data) && abs((float) $data['total_qty'] - $totalQuantity) > 0.00001) {
                throw ValidationException::withMessages(['total_qty' => 'Total quantity must equal the sum of item quantities ('.$totalQuantity.').']);
            }
            unset($data['items'], $data['total_qty']);
            $data['sales_executive_id'] = $quote?->sales_executive_id ?? Access::orderOwnerId(
                $po->sales_executive_id, true, $data['sales_executive_id'] ?? null,
                array_key_exists('sales_executive_id', $data),
            );
            $po->update($data);
            if ($replaceItems) {
                $retainedIds = [];
                foreach ($items as $item) {
                    $itemId = $item['id'] ?? null;
                    unset($item['id'], $item['discount_type'], $item['discount'], $item['discount_amount']);
                    if ($itemId !== null) {
                        $existing = $po->items()->findOrFail($itemId);
                        $existing->update($item);
                        $retainedIds[] = $existing->id;
                    } else {
                        $retainedIds[] = $po->items()->create($item)->id;
                    }
                }
                // A supplied item list replaces the list; omitted existing lines are removed.
                $po->items()->whereNotIn('id', $retainedIds)->get()->each->delete();
            }
            $po->updateTotals();
            return response()->json(['success' => true, 'data' => $po->fresh()->load('items')], 200);
        });
    }

    public function convertQuotation(Request $request, int $quotation)
    {
        return DB::transaction(function () use ($request, $quotation) {
            Plant::whereKey(Access::plantId())->lockForUpdate()->firstOrFail();
            $quote = Access::customerPOQuotations()->lockForUpdate()->findOrFail($quotation);
            $data = $request->validate([
                'order_date' => ['sometimes', 'required', 'date_format:Y-m-d'],
                'customer_po_reference' => ['nullable', 'string', 'max:150'],
                'notes' => ['nullable', 'string', 'max:10000'],
            ]);
            $conversion = clone $request;
            $conversion->replace(array_merge($data, [
                'quotation_id' => $quote->id,
                'patron_id' => $quote->patron_id,
                'site_id' => $quote->site_id,
                'order_date' => $data['order_date'] ?? now()->toDateString(),
                'is_tax_inclusive' => (bool) $quote->is_tax_inclusive,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $quote->notes,
            ]));
            return $this->save($conversion);
        });
    }

    public function destroy(int $customer_po)
    {
        return DB::transaction(function () use ($customer_po) {
            Plant::whereKey(Access::plantId())->lockForUpdate()->firstOrFail();
            $po = Access::customerPOs()->lockForUpdate()->findOrFail($customer_po);
            $this->editable($po);
            $quote = $po->quotation_id ? Access::customerPOQuotations()->lockForUpdate()->findOrFail($po->quotation_id) : null;
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
            $po->update(['is_sales_order' => 1, 'status' => $allocated >= $po->items()->sum('quantity') ? CustomerPO::STATUS_COMPLETED : CustomerPO::STATUS_CONFIRMED]);
            return response()->json(['success' => true, 'data' => $orders], 201);
        });
    }
}
