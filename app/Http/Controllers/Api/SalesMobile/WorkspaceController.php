<?php

/*
Author: ragul-onemodo
Created: 2026-10-09 18:04:42 Asia/Calcutta (UTC+05:30)
*/

namespace App\Http\Controllers\Api\SalesMobile;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\ConcreteBatchingSchedule;
use App\Models\CrmActivity;
use App\Models\CrmLead;
use App\Models\MixDesign;
use App\Models\ProductUnit;
use App\Models\Personnel;
use App\Models\Tax;
use App\Models\CustomerPO;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Services\Mobile\MobileAccess as Access;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function salesExecutives(Request $r)
    {
        $r->validate(['search' => 'nullable|string|max:100']);
        $query = Personnel::withoutGlobalScope('plant_id')
            ->where('plant_id', Access::plantId())
            ->whereHas('designation', fn ($q) => $q->where(fn ($designation) => $designation
                ->where('code', 'SE')->orWhere('name', 'Sales Executive')));
        if ($r->filled('search')) {
            $query->where(fn ($q) => $q->where('first_name', 'like', '%'.$r->search.'%')
                ->orWhere('last_name', 'like', '%'.$r->search.'%')
                ->orWhere('employee_code', 'like', '%'.$r->search.'%'));
        }

        return response()->json(['success' => true, 'data' => $query
            ->orderBy('first_name')->orderBy('id')
            ->get(['id', 'first_name', 'last_name','plant_id','employee_code'])
            ->map(fn ($personnel) => ['id' => $personnel->id, 'label' => $personnel->label, 'plant_id' => $personnel->plant_id, 'employee_code' => $personnel->employee_code])]);
    }

    public function mixDesigns(Request $r)
    {
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
        $r->validate(['search' => 'nullable|string|max:100', 'per_page' => 'nullable|integer|between:1,100']);
        $q = Tax::where('plant_id', Access::plantId())->where('status', 1)->ofTaxType('Sales')->ofGroup(['GST', 'IGST'])->parentOnly()
            ->with(['children' => fn ($q) => $q->where('plant_id', Access::plantId())->where('status', 1)->ofTaxType('Sales')->ofGroup(['GST', 'CGST', 'SGST', 'IGST'])]);
        if ($r->filled('search')) {
            $q->where('tax_name', 'like', '%'.$r->search.'%');
        }

        return response()->json(['success' => true, 'data' => $q->orderBy('tax_name')->orderBy('id')->paginate($r->integer('per_page', 20))]);
    }

    public function sites(Request $r)
    {
        $r->validate(['search' => 'nullable|string|max:100', 'per_page' => 'nullable|integer|between:1,100']);
        $q = Access::plantSites();
        if ($r->filled('search')) {
            $q->where('name', 'like', '%'.$r->search.'%');
        }
        $page = $q->orderBy('name')->orderBy('id')->paginate($r->integer('per_page', 20));
        $page->getCollection()->each->setAppends([]);

        return response()->json(['success' => true, 'data' => $page]);
    }

    public function site(int $site)
    {
        $model = Access::plantSites()->findOrFail($site)->setAppends([]);

        return response()->json(['success' => true, 'data' => $model]);
    }

    public function customers(Request $r)
    {
        $r->validate(['search' => 'nullable|string|max:100', 'per_page' => 'nullable|integer|between:1,100']);
        $q = Access::customers()->select(['id', 'legal_name', 'plant_id'])->with('contacts');
        if ($r->filled('search')) {
            $q->where('legal_name', 'like', '%'.$r->search.'%');
        }
        $page = $q->orderBy('legal_name')->paginate($r->integer('per_page', 20));
        $page->getCollection()->each->setAppends([]);

        return response()->json(['success' => true, 'data' => $page]);
    }

    public function customer(int $customer)
    {
        $model = Access::customers()->findOrFail($customer)->load('contacts')->setAppends([]);
        $data = ['customer' => $model];
        $orders = Access::orders()->where('customer_id', $customer)->get();
        $progress = $orders->map(fn ($order) => OrderController::progress($order));
        $data['progress'] = collect(['batch_count', 'completed_batches', 'committed_m3', 'dispatched_m3', 'delivered_m3', 'remaining_delivery_m3'])->mapWithKeys(fn ($key) => [$key => $progress->sum($key)]);

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function dashboard()
    {
        $data = ['plant_id' => Access::plantId(), 'as_of' => now()->toIso8601String()];
        $data['open_leads'] = Access::leads()->whereNotIn('status', CrmLead::CLOSED_STATUSES)->count();
        $q = Access::activities()->whereNull('completed_at')->where('type', '!=', 'System');
        $data['followups_today'] = (clone $q)->whereDate('due_at', today())->count();
        $data['overdue_followups'] = (clone $q)->where('due_at', '<', now())->count();
        $data['active_orders'] = Access::orders()->whereIn('status', [1, 2])->count();
        $data['completed_batches'] = Batch::where('plant_id', Access::plantId())->whereIn('sales_order_id', Access::orders()->select('id'))->where('status', Batch::STATUS_COMPLETED)->count();
        $data['quotations_pending_approval'] = Access::quotations()->where('mobile_approval_state', 'pending')->count();

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function marketing()
    {
        return response()->json(['success' => true, 'data' => ['by_source' => Access::leads()->selectRaw('source, COUNT(*) as total')->groupBy('source')->get(), 'site_visits' => Access::activities()->where('type', 'Site Visit')->count()]]);
    }

    public function lookups()
    {
        $plantId = Access::plantId();
        $data = ['lead_statuses' => CrmLead::STATUSES, 'lead_status_options' => CrmLead::defaultStatusOptions(), 'lead_sources' => CrmLead::SOURCES, 'priorities' => CrmLead::PRIORITIES, 'activity_types' => CrmActivity::TYPES, 'pump_types' => ConcreteBatchingSchedule::PUMP_TYPES];
        $data['customer_po_statuses'] = CustomerPO::statusOptions();
        $data['quotation_statuses'] = Quotation::statusOptions();
        $data['sales_order_statuses'] = SalesOrder::statusOptions();
        // $data['mix_designs'] = MixDesign::where('plant_id', Access::plantId())->where('is_active', 1)->get(['id', 'design_name', 'design_code']);
        $data['sales_taxes'] = Tax::withoutGlobalScope('plant_id')->where('plant_id', $plantId)->where('status', 1)->ofTaxType('Sales')->ofGroup(['GST', 'IGST'])->parentOnly()->with(['children' => fn ($q) => $q->withoutGlobalScope('plant_id')->where('plant_id', $plantId)->where('status', 1)->ofTaxType('Sales')->ofGroup(['GST', 'CGST', 'SGST', 'IGST'])])->get();
        // $data['sites'] = Access::plantSites()->get(['id', 'name']);
        // $data['units'] = ProductUnit::query()->get();
        // $data['customers'] = Access::customers()->get(['id', 'legal_name'])->each->setAppends([]);
        // $data['customer_pos'] = Access::customerPOs()->with('items')->get();
        // $data['sales_orders'] = Access::orders()->whereIn('status', [1, 2])->get(['id', 'prefix', 'order_no', 'customer_id', 'site_id', 'mix_design_id', 'total_qty']);
        $data['vehicles'] = MachinesDropdown(['Transit Mixer', 'Truck'], plantId: $plantId);
        $data['pumps'] = ConcretePumpOptions($plantId);
        $data['drivers'] = DriversOperatorsDropdown(null, $plantId);

        return response()->json(['success' => true, 'data' => $data]);
    }
}
