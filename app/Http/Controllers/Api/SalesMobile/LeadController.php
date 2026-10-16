<?php
/*
Author: ragul-onemodo
Created: 2026-10-09 18:01:41 Asia/Calcutta (UTC+05:30)
*/

namespace App\Http\Controllers\Api\SalesMobile;

use App\Http\Controllers\Controller;
use App\Models\{CrmLead, CrmActivity, User};
use App\Services\CrmLeadService;
use App\Services\Mobile\MobileAccess as Access;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeadController extends Controller
{
    public function index(Request $r)
    {
        Access::authorize('CRM_LEAD', 'VIEW');
        $r->validate(['search' => 'nullable|string|max:100', 'status' => ['nullable', Rule::in(CrmLead::STATUSES)], 'per_page' => 'nullable|integer|between:1,100']);
        $q = Access::leads()->with(['owner:id,username', 'mixDesign:id,design_name'])->withMin(['activities as next_follow_up' => fn ($a) => $a->whereNull('completed_at')], 'due_at');
        if ($r->filled('search')) $q->where(fn ($q) => $q->where('contact_name', 'like', '%'.$r->search.'%')->orWhere('company_name', 'like', '%'.$r->search.'%')->orWhere('phone', 'like', '%'.$r->search.'%'));
        if ($r->filled('status')) $q->where('status', $r->status);
        return response()->json(['success' => true, 'data' => $q->latest('id')->paginate($r->integer('per_page', 20))]);
    }

    public function show(int $lead)
    {
        Access::authorize('CRM_LEAD', 'VIEW');
        return response()->json(['success' => true, 'data' => Access::leads()->findOrFail($lead)->load(['mixDesign:id,design_name', 'activities' => fn ($q) => $q->latest('id'), 'deal', 'customer', 'owner:id,username'])]);
    }

    private function data(Request $r, ?CrmLead $lead = null): array
    {
        $r->merge(['email' => $r->filled('email') ? strtolower(trim($r->email)) : null, 'phone' => $r->filled('phone') ? preg_replace('/[^0-9]/', '', $r->phone) : null]);
        $data = $r->validate([
            'contact_name' => 'required|string|max:200', 'company_name' => 'nullable|string|max:200',
            'phone' => 'nullable|required_without:email|regex:/^[0-9]{7,15}$/', 'email' => 'nullable|required_without:phone|email|max:150',
            'enquiry_date' => 'required|date_format:Y-m-d', 'source' => ['required', Rule::in(CrmLead::SOURCES)],
            'priority' => ['required', Rule::in(CrmLead::PRIORITIES)], 'status' => ['required', Rule::in($lead?->converted_at ? ['Converted'] : ['New', 'Contacted', 'Qualified', 'Unqualified'])],
            'assigned_to' => 'nullable|integer', 'address' => 'nullable|string|max:400', 'project_name' => 'nullable|string|max:200',
            'requirement' => 'prohibited', 'mix_design_id' => ['nullable', 'integer', Rule::exists('mm_mix_designs', 'id')->where('plant_id', Access::plantId())->whereNull('deleted_at')->where(fn ($q) => $q->where('is_active', 1)->when($lead?->mix_design_id, fn ($q) => $q->orWhere('id', $lead->mix_design_id)))], 'estimated_quantity' => 'nullable|numeric|min:0|max:99999999999',
            'delivery_location' => 'nullable|string|max:500', 'expected_value' => 'required|numeric|min:0|max:99999999999999',
            'expected_purchase_date' => 'nullable|date_format:Y-m-d', 'remarks' => 'nullable|string|max:10000',
            'lost_reason' => 'nullable|required_if:status,Unqualified|string|max:2000',
        ]);
        abort_if(Access::isSalesExecutive() && isset($data['assigned_to']) && (int) $data['assigned_to'] !== (int) $r->user()->id, 403, 'Sales executives can only own their own leads.');
        $owner = $data['assigned_to'] ?? $lead?->assigned_to ?? $r->user()->id;
        if ((int) $owner !== (int) ($lead?->assigned_to ?? $r->user()->id)) Access::authorize('CRM_LEAD', 'ASSIGN');
        $user = User::where('is_active', 1)->find($owner);
        abort_unless($user && Access::plants($user)->whereKey(Access::plantId())->exists(), 422, 'Owner must belong to this plant.');
        $data['assigned_to'] = $owner;
        $data['lost_reason'] = $data['status'] === 'Unqualified' ? ($data['lost_reason'] ?? null) : null;
        return $data;
    }

    public function store(Request $r, CrmLeadService $service)
    {
        Access::authorize('CRM_LEAD', 'CREATE');
        return response()->json(['success' => true, 'data' => $service->create(Access::plantId(), $this->data($r))->load('mixDesign:id,design_name')], 201);
    }
    public function update(Request $r, int $lead, CrmLeadService $service)
    {
        Access::authorize('CRM_LEAD', 'UPDATE');
        $model = Access::leads()->findOrFail($lead);
        return response()->json(['success' => true, 'data' => $service->update($model, $this->data($r, $model))->load('mixDesign:id,design_name')]);
    }
    public function destroy(int $lead)
    {
        Access::authorize('CRM_LEAD', 'DELETE');
        $model = Access::leads()->findOrFail($lead);
        abort_if($model->converted_at, 422, 'Converted leads cannot be deleted.');
        $model->delete();
        return response()->json(['success' => true, 'message' => 'Lead deleted.']);
    }
    public function convert(Request $r, int $lead, CrmLeadService $service)
    {
        Access::authorize('CRM_LEAD', 'CONVERT');
        $data = $r->validate(['customer_id' => ['nullable', 'integer', Access::exists(Access::customers())]]);
        return response()->json(['success' => true, 'data' => $service->convert(Access::leads()->findOrFail($lead), $data['customer_id'] ?? null)]);
    }

    private function activities()
    {
        return Access::activities()->where('type', '!=', 'System');
    }
    public function followups(Request $r)
    {
        Access::authorize('CRM_LEAD', 'VIEW');
        $r->validate(['view' => 'nullable|in:today,upcoming,overdue,completed,all', 'per_page' => 'nullable|integer|between:1,100']);
        $q = $this->activities()->with('lead:id,contact_name,company_name');
        $view = $r->input('view', 'today');
        if ($view === 'completed') $q->whereNotNull('completed_at');
        elseif ($view !== 'all') {
            $q->whereNull('completed_at');
            if ($view === 'today') $q->whereDate('due_at', today());
            if ($view === 'overdue') $q->where('due_at', '<', now());
            if ($view === 'upcoming') $q->where('due_at', '>', now());
        }
        return response()->json(['success' => true, 'data' => $q->orderBy('due_at')->orderBy('id')->paginate($r->integer('per_page', 20))]);
    }
    public function saveActivity(Request $r, ?int $activity = null)
    {
        Access::authorize('CRM_LEAD', 'UPDATE');
        $model = $activity ? $this->activities()->findOrFail($activity) : new CrmActivity;
        abort_if($model->completed_at, 422, 'Completed activities cannot be edited.');
        $data = $r->validate(['lead_id' => 'required|integer', 'type' => ['required', Rule::in(CrmActivity::TYPES)], 'subject' => 'required|string|max:200', 'description' => 'nullable|string|max:10000', 'due_at' => 'nullable|required_unless:type,Note|date']);
        $lead = Access::leads()->findOrFail($data['lead_id']);
        $model->fill(array_merge($data, ['plant_id' => Access::plantId(), 'assigned_to' => $lead->assigned_to, 'completed_at' => $data['type'] === 'Note' ? now() : null]))->save();
        return response()->json(['success' => true, 'data' => $model], $activity ? 200 : 201);
    }
    public function complete(Request $r, int $activity)
    {
        Access::authorize('CRM_LEAD', 'UPDATE');
        $data = $r->validate(['description' => 'nullable|string|max:10000']);
        $model = $this->activities()->findOrFail($activity);
        if (!$model->completed_at) $model->update(array_merge($data, ['completed_at' => now()]));
        return response()->json(['success' => true, 'data' => $model]);
    }
    public function deleteActivity(int $activity)
    {
        Access::authorize('CRM_LEAD', 'DELETE');
        $this->activities()->findOrFail($activity)->delete();
        return response()->json(['success' => true, 'message' => 'Activity deleted.']);
    }
}
