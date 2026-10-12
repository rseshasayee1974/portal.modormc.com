<?php
/*
Author: ragul-onemodo
Created: 2026-10-09 18:04:42 Asia/Calcutta (UTC+05:30)
*/

namespace App\Http\Controllers\Api\SalesMobile;

use App\Http\Controllers\Controller;
use App\Models\{Patron, CrmActivity, MixDesign, Site, Tax, Batch};
use App\Services\Mobile\MobileAccess as Access;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    private function customersQuery()
    {
        return Patron::where('plant_id', Access::plantId())->ofType('Customer')->when(!Access::all('PATRON'), function ($q) {
            $q->where(fn ($q) => $q->whereIn('id', Access::leads()->whereNotNull('customer_id')->select('customer_id'))
                ->orWhereIn('id', Access::orders()->select('customer_id'))->orWhereIn('id', Access::quotations()->select('patron_id')));
        });
    }
    public function customers(Request $r)
    {
        Access::authorize('PATRON', 'VIEW');
        $r->validate(['search' => 'nullable|string|max:100', 'per_page' => 'nullable|integer|between:1,100']);
        $q = $this->customersQuery()->select(['id', 'legal_name', 'plant_id'])->with('contacts');
        if ($r->filled('search')) $q->where('legal_name', 'like', '%'.$r->search.'%');
        $page = $q->orderBy('legal_name')->paginate($r->integer('per_page', 20));
        $page->getCollection()->each->setAppends([]);
        return response()->json(['success' => true, 'data' => $page]);
    }
    public function customer(int $customer)
    {
        Access::authorize('PATRON', 'VIEW');
        $model = $this->customersQuery()->findOrFail($customer)->load('contacts')->setAppends([]);
        $data = ['customer' => $model];
        if (Access::permits('SALES_ORDER', 'VIEW')) {
            $orders = Access::orders()->where('customer_id', $customer)->get();
            $progress = $orders->map(fn ($order) => OrderController::progress($order));
            $data['progress'] = collect(['batch_count', 'completed_batches', 'committed_m3', 'dispatched_m3', 'delivered_m3', 'remaining_delivery_m3'])->mapWithKeys(fn ($key) => [$key => $progress->sum($key)]);
        }
        return response()->json(['success' => true, 'data' => $data]);
    }
    public function dashboard()
    {
        $data = ['plant_id' => Access::plantId(), 'as_of' => now()->toIso8601String()];
        if (Access::permits('CRM_LEAD', 'VIEW')) {
            $data['open_leads'] = Access::leads()->whereNotIn('status', ['Converted', 'Unqualified'])->count();
            $q = CrmActivity::whereIn('lead_id', Access::leads()->select('id'))->whereNull('completed_at')->where('type', '!=', 'System');
            $data['followups_today'] = (clone $q)->whereDate('due_at', today())->count();
            $data['overdue_followups'] = (clone $q)->where('due_at', '<', now())->count();
        }
        if (Access::permits('SALES_ORDER', 'VIEW')) {
            $data['active_orders'] = Access::orders()->whereIn('status', [1, 2])->count();
            $data['completed_batches'] = Batch::where('plant_id', Access::plantId())->whereIn('sales_order_id', Access::orders()->select('id'))->where('status', Batch::STATUS_COMPLETED)->count();
        }
        if (Access::permits('QUOTATION', 'VIEW')) $data['quotations_pending_approval'] = Access::quotations()->where('mobile_approval_state', 'pending')->count();
        return response()->json(['success' => true, 'data' => $data]);
    }
    public function marketing()
    {
        Access::authorize('CRM_LEAD', 'VIEW');
        return response()->json(['success' => true, 'data' => ['by_source' => Access::leads()->selectRaw('source, COUNT(*) as total')->groupBy('source')->get(), 'site_visits' => CrmActivity::whereIn('lead_id', Access::leads()->select('id'))->where('type', 'Site Visit')->count()]]);
    }
    public function lookups(Request $r)
    {
        $data = ['lead_statuses' => \App\Models\CrmLead::STATUSES, 'lead_sources' => \App\Models\CrmLead::SOURCES, 'priorities' => \App\Models\CrmLead::PRIORITIES, 'activity_types' => CrmActivity::TYPES, 'pump_types' => \App\Models\ConcreteBatchingSchedule::PUMP_TYPES];
        if (Access::permits('CRM_LEAD', 'VIEW') || Access::permits('QUOTATION', 'VIEW') || Access::permits('SALES_ORDER', 'VIEW')) {
            $data['mix_designs'] = MixDesign::where('plant_id', Access::plantId())->where('is_active', 1)->get(['id', 'design_name', 'design_code']);
        }
        if (Access::permits('QUOTATION', 'VIEW') || Access::permits('SALES_ORDER', 'VIEW')) {
            $data['sales_taxes'] = Tax::where('plant_id', Access::plantId())->where('status', 1)->ofTaxType('Sales')->parentOnly()->with('children')->get();
            $data['sites'] = Site::where('plant_id', Access::plantId())->get(['id', 'name']);
            $data['units'] = \App\Models\ProductUnit::where('plant_id', Access::plantId())->get();
        }
        if (Access::permits('PATRON', 'VIEW')) $data['customers'] = $this->customersQuery()->get(['id', 'legal_name'])->each->setAppends([]);
        if (Access::permits('SALES_ORDER', 'VIEW')) {
            $data['customer_pos'] = \App\Models\CustomerPO::where('plant_id', Access::plantId())->when(!Access::all('SALES_ORDER'), fn ($q) => $q->where(function ($q) use ($r) { $q->where('created_by', $r->user()->id); if (Access::personnelId()) $q->orWhere('sales_executive_id', Access::personnelId()); }))->with('items')->get();
            $data['sales_orders'] = Access::orders()->whereIn('status', [1, 2])->get(['id', 'prefix', 'order_no', 'customer_id', 'site_id', 'mix_design_id', 'total_qty']);
        }
        if (Access::permits('BATCHING_SCHEDULE', 'VIEW')) {
            $data['vehicles'] = MachinesDropdown(['Transit Mixer', 'Truck']);
            $data['pumps'] = ConcretePumpOptions();
            $data['drivers'] = DriversOperatorsDropdown();
        }
        return response()->json(['success' => true, 'data' => $data]);
    }
}
