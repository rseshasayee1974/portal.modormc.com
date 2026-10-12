<?php
/*
Author: ragul-onemodo
Created: 2026-10-09 18:04:42 Asia/Calcutta (UTC+05:30)
*/

namespace App\Http\Controllers\Api\SalesMobile;

use App\Http\Controllers\Controller;
use App\Models\{SalesOrder, CustomerPO, Plant, Batch, Dispatch};
use App\Services\Mobile\MobileAccess as Access;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index(Request $r)
    {
        Access::authorize('SALES_ORDER', 'VIEW');
        $r->validate(['per_page' => 'nullable|integer|between:1,100', 'search' => 'nullable|string|max:100', 'customer_id' => 'nullable|integer']);
        $q = Access::orders()->with(['customer:id,legal_name', 'site:id,name', 'mixDesign:id,design_name']);
        if ($r->filled('search')) $q->where('order_no', 'like', '%'.$r->search.'%');
        if ($r->filled('customer_id')) $q->where('customer_id', $r->customer_id);
        return response()->json(['success' => true, 'data' => $q->latest('id')->paginate($r->integer('per_page', 20))]);
    }
    public function show(int $order)
    {
        Access::authorize('SALES_ORDER', 'VIEW');
        $model = Access::orders()->findOrFail($order)->load(['customer', 'site', 'mixDesign']);
        return response()->json(['success' => true, 'data' => ['order' => $model, 'progress' => self::progress($model)]]);
    }
    public static function progress(SalesOrder $order): array
    {
        $batches = $order->batches()->where('plant_id', Access::plantId())->get(['id', 'status', 'batch_size']);
        $trips = $order->dispatches()->where('plant_id', Access::plantId())->withActiveBatch()->with('batch:id,batch_size')->whereNotIn('dispatch_status', ['Draft', 'Cancelled', 'Loading'])->get();
        $delivered = $trips->whereIn('dispatch_status', ['Delivered', 'Completed']);
        return ['ordered_m3' => (float) $order->total_qty, 'batch_count' => $batches->count(),
            'completed_batches' => $batches->where('status', Batch::STATUS_COMPLETED)->count(),
            'cancelled_batches' => $batches->where('status', Batch::STATUS_CANCELLED)->count(),
            'committed_m3' => (float) $batches->where('status', '!=', Batch::STATUS_CANCELLED)->sum('batch_size'),
            'dispatched_m3' => (float) $trips->sum('delivered_qty'), 'delivered_m3' => (float) $delivered->sum('delivered_qty'),
            'remaining_delivery_m3' => max(0, round((float) $order->total_qty - $delivered->sum('delivered_qty'), 3))];
    }
    public function batches(Request $r, int $order)
    {
        Access::authorize('SALES_ORDER', 'VIEW');
        $r->validate(['per_page' => 'nullable|integer|between:1,100']);
        $model = Access::orders()->findOrFail($order);
        return response()->json(['success' => true, 'data' => $model->batches()->select(['id', 'batch_no', 'sales_order_id', 'batch_size', 'status', 'start_time', 'end_time'])->orderBy('batch_no')->paginate($r->integer('per_page', 20))]);
    }
    public function dispatches(Request $r, int $order)
    {
        Access::authorize('SALES_ORDER', 'VIEW');
        $r->validate(['per_page' => 'nullable|integer|between:1,100']);
        $model = Access::orders()->findOrFail($order);
        return response()->json(['success' => true, 'data' => $model->dispatches()->withActiveBatch()->with(['truck:id,registration', 'batch:id,batch_no,batch_size'])->select(['id', 'sales_order_id', 'batch_id', 'truck_id', 'prefix', 'dispatch_no', 'dispatch_time', 'delivery_time', 'delivered_qty', 'dispatch_status'])->orderBy('dispatch_time')->orderBy('id')->paginate($r->integer('per_page', 20))]);
    }
    private function data(Request $r): array
    {
        $exists = fn ($table) => Rule::exists($table, 'id')->where('plant_id', Access::plantId())->whereNull('deleted_at');
        $data = $r->validate([
            'customer_id' => ['required', 'integer', $exists('mm_patrons')], 'site_id' => ['required', 'integer', $exists('mm_sites')],
            'mix_design_id' => ['required', 'integer', $exists('mm_mix_designs')], 'customer_po_id' => ['required', 'integer', $exists('mm_customer_pos')],
            'total_qty' => 'required|numeric|between:0.01,999999', 'scheduled_start' => 'nullable|date',
            'scheduled_end' => 'nullable|date|after_or_equal:scheduled_start',
        ]);
        $po = CustomerPO::findOrFail($data['customer_po_id']);
        abort_unless((int) $po->patron_id === (int) $data['customer_id'] && (int) $po->site_id === (int) $data['site_id'], 422, 'Customer and site must match the customer PO.');
        if (!Access::all('SALES_ORDER')) abort_unless((int) $po->created_by === (int) $r->user()->id || (Access::personnelId() && (int) $po->sales_executive_id === Access::personnelId()), 404);
        $item = $po->items()->where('mix_design_id', $data['mix_design_id'])->firstOrFail();
        $data['sales_executive_id'] = Access::personnelId();
        $data['rate'] = $item->rate;
        $data['tax_id'] = $item->tax_id;
        $data['is_tax_inclusive'] = $po->is_tax_inclusive;
        $data['concrete_pump'] = $item->concrete_pump;
        $data['pump_rate'] = $item->pump_rate ?? 0;
        return $data;
    }
    public function store(Request $r)
    {
        Access::authorize('SALES_ORDER', 'CREATE');
        $data = $this->data($r);
        return DB::transaction(function () use ($data) {
            Plant::whereKey(Access::plantId())->lockForUpdate()->firstOrFail();
            $number = SalesOrder::generateOrderNo(Access::plantId());
            $model = SalesOrder::create(array_merge($data, ['plant_id' => Access::plantId(), 'prefix' => $number['prefix'], 'order_no' => $number['next_number'], 'status' => SalesOrder::STATUS_CONFIRMED, 'produced_qty' => 0]));
            return response()->json(['success' => true, 'data' => $model], 201);
        });
    }
    public function update(Request $r, int $order)
    {
        Access::authorize('SALES_ORDER', 'UPDATE');
        return DB::transaction(function () use ($r, $order) {
            $model = Access::orders()->lockForUpdate()->findOrFail($order);
            $this->editable($model);
            $model->update($this->data($r));
            return response()->json(['success' => true, 'data' => $model]);
        });
    }
    private function editable(SalesOrder $model): void
    {
        abort_if(in_array((int) $model->status, [SalesOrder::STATUS_COMPLETED, SalesOrder::STATUS_CANCELLED]), 422, 'Completed or cancelled orders cannot be edited or deleted.');
        abort_if($model->batches()->exists() || $model->dispatches()->exists() || \App\Models\ConcreteBatchingSchedule::where('sales_order_id', $model->id)->exists(), 422, 'Order has production, dispatch or scheduling activity.');
    }
    public function destroy(int $order)
    {
        Access::authorize('SALES_ORDER', 'DELETE');
        return DB::transaction(function () use ($order) {
            $model = Access::orders()->lockForUpdate()->findOrFail($order);
            $this->editable($model);
            $model->delete();
            return response()->json(['success' => true, 'message' => 'Sales order deleted.']);
        });
    }
}
