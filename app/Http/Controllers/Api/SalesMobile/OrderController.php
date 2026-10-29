<?php
/*
Author: ragul-onemodo
Created: 2026-10-09 18:04:42 Asia/Calcutta (UTC+05:30)
*/

namespace App\Http\Controllers\Api\SalesMobile;

use App\Http\Controllers\Controller;
use App\Models\{SalesOrder, Plant, Batch};
use App\Services\Mobile\MobileAccess as Access;
use App\Support\StoredDateSerializer;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function callAction($method, $parameters)
    {
        try {
            return parent::callAction($method, $parameters);
        } catch (ModelNotFoundException $exception) {
            if ($exception->getModel() !== SalesOrder::class) throw $exception;

            return response()->json([
                'message' => 'Sales order not found or unavailable in the selected plant.',
            ], 404);
        }
    }

    public function customerPOs(Request $r)
    {
        $r->validate([
            'per_page' => 'nullable|integer|between:1,100', 'page' => 'nullable|integer|min:1', 'search' => 'nullable|string|max:100',
            'customer_id' => 'nullable|integer', 'site_id' => 'nullable|integer', 'status' => 'nullable|integer|in:0,1,2',
        ]);
        $fields = ['id', 'prefix', 'reference', 'customer_po_reference', 'quotation_id', 'patron_id', 'site_id',
            'order_date', 'due_date', 'notes', 'sales_executive_id', 'concrete_pump', 'pump_rate', 'adjustment',
            'status', 'is_sales_order', 'is_tax_inclusive', 'amount_untaxed', 'amount_tax', 'amount_total',
            'converted_by_user_id', 'created_at', 'updated_at'];
        $itemFields = ['id', 'customer_po_id', 'mix_design_id', 'quantity', 'rate', 'tax_id',
            'concrete_pump', 'pump_rate', 'untaxed_amount', 'tax_amount', 'amount_total'];
        $q = Access::customerPOs()->select($fields)->with(['patron' => fn ($q) => $q->select('id', 'legal_name')->whereIn('id', Access::customers()->select('id')), 'site' => fn ($q) => $q->select('id', 'name')->whereIn('id', Access::sites()->select('id')), 'items' => fn ($items) => $items->select($itemFields)]);
        if ($r->filled('search')) $q->where(function ($search) use ($r) {
            $search->where('reference', 'like', '%'.$r->search.'%')
                ->orWhere('customer_po_reference', 'like', '%'.$r->search.'%');
        });
        if ($r->filled('customer_id')) $q->where('patron_id', $r->customer_id);
        if ($r->filled('site_id')) $q->where('site_id', $r->site_id);
        if ($r->filled('status')) $q->where('status', $r->status);
        $page = $q->latest('id')->paginate($r->integer('per_page', 20));
        $page->getCollection()->each(function ($po) use ($fields, $itemFields) {
            $po->setAppends(['amount_untaxed', 'amount_tax', 'amount_total'])->setVisible(array_merge($fields, ['patron', 'site', 'items']));
            $po->patron?->setAppends([]);
            $po->site?->setAppends([]);
            $po->items->each->setVisible(array_values(array_diff($itemFields, ['customer_po_id'])));
        });
        return response()->json(['success' => true, 'data' => $page]);
    }

    public function index(Request $r)
    {
        $statuses = [];
        $r->validate([
            'per_page' => 'nullable|integer|between:1,100', 'page' => 'nullable|integer|min:1',
            'search' => 'nullable|string|max:100', 'customer_id' => 'nullable|integer',
            'status' => ['nullable', function ($attribute, $value, $fail) use (&$statuses) {
                $values = is_array($value) ? $value : (is_string($value) || is_int($value) ? explode(',', (string) $value) : []);
                if (!$values || count($values) > 100) {
                    $fail('Status must contain one or more values from 0 to 4.');
                    return;
                }
                foreach ($values as $status) {
                    if ((!is_string($status) && !is_int($status)) || !preg_match('/^[0-4]$/', trim((string) $status))) {
                        $fail('Status must contain only 0 (Draft), 1 (Scheduled), 2 (In Progress), 3 (Completed), or 4 (Cancelled).');
                        return;
                    }
                    $statuses[] = (int) trim((string) $status);
                }
                $statuses = array_values(array_unique($statuses));
            }],
        ]);
        $q = Access::orders()->with(['customer' => fn ($q) => $q->select('id', 'legal_name')->whereIn('id', Access::customers()->select('id')), 'site' => fn ($q) => $q->select('id', 'name')->whereIn('id', Access::sites()->select('id')), 'mixDesign' => fn ($q) => $q->select('id', 'design_name')->where('plant_id', Access::plantId())]);
        if ($r->filled('search')) $q->where('order_no', 'like', '%'.$r->search.'%');
        if ($r->filled('customer_id')) $q->where('customer_id', $r->customer_id);
        if ($statuses) $q->whereIn('status', $statuses);
        $page = $q->latest('id')->paginate($r->integer('per_page', 20));
        $page->getCollection()->each(function ($order) {
            $order->customer?->setAppends([]);
            $order->site?->setAppends([]);
            $order->progress = self::progress($order);
        });
        return response()->json(['success' => true, 'data' => $page]);
    }
    public function show(int $order)
    {
        $model = Access::orders()->findOrFail($order)->load(['customer' => fn ($q) => $q->whereIn('id', Access::customers()->select('id')), 'site' => fn ($q) => $q->whereIn('id', Access::sites()->select('id')), 'mixDesign' => fn ($q) => $q->where('plant_id', Access::plantId())]);
        $model->customer?->setAppends([]);
        $model->site?->setAppends([]);
        return response()->json(['success' => true, 'data' => ['order' => $model, 'progress' => self::progress($model)]]);
    }
    public static function progress(SalesOrder $order): array
    {
        $batches = $order->batches()->where('plant_id', Access::plantId())->get(['id', 'status', 'batch_size']);
        $trips = $order->dispatches()->where('plant_id', Access::plantId())->withActiveBatch()->with('batch:id,batch_size')->whereNotIn('dispatch_status', ['Draft', 'Cancelled', 'Loading'])->get();
        $delivered = $trips->whereIn('dispatch_status', ['Delivered', 'Completed', 'Invoiced']);
        $dispatchedQty = round((float) $trips->sum('delivered_qty'), 3);
        $deliveredQty = round((float) $delivered->sum('delivered_qty'), 3);
        return ['ordered_m3' => (float) $order->total_qty, 'batch_count' => $batches->count(),
            'completed_batches' => $batches->where('status', Batch::STATUS_COMPLETED)->count(),
            'cancelled_batches' => $batches->where('status', Batch::STATUS_CANCELLED)->count(),
            'committed_m3' => round((float) $batches->where('status', '!=', Batch::STATUS_CANCELLED)->sum('batch_size'), 3),
            'dispatched_m3' => $dispatchedQty, 'delivered_m3' => $deliveredQty,
            'remaining_delivery_m3' => max(0, round((float) $order->total_qty - $deliveredQty, 3))];
    }
    public function batches(Request $r, int $order)
    {
        $r->validate(['per_page' => 'nullable|integer|between:1,100', 'page' => 'nullable|integer|min:1']);
        $model = Access::orders()->findOrFail($order);
        $fields = ['id', 'batch_no', 'sales_order_id', 'batch_size', 'status', 'start_time', 'end_time'];
        $page = $model->batches()->where('plant_id', Access::plantId())
            ->select($fields)
            ->orderBy('batch_no')->orderBy('id')->paginate($r->integer('per_page', 20));
        $page->getCollection()->each(fn ($batch) => $batch->setAppends([])->setVisible($fields));
        return response()->json(['success' => true, 'data' => $page]);
    }
    public function dispatches(Request $r, int $order)
    {
        $r->validate(['per_page' => 'nullable|integer|between:1,100', 'page' => 'nullable|integer|min:1']);
        $model = Access::orders()->findOrFail($order);
        $fields = ['id', 'sales_order_id', 'batch_id', 'truck_id', 'prefix', 'dispatch_no',
            'dispatch_time', 'delivery_time', 'delivered_qty', 'dispatch_status'];
        $page = $model->dispatches()->where('plant_id', Access::plantId())->withActiveBatch()
            ->with(['truck' => fn ($q) => $q->select('id', 'registration')->where('plant_id', Access::plantId()), 'batch' => fn ($q) => $q->select('id', 'batch_no', 'batch_size')->where('plant_id', Access::plantId())->where('sales_order_id', $model->id)])
            ->select($fields)
            ->orderBy('dispatch_time')->orderBy('id')->paginate($r->integer('per_page', 20));
        $page->getCollection()->each(function ($dispatch) use ($fields) {
            $dispatch->setAppends([])->setVisible(array_merge($fields, ['truck', 'batch']));
            $dispatch->truck?->setAppends([])->setVisible(['id', 'registration']);
            $dispatch->batch?->setAppends([])->setVisible(['id', 'batch_no', 'batch_size']);
        });
        $page->through(fn ($dispatch) => StoredDateSerializer::model($dispatch));
        return response()->json(['success' => true, 'data' => $page]);
    }
    private function data(Request $r, ?SalesOrder $order = null): array
    {
        if ($r->filled('pump_type') && !$r->filled('concrete_pump')) {
            $r->merge(['concrete_pump' => $r->input('pump_type')]);
        }
        if ($order && !$r->exists('customer_po_id')) $r->merge(['customer_po_id' => $order->customer_po_id]);
        $exists = fn ($table) => Rule::exists($table, 'id')->where('plant_id', Access::plantId())->whereNull('deleted_at');
        $data = $r->validate([
            'customer_id' => ['required', 'integer', Access::exists(Access::customers())], 'site_id' => ['required', 'integer', Access::exists(Access::sites())],
            'mix_design_id' => ['required', 'integer', $exists('mm_mix_designs')], 'customer_po_id' => ['nullable', 'integer', Access::exists(Access::customerPOs())],
            'sales_executive_id' => ['nullable', 'integer', Rule::exists('mm_personnels', 'id')->where('plant_id', Access::plantId())->whereNull('deleted_at')],
            'status' => ['nullable', 'integer', 'in:0,1,2,3,4'],
            'total_qty' => 'required|numeric|between:0.01,999999', 'scheduled_start' => 'nullable|date',
            'scheduled_end' => 'nullable|date|after_or_equal:scheduled_start',
            'rate' => 'required_without:customer_po_id|numeric|between:0,99999999',
            'tax_id' => ['nullable', 'integer', $exists('mm_taxes')->where('tax_type', 'Sales')->where('status', 1)->whereNull('parent_id')],
            'is_tax_inclusive' => 'nullable|boolean', 'concrete_pump' => 'nullable|string|max:100',
            'pump_type' => 'nullable|string|max:100',
            'pump_rate' => 'nullable|numeric|between:0,99999999',
            'uom_id' => ['nullable', 'integer', Rule::exists('mm_product_units', 'id')->whereNull('deleted_at')],
        ], [
            'customer_id.exists' => 'The selected customer is unavailable in the selected plant.',
            'site_id.exists' => 'The selected site is unavailable in the selected plant.',
            'mix_design_id.exists' => 'The selected mix design is unavailable in the selected plant.',
            'customer_po_id.exists' => 'The selected customer PO is unavailable to you in the selected plant.',
            'sales_executive_id.exists' => 'The selected sales executive is unavailable in the selected plant.',
            'rate.required_without' => 'A rate is required when no customer PO is selected.',
            'status.in' => 'Status must contain only 0 (Draft), 1 (Scheduled), 2 (In Progress), 3 (Completed), or 4 (Cancelled).',
        ]);
        $data['sales_executive_id'] = Access::orderOwnerId(
            $order?->sales_executive_id,
            $order !== null,
            $r->has('sales_executive_id') ? $r->input('sales_executive_id') : null,
            $r->has('sales_executive_id')
        );
        if ($r->has('status') && $data['status'] !== null) {
            $data['status'] = (int) $data['status'];
        } elseif ($order) {
            $data['status'] = (int) $order->status;
        } else {
            $data['status'] = SalesOrder::STATUS_CONFIRMED;
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn('mm_sales_orders', 'pump_type')) {
            $data['pump_type'] = $data['concrete_pump'] ?? null;
        } else {
            unset($data['pump_type']);
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn('mm_sales_orders', 'uom_id')) {
            if (!array_key_exists('uom_id', $data)) {
                $data['uom_id'] = $order?->uom_id ?? null;
            }
        } else {
            unset($data['uom_id']);
        }
        if (empty($data['customer_po_id'])) {
            $data['customer_po_id'] = null;
            $data['tax_id'] = $data['tax_id'] ?? null;
            $data['is_tax_inclusive'] = $data['is_tax_inclusive'] ?? false;
            $data['concrete_pump'] = $data['concrete_pump'] ?? null;
            $data['pump_rate'] = $data['pump_rate'] ?? 0;
            return $data;
        }
        $po = Access::customerPOs()->findOrFail($data['customer_po_id']);
        abort_unless((int) $po->patron_id === (int) $data['customer_id'] && (int) $po->site_id === (int) $data['site_id'], 422, 'Customer and site must match the customer PO.');
        $item = $po->items()->where('mix_design_id', $data['mix_design_id'])->first();
        if (!$item) throw ValidationException::withMessages([
            'mix_design_id' => ['The selected mix design is not available in the selected customer PO. Choose a mix design from its items or remove customer_po_id to use a direct rate.'],
        ]);
        if (Access::isCommercialAdministrator()) {
            $quantity = $po->items()->where('mix_design_id', $data['mix_design_id'])->sum('quantity');
            $allocated = $po->salesOrders()->where('mix_design_id', $data['mix_design_id'])
                ->where('status', '!=', SalesOrder::STATUS_CANCELLED)
                ->when($order, fn ($query) => $query->whereKeyNot($order->id))->sum('total_qty');
            abort_if($allocated + $data['total_qty'] > $quantity + 0.00001, 422, 'Quantity exceeds the remaining customer PO quantity.');
        }
        if (!$r->has('sales_executive_id') && (!$order || $order->sales_executive_id === null)) {
            $data['sales_executive_id'] = $po->sales_executive_id ?? $data['sales_executive_id'];
        }
        $data['rate'] = $item->rate;
        $data['tax_id'] = $item->tax_id;
        $data['is_tax_inclusive'] = $po->is_tax_inclusive;
        $data['concrete_pump'] = $item->concrete_pump;
        if (\Illuminate\Support\Facades\Schema::hasColumn('mm_sales_orders', 'pump_type')) {
            $data['pump_type'] = $item->concrete_pump;
        }
        $data['pump_rate'] = $item->pump_rate ?? 0;
        if (\Illuminate\Support\Facades\Schema::hasColumn('mm_sales_orders', 'uom_id') && (!array_key_exists('uom_id', $data) || $data['uom_id'] === null)) {
            $data['uom_id'] = $item->uom_id ?? $order?->uom_id ?? null;
        }
        return $data;
    }
    public function store(Request $r)
    {
        return DB::transaction(function () use ($r) {
            Plant::whereKey(Access::plantId())->lockForUpdate()->firstOrFail();
            $data = $this->data($r);
            $number = SalesOrder::generateOrderNo(Access::plantId());
            $model = SalesOrder::create(array_merge($data, ['plant_id' => Access::plantId(), 'prefix' => $number['prefix'], 'order_no' => $number['next_number'], 'status' => $data['status'] ?? SalesOrder::STATUS_CONFIRMED, 'produced_qty' => 0]));
            return response()->json(['success' => true, 'data' => $model->fresh()], 201);
        });
    }
    public function update(Request $r, int $order)
    {
        return DB::transaction(function () use ($r, $order) {
            Plant::whereKey(Access::plantId())->lockForUpdate()->firstOrFail();
            $model = Access::orders()->lockForUpdate()->findOrFail($order);
            $this->editable($model);
            $model->update($this->data($r, $model));
            return response()->json(['success' => true, 'data' => $model->fresh()]);
        });
    }
    private function editable(SalesOrder $model): void
    {
        abort_if(in_array((int) $model->status, [SalesOrder::STATUS_COMPLETED, SalesOrder::STATUS_CANCELLED]), 422, 'Completed or cancelled orders cannot be edited or deleted.');
        abort_if($model->batches()->exists() || $model->dispatches()->exists() || \App\Models\ConcreteBatchingSchedule::where('sales_order_id', $model->id)->exists(), 422, 'Order cannot be edited it has production, dispatch or scheduling activity.');
    }
    public function destroy(int $order)
    {
        return DB::transaction(function () use ($order) {
            Plant::whereKey(Access::plantId())->lockForUpdate()->firstOrFail();
            $model = Access::orders()->lockForUpdate()->findOrFail($order);
            $this->editable($model);
            $model->delete();
            return response()->json(['success' => true, 'message' => 'Sales order deleted.']);
        });
    }
}
