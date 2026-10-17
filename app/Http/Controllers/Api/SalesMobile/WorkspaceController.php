<?php
/*
Author: ragul-onemodo
Created: 2026-10-09 18:04:42 Asia/Calcutta (UTC+05:30)
*/

namespace App\Http\Controllers\Api\SalesMobile;

use App\Http\Controllers\Controller;
use App\Models\{CrmActivity, MixDesign, Tax, Batch};
use App\Services\Mobile\MobileAccess as Access;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function mixDesigns(Request $r)
    {
        abort_unless(Access::permits('MIX_DESIGN', 'VIEW') || Access::permits('CRM_LEAD', 'VIEW')
            || Access::permits('QUOTATION', 'VIEW') || Access::permits('SALES_ORDER', 'VIEW'), 403, 'Mix design lookup access is required.');
        $r->validate(['search' => 'nullable|string|max:100', 'per_page' => 'nullable|integer|between:1,100']);
        $q = MixDesign::where('plant_id', Access::plantId())->where('is_active', 1);
        if ($r->filled('search')) {
            $q->where(fn ($q) => $q->where('design_name', 'like', '%'.$r->search.'%')
                ->orWhere('design_code', 'like', '%'.$r->search.'%'));
        }
        return response()->json(['success' => true, 'data' => $q->orderBy('design_name')->orderBy('id')
            ->paginate($r->integer('per_page', 20), ['id', 'design_name', 'design_code', 'concrete_grade_id', 'unit_id', 'rate_per_qty'])]);
    }

    public function taxes(Request $r)
    {
        abort_unless(Access::permits('TAX', 'VIEW') || Access::permits('QUOTATION', 'VIEW')
            || Access::permits('SALES_ORDER', 'VIEW'), 403, 'Sales tax lookup access is required.');
        $r->validate(['search' => 'nullable|string|max:100', 'per_page' => 'nullable|integer|between:1,100']);
        $q = Tax::where('plant_id', Access::plantId())->where('status', 1)->ofTaxType('Sales')->ofGroup(['GST', 'IGST'])->parentOnly()
            ->with(['children' => fn ($q) => $q->where('plant_id', Access::plantId())->where('status', 1)->ofTaxType('Sales')->ofGroup(['GST', 'CGST', 'SGST', 'IGST'])]);
        if ($r->filled('search')) $q->where('tax_name', 'like', '%'.$r->search.'%');
        return response()->json(['success' => true, 'data' => $q->orderBy('tax_name')->orderBy('id')->paginate($r->integer('per_page', 20))]);
    }

    public function sites(Request $r)
    {
        Access::authorize('SITE', 'VIEW');
        $r->validate(['search' => 'nullable|string|max:100', 'per_page' => 'nullable|integer|between:1,100']);
        $q = Access::plantSites();
        if ($r->filled('search')) $q->where('name', 'like', '%'.$r->search.'%');
        $page = $q->orderBy('name')->orderBy('id')->paginate($r->integer('per_page', 20));
        $page->getCollection()->each->setAppends([]);
        return response()->json(['success' => true, 'data' => $page]);
    }

    public function site(int $site)
    {
        Access::authorize('SITE', 'VIEW');
        $model = Access::plantSites()->findOrFail($site)->setAppends([]);
        return response()->json(['success' => true, 'data' => $model]);
    }

    public function customers(Request $r)
    {
        Access::authorize('PATRON', 'VIEW');
        $r->validate(['search' => 'nullable|string|max:100', 'per_page' => 'nullable|integer|between:1,100']);
        $q = Access::customers()->select(['id', 'legal_name', 'plant_id'])->with('contacts');
        if ($r->filled('search')) $q->where('legal_name', 'like', '%'.$r->search.'%');
        $page = $q->orderBy('legal_name')->paginate($r->integer('per_page', 20));
        $page->getCollection()->each->setAppends([]);
        return response()->json(['success' => true, 'data' => $page]);
    }
    public function customer(int $customer)
    {
        Access::authorize('PATRON', 'VIEW');
        $model = Access::customers()->findOrFail($customer)->load('contacts')->setAppends([]);
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
            $q = Access::activities()->whereNull('completed_at')->where('type', '!=', 'System');
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
        return response()->json(['success' => true, 'data' => ['by_source' => Access::leads()->selectRaw('source, COUNT(*) as total')->groupBy('source')->get(), 'site_visits' => Access::activities()->where('type', 'Site Visit')->count()]]);
    }
    public function lookups(Request $r)
    {
        $data = ['lead_statuses' => \App\Models\CrmLead::STATUSES, 'lead_sources' => \App\Models\CrmLead::SOURCES, 'priorities' => \App\Models\CrmLead::PRIORITIES, 'activity_types' => CrmActivity::TYPES, 'pump_types' => \App\Models\ConcreteBatchingSchedule::PUMP_TYPES];
        if (Access::permits('CRM_LEAD', 'VIEW') || Access::permits('QUOTATION', 'VIEW') || Access::permits('SALES_ORDER', 'VIEW')) {
            $data['mix_designs'] = MixDesign::where('plant_id', Access::plantId())->where('is_active', 1)->get(['id', 'design_name', 'design_code']);
        }
        if (Access::permits('QUOTATION', 'VIEW') || Access::permits('SALES_ORDER', 'VIEW')) {
            $data['sales_taxes'] = Tax::where('plant_id', Access::plantId())->where('status', 1)->ofTaxType('Sales')->ofGroup(['GST', 'IGST'])->parentOnly()->with(['children' => fn ($q) => $q->where('plant_id', Access::plantId())->where('status', 1)->ofTaxType('Sales')->ofGroup(['GST', 'CGST', 'SGST', 'IGST'])])->get();
            $data['sites'] = Access::plantSites()->get(['id', 'name']);
            $data['units'] = \App\Models\ProductUnit::query()->get();
        }
        if (Access::permits('PATRON', 'VIEW')) $data['customers'] = Access::customers()->get(['id', 'legal_name'])->each->setAppends([]);
        if (Access::permits('SALES_ORDER', 'VIEW')) {
            $data['customer_pos'] = Access::customerPOs()->with('items')->get();
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
