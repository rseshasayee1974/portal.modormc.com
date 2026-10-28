<?php

/*
Author: ragul-onemodo
Created: 2026-10-09 18:01:41 Asia/Calcutta (UTC+05:30)
*/

namespace App\Http\Controllers\Api\SalesMobile;

use App\Http\Controllers\Controller;
use App\Models\CrmActivity;
use App\Models\CrmLead;
use App\Models\Personnel;
use App\Models\Plant;
use App\Models\User;
use App\Services\CrmLeadService;
use App\Services\Mobile\MobileAccess as Access;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LeadController extends Controller
{
    protected function leads(): Builder
    {
        return Access::leads();
    }

    public function index(Request $r)
    {
        Access::authorize('CRM_LEAD', 'VIEW');
        $r->validate(['search' => 'nullable|string|max:100', 'status' => ['nullable', Rule::in(CrmLead::STATUSES)], 'per_page' => 'nullable|integer|between:1,100', 'page' => 'nullable|integer|min:1']);
        $q = $this->leads()->with(['owner:id,username', 'mixDesign' => fn ($q) => $q->select('id', 'design_name')->where('plant_id', Access::plantId())])->withMin(['activities as next_follow_up' => fn ($a) => $a->whereIn('id', $this->activities()->select('id'))->whereNull('completed_at')], 'due_at');
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
        $model = $this->leads()->findOrFail($lead)->load(['mixDesign' => fn ($q) => $q->select('id', 'design_name')->where('plant_id', Access::plantId()), 'activities' => fn ($q) => $q->whereIn('id', $this->activities()->select('id'))->latest('id'), 'deal' => fn ($q) => $q->where('plant_id', Access::plantId()), 'customer' => fn ($q) => $q->whereIn('id', Access::customers()->select('id')), 'owner:id,username']);
        $model->customer?->setAppends([]);
        $model->setAttribute('allowed_statuses', $this->allowedStatuses($model));
        $model->setAttribute('status_options', $this->statusOptions($model));

        return response()->json(['success' => true, 'data' => $model]);
    }

    public function allowedStatuses(?CrmLead $lead = null): array
    {
        return $lead ? $lead->allowedStatuses() : CrmLead::STANDARD_STATUSES;
    }

    public function statusOptions(?CrmLead $lead = null): array
    {
        return array_map(fn ($status) => [
            'label' => $status,
            'value' => $status,
        ], $this->allowedStatuses($lead));
    }

    protected function leadOwnerId(?CrmLead $lead): ?int
    {
        return (int) request()->user()->id;
    }

    protected function loadLeadDetails(CrmLead $lead): CrmLead
    {
        $lead->load([
            'mixDesign' => fn ($q) => $q->select('id', 'design_name')->where('plant_id', Access::plantId()),
            'customer' => fn ($q) => $q->whereIn('id', Access::customers()->select('id')),
            'owner:id,username',
        ]);
        $lead->customer?->setAppends([]);
        $lead->setAttribute('allowed_statuses', $this->allowedStatuses($lead));
        $lead->setAttribute('status_options', $this->statusOptions($lead));

        return $lead;
    }

    protected function data(Request $r, ?CrmLead $lead = null): array
    {
        // 1. Alias mapping
        if (! $r->has('customer_id') && $r->has('patron_id')) {
            $r->merge(['customer_id' => $r->input('patron_id')]);
        }
        if (! $r->has('contact_name') && $r->has('customer_name')) {
            $r->merge(['contact_name' => $r->input('customer_name')]);
        }
        if (! $r->has('remarks') && $r->has('notes')) {
            $r->merge(['remarks' => $r->input('notes')]);
        }
        if (! $r->has('delivery_location') && $r->has('location')) {
            $r->merge(['delivery_location' => $r->input('location')]);
        }
        if (! $r->has('estimated_quantity') && $r->has('quantity')) {
            $r->merge(['estimated_quantity' => $r->input('quantity')]);
        }
        if (! $r->has('assigned_to') && $r->has('sales_executive_id')) {
            $personnelId = $r->input('sales_executive_id');
            if ($personnelId) {
                $userId = Personnel::where('id', $personnelId)->value('user_id');
                $r->merge(['assigned_to' => $userId ?: $personnelId]);
            } else {
                $r->merge(['assigned_to' => null]);
            }
        }

        // 2. Normalization: empty string to null for nullable fields
        $nullableFields = [
            'mix_design_id', 'customer_id', 'assigned_to', 'estimated_quantity',
            'expected_purchase_date', 'lost_reason', 'requirement', 'company_name',
            'address', 'project_name', 'delivery_location', 'remarks', 'phone', 'email',
        ];
        foreach ($nullableFields as $field) {
            if ($r->has($field) && $r->input($field) === '') {
                $r->merge([$field => null]);
            }
        }

        // 3. Normalization: dates (extract Y-m-d if ISO string provided)
        foreach (['enquiry_date', 'expected_purchase_date'] as $dateField) {
            if ($r->has($dateField) && is_string($r->input($dateField)) && strlen($r->input($dateField)) >= 10) {
                $val = substr($r->input($dateField), 0, 10);
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
                    $r->merge([$dateField => $val]);
                }
            }
        }

        // 4. Normalization: expected_value defaults to 0 if empty/null
        if ($r->has('expected_value') && ($r->input('expected_value') === '' || $r->input('expected_value') === null)) {
            $r->merge(['expected_value' => 0]);
        }

        // 5. Normalization: phone and email
        foreach (['email', 'phone'] as $field) {
            if (array_key_exists($field, $r->all())) {
                if ($r->filled($field) && is_string($r->input($field))) {
                    if ($field === 'email') {
                        $r->merge([$field => strtolower(trim($r->input($field)))]);
                    } else {
                        $phone = preg_replace('/[^0-9]/', '', $r->input($field));
                        if (strlen($phone) === 12 && str_starts_with($phone, '91')) {
                            $phone = substr($phone, 2);
                        }
                        $r->merge([$field => $phone]);
                    }
                } else {
                    $r->merge([$field => $r->input($field)]);
                }
            }
        }

        // 6. Normalization: source, priority, status casing
        if ($r->has('source') && is_string($r->input('source'))) {
            $sourceLower = strtolower(trim($r->input('source')));
            foreach (CrmLead::SOURCES as $source) {
                if (strtolower($source) === $sourceLower) {
                    $r->merge(['source' => $source]);
                    break;
                }
            }
        }
        if ($r->has('priority') && is_string($r->input('priority'))) {
            $priorityLower = strtolower(trim($r->input('priority')));
            foreach (CrmLead::PRIORITIES as $priority) {
                if (strtolower($priority) === $priorityLower) {
                    $r->merge(['priority' => $priority]);
                    break;
                }
            }
        }
        if ($r->has('status') && is_string($r->input('status'))) {
            $statusLower = strtolower(trim($r->input('status')));
            foreach (CrmLead::STATUSES as $status) {
                if (strtolower($status) === $statusLower) {
                    $r->merge(['status' => $status]);
                    break;
                }
            }
        }

        $rules = [
            'contact_name' => 'required|string|max:200',
            'company_name' => 'nullable|string|max:200',
            'phone' => 'nullable|required_without:email|regex:/^[0-9]{7,15}$/',
            'email' => 'nullable|required_without:phone|email|max:150',
            'enquiry_date' => 'required|date_format:Y-m-d',
            'source' => ['required', Rule::in(CrmLead::SOURCES)],
            'priority' => ['required', Rule::in(CrmLead::PRIORITIES)],
            'status' => ['required', Rule::in($this->allowedStatuses($lead))],
            'assigned_to' => 'nullable|integer',
            'address' => 'nullable|string|max:400',
            'project_name' => 'nullable|string|max:200',
            'requirement' => 'nullable|string|max:500',
            'mix_design_id' => ['nullable', 'integer', Rule::exists('mm_mix_designs', 'id')->where('plant_id', Access::plantId())->whereNull('deleted_at')->where(fn ($q) => $q->where('is_active', 1)->when($lead?->mix_design_id, fn ($q) => $q->orWhere('id', $lead->mix_design_id)))],
            'estimated_quantity' => 'nullable|numeric|min:0|max:99999999999',
            'delivery_location' => 'nullable|string|max:500',
            'expected_value' => 'required|numeric|min:0|max:99999999999999',
            'expected_purchase_date' => 'nullable|date_format:Y-m-d',
            'remarks' => 'nullable|string|max:10000',
            'lost_reason' => 'nullable|required_if:status,'.CrmLead::STATUS_UNQUALIFIED.'|string|max:2000',
        ];

        if ($lead) {
            $rules['phone'] = ['nullable', 'regex:/^[0-9]{7,15}$/'];
            $rules['email'] = ['nullable', 'email', 'max:150'];
            $rules['lost_reason'] = ['nullable', 'string', 'max:2000'];
            $rules['assigned_to'] = ['nullable', 'integer'];
            foreach ($rules as $field => $rule) {
                $rules[$field] = array_merge(['sometimes'], is_array($rule) ? $rule : explode('|', $rule));
            }
        }

        $data = $r->validate($rules, [
            'mix_design_id.exists' => 'The selected mix design is unavailable in this plant. Refresh the mix designs for the selected plant and choose an active design.',
        ]);

        $resolvedOwnerId = $this->leadOwnerId($lead);
        if ($resolvedOwnerId !== null) {
            abort_if(isset($data['assigned_to']) && (int) $data['assigned_to'] !== $resolvedOwnerId, 403, 'Mobile leads must belong to the logged-in user.');
        }

        if (! $lead || array_key_exists('assigned_to', $data)) {
            $owner = array_key_exists('assigned_to', $data) ? $data['assigned_to'] : ($lead ? $lead->assigned_to : $r->user()->id);
            if ($owner !== null && (int) $owner !== (int) ($lead?->assigned_to ?? $r->user()->id)) {
                Access::authorize('CRM_LEAD', 'ASSIGN');
            } elseif ($owner === null && $lead?->assigned_to !== null) {
                Access::authorize('CRM_LEAD', 'ASSIGN');
            }
            if ($owner !== null) {
                $user = User::where('is_active', 1)->find($owner);
                abort_unless($user && Access::plants($user)->whereKey(Access::plantId())->exists(), 422, 'Owner must belong to this plant.');
            }
            $data['assigned_to'] = $owner;
        }

        if (! $lead) {
            $data['lost_reason'] = ($data['status'] ?? null) === CrmLead::STATUS_UNQUALIFIED ? ($data['lost_reason'] ?? null) : null;
        } else {
            if (isset($data['status']) && $data['status'] !== CrmLead::STATUS_UNQUALIFIED) {
                $data['lost_reason'] = null;
            }
        }

        return $data;
    }

    public function store(Request $r, CrmLeadService $service)
    {
        Access::authorize('CRM_LEAD', 'CREATE');
        $data = $this->data($r);
        $lead = $service->create(Access::plantId(), $data);

        return response()->json(['success' => true, 'data' => $this->loadLeadDetails($lead)], 201);
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

            return response()->json(['success' => true, 'data' => $this->loadLeadDetails($model)]);
        });
    }

    public function update(Request $r, int $lead, CrmLeadService $service)
    {
        Access::authorize('CRM_LEAD', 'UPDATE');
        $model = $this->leads()->findOrFail($lead);
        $updated = $service->update($model, $this->data($r, $model));

        return response()->json(['success' => true, 'data' => $this->loadLeadDetails($updated)]);
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
            ? $this->statusOptions($this->leads()->findOrFail($lead))
            : $this->statusOptions();

        return response()->json([
            'success' => true,
            'data' => $options,
            'statuses' => array_column($options, 'value'),
        ]);
    }

    protected function activities()
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