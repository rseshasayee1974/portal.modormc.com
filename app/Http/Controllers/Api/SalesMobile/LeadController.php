<?php

/*
Author: ragul-onemodo
Created: 2026-10-09 18:01:41 Asia/Calcutta (UTC+05:30)
*/

namespace App\Http\Controllers\Api\SalesMobile;

use App\Http\Controllers\Controller;
use App\Models\CrmActivity;
use App\Models\CrmLead;
use App\Models\Plant;
use App\Models\User;
use App\Services\CrmLeadService;
use App\Services\Mobile\MobileAccess as Access;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LeadController extends Controller
{
    protected function leads(): \Illuminate\Database\Eloquent\Builder
    {
        return Access::leads();
    }

    public function index(Request $r)
    {
        Access::authorize('CRM_LEAD', 'VIEW');
        $r->validate(['search' => 'nullable|string|max:100', 'status' => ['nullable', Rule::in(CrmLead::STATUSES)], 'per_page' => 'nullable|integer|between:1,100', 'page' => 'nullable|integer|min:1']);
        $q = $this->leads()->with(['owner:id,username', 'mixDesign' => fn ($q) => $q->select('id', 'design_name')->where('plant_id', Access::plantId())])->withMin(['activities as next_follow_up' => fn ($a) => $a->whereIn('id', Access::activities()->select('id'))->whereNull('completed_at')], 'due_at');
        if ($r->filled('search')) {
            $q->where(fn ($q) => $q->where('contact_name', 'like', '%'.$r->search.'%')->orWhere('company_name', 'like', '%'.$r->search.'%')->orWhere('phone', 'like', '%'.$r->search.'%'));
        }
        if ($r->filled('status')) {
            $q->where('status', $r->status);
        }

        return response()->json(['success' => true, 'data' => $q->latest('id')->paginate($r->integer('per_page', 20))]);
    }

    public function show(int $lead)
    {
        Access::authorize('CRM_LEAD', 'VIEW');
        $model = $this->leads()->findOrFail($lead)->load(['mixDesign' => fn ($q) => $q->select('id', 'design_name')->where('plant_id', Access::plantId()), 'activities' => fn ($q) => $q->whereIn('id', Access::activities()->select('id'))->latest('id'), 'deal' => fn ($q) => $q->where('plant_id', Access::plantId()), 'customer' => fn ($q) => $q->whereIn('id', Access::customers()->select('id')), 'owner:id,username']);
        $model->customer?->setAppends([]);
        $model->setAttribute('allowed_statuses', $model->allowedStatuses());
        $model->setAttribute('status_options', $model->statusOptions());

        return response()->json(['success' => true, 'data' => $model]);
    }

    protected function leadOwnerId(?CrmLead $lead): int
    {
        return (int) request()->user()->id;
    }

    private function data(Request $r, ?CrmLead $lead = null): array
    {
        foreach (['email', 'phone'] as $field) {
            if (array_key_exists($field, $r->all())) {
                $r->merge([$field => $r->filled($field) && is_string($r->input($field))
                    ? ($field === 'email' ? strtolower(trim($r->input($field))) : preg_replace('/[^0-9]/', '', $r->input($field)))
                    : $r->input($field)]);
            }
        }
        $rules = [
            'contact_name' => 'required|string|max:200', 'company_name' => 'nullable|string|max:200',
            'phone' => 'nullable|required_without:email|regex:/^[0-9]{7,15}$/', 'email' => 'nullable|required_without:phone|email|max:150',
            'enquiry_date' => 'required|date_format:Y-m-d', 'source' => ['required', Rule::in(CrmLead::SOURCES)],
            'priority' => ['required', Rule::in(CrmLead::PRIORITIES)], 'status' => ['required', Rule::in($lead ? $lead->allowedStatuses() : CrmLead::STANDARD_STATUSES)],
            'assigned_to' => 'nullable|integer', 'address' => 'nullable|string|max:400', 'project_name' => 'nullable|string|max:200',
            'requirement' => 'nullable', 'mix_design_id' => ['nullable', 'integer', Rule::exists('mm_mix_designs', 'id')->where('plant_id', Access::plantId())->whereNull('deleted_at')->where(fn ($q) => $q->where('is_active', 1)->when($lead?->mix_design_id, fn ($q) => $q->orWhere('id', $lead->mix_design_id)))], 'estimated_quantity' => 'nullable|numeric|min:0|max:99999999999',
            'delivery_location' => 'nullable|string|max:500', 'expected_value' => 'required|numeric|min:0|max:99999999999999',
            'expected_purchase_date' => 'nullable|date_format:Y-m-d', 'remarks' => 'nullable|string|max:10000',
            'lost_reason' => 'nullable|required_if:status,'.CrmLead::STATUS_UNQUALIFIED.'|string|max:2000',
        ];
        if ($lead) {
            $rules['phone'] = ['nullable', 'regex:/^[0-9]{7,15}$/'];
            $rules['email'] = ['nullable', 'email', 'max:150'];
            $rules['lost_reason'] = ['nullable', 'string', 'max:2000'];
            $rules['assigned_to'] = ['required', 'integer'];
            foreach ($rules as $field => $rule) {
                $rules[$field] = array_merge(['sometimes'], is_array($rule) ? $rule : explode('|', $rule));
            }
        }
        $data = $r->validate($rules, [
            'mix_design_id.exists' => 'The selected mix design is unavailable in this plant. Refresh the mix designs for the selected plant and choose an active design.',
        ]);
        abort_if(isset($data['assigned_to']) && (int) $data['assigned_to'] !== $this->leadOwnerId($lead), 403, 'Mobile leads must belong to the logged-in user.');
        if (! $lead || array_key_exists('assigned_to', $data)) {
            $owner = $data['assigned_to'] ?? $lead?->assigned_to ?? $r->user()->id;
            if ((int) $owner !== (int) ($lead?->assigned_to ?? $r->user()->id)) {
                Access::authorize('CRM_LEAD', 'ASSIGN');
            }
            $user = User::where('is_active', 1)->find($owner);
            abort_unless($user && Access::plants($user)->whereKey(Access::plantId())->exists(), 422, 'Owner must belong to this plant.');
            $data['assigned_to'] = $owner;
        }
        if (! $lead) {
            $data['lost_reason'] = $data['status'] === CrmLead::STATUS_UNQUALIFIED ? ($data['lost_reason'] ?? null) : null;
        }

        return $data;
    }

    public function store(Request $r, CrmLeadService $service)
    {
        Access::authorize('CRM_LEAD', 'CREATE');
        $data = $this->data($r);

        return response()->json(['success' => true, 'data' => $service->create(Access::plantId(), $data)->load(['mixDesign' => fn ($q) => $q->select('id', 'design_name')->where('plant_id', Access::plantId())])], 201);
    }

    public function approve(int $lead, CrmLeadService $service)
    {
        Access::authorize('CRM_LEAD', 'APPROVE');

        return DB::transaction(function () use ($lead, $service) {
            Plant::whereKey(Access::plantId())->lockForUpdate()->firstOrFail();
            $model = $this->leads()->lockForUpdate()->findOrFail($lead);
            if ($model->converted_at || ! in_array($model->status, CrmLead::QUOTATION_CONVERTIBLE_STATUSES, true)) {
                throw ValidationException::withMessages(['status' => 'Only an open, unconverted lead can be approved.']);
            }
            if ($model->status !== CrmLead::STATUS_APPROVED) {
                $model->update(['status' => CrmLead::STATUS_APPROVED]);
                $service->prepareCustomerAndSite($model);
                $service->log($model, 'Lead approved', 'Approved by user '.request()->user()->id);
            } else {
                $service->prepareCustomerAndSite($model);
            }

            return response()->json(['success' => true, 'data' => $model]);
        });
    }

    public function update(Request $r, int $lead, CrmLeadService $service)
    {
        Access::authorize('CRM_LEAD', 'UPDATE');
        $model = $this->leads()->findOrFail($lead);

        return response()->json(['success' => true, 'data' => $service->update($model, $this->data($r, $model))->load(['mixDesign' => fn ($q) => $q->select('id', 'design_name')->where('plant_id', Access::plantId())])]);
    }

    public function destroy(int $lead)
    {
        Access::authorize('CRM_LEAD', 'DELETE');
        $model = $this->leads()->findOrFail($lead);
        abort_if($model->converted_at, 422, 'Converted leads cannot be deleted.');
        $model->delete();

        return response()->json(['success' => true, 'message' => 'Lead deleted.']);
    }

    public function convert(Request $r, int $lead, CrmLeadService $service)
    {
        Access::authorize('CRM_LEAD', 'CONVERT');
        $data = $r->validate(['customer_id' => ['nullable', 'integer', Access::exists(Access::customers())]]);

        return response()->json(['success' => true, 'data' => $service->convert($this->leads()->findOrFail($lead), $data['customer_id'] ?? null)]);
    }

    public function statuses(?int $lead = null)
    {
        Access::authorize('CRM_LEAD', 'VIEW');
        $options = $lead
            ? $this->leads()->findOrFail($lead)->statusOptions()
            : CrmLead::defaultStatusOptions();

        return response()->json([
            'success' => true,
            'data' => $options,
            'statuses' => array_column($options, 'value'),
        ]);
    }

    private function activities()
    {
        return Access::activities()->where('type', '!=', 'System');
    }

    public function followups(Request $r)
    {
        Access::authorize('CRM_LEAD', 'VIEW');
        $r->validate(['view' => 'nullable|in:today,upcoming,overdue,completed,all', 'per_page' => 'nullable|integer|between:1,100', 'page' => 'nullable|integer|min:1']);
        $q = $this->activities()->with('lead:id,contact_name,company_name');
        $view = $r->input('view', 'today');
        if ($view === 'completed') {
            $q->whereNotNull('completed_at');
        } elseif ($view !== 'all') {
            $q->whereNull('completed_at');
            if ($view === 'today') {
                $q->whereDate('due_at', today());
            }
            if ($view === 'overdue') {
                $q->where('due_at', '<', now());
            }
            if ($view === 'upcoming') {
                $q->where('due_at', '>', now());
            }
        }

        return response()->json(['success' => true, 'data' => $q->orderBy('due_at')->orderBy('id')->paginate($r->integer('per_page', 20))]);
    }

    public function saveActivity(Request $r, ?int $activity = null)
    {
        Access::authorize('CRM_LEAD', 'UPDATE');
        $model = $activity ? $this->activities()->findOrFail($activity) : new CrmActivity;
        abort_if($model->completed_at, 422, 'Completed activities cannot be edited.');
        $data = $r->validate(['lead_id' => 'required|integer', 'type' => ['required', Rule::in(CrmActivity::TYPES)], 'subject' => 'required|string|max:200', 'description' => 'nullable|string|max:10000', 'due_at' => 'nullable|required_unless:type,Note|date']);
        $lead = $this->leads()->findOrFail($data['lead_id']);
        $model->fill(array_merge($data, ['plant_id' => Access::plantId(), 'assigned_to' => $lead->assigned_to, 'completed_at' => $data['type'] === 'Note' ? now() : null]))->save();

        return response()->json(['success' => true, 'data' => $model], $activity ? 200 : 201);
    }

    public function complete(Request $r, int $activity)
    {
        Access::authorize('CRM_LEAD', 'UPDATE');
        $data = $r->validate(['description' => 'nullable|string|max:10000']);
        $model = $this->activities()->findOrFail($activity);
        if (! $model->completed_at) {
            $model->update(array_merge($data, ['completed_at' => now()]));
        }

        return response()->json(['success' => true, 'data' => $model]);
    }

    public function deleteActivity(int $activity)
    {
        Access::authorize('CRM_LEAD', 'DELETE');
        $this->activities()->findOrFail($activity)->delete();

        return response()->json(['success' => true, 'message' => 'Activity deleted.']);
    }
}
