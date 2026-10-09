<?php
/*
Author: ragul-onemodo
Created: 2026-10-09 12:29:20 Asia/Calcutta (UTC+05:30)
*/
namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\{CrmLead, CrmActivity, CrmDeal, CrmAttachment, Patron, Plant, User, MixDesign};
use App\Services\CrmLeadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CrmLeadController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'CRM_LEAD';

    private function plantId(): int
    {
        $id = (int) session('active_plant_id');
        abort_unless($id && Plant::whereKey($id)->where('is_active', 1)->exists(), 403, 'Select an active plant.');
        return $id;
    }

    private function scoped(CrmLead $lead): CrmLead
    {
        abort_unless((int) $lead->plant_id === $this->plantId() && !$lead->trashed(), 404);
        return $lead;
    }

    private function owners()
    {
        $plantId = $this->plantId();
        $entityId = Plant::whereKey($plantId)->value('entity_id');
        return User::where('is_active', 1)->where(function ($q) use ($entityId, $plantId) {
            $q->whereKey(auth()->id())->orWhereHas('entityUsers', fn ($member) => $member->where('entity_id', $entityId)->where(fn ($p) => $p->whereNull('plant_id')->orWhere('plant_id', $plantId)));
        });
    }

    public function index()
    {
        $this->authorizeModule('menu');
        $plantId = $this->plantId();
        $leads = CrmLead::where('plant_id', $plantId)->with(['owner:id,username', 'deal'])->withMin(['activities as next_follow_up' => fn ($q) => $q->whereNull('completed_at')->whereNotNull('due_at')], 'due_at')->orderByDesc('id')->get();
        $statuses = $leads->groupBy('status')->map->count();
        $owners = $this->owners()->orderBy('username')->get(['id as value', 'username as label']);
        $customers = Patron::where('plant_id', $plantId)->ofType('Customer')->where('status', 1)->orderBy('legal_name')->get(['id', 'legal_name'])->map(fn ($p) => ['value' => $p->id, 'label' => $p->legal_name]);
        return Inertia::render('CrmLeads/Index', [
            'leads' => $leads, 'owners' => $owners, 'customers' => $customers,
            'options' => ['statuses' => CrmLead::STATUSES, 'sources' => CrmLead::SOURCES, 'priorities' => CrmLead::PRIORITIES, 'activity_types' => CrmActivity::TYPES, 'deal_stages' => CrmDeal::STAGES, 'requirements' => $this->mixDesignOptions($plantId)],
            'metrics' => [
                'total' => $leads->count(), 'new' => $statuses->get('New', 0), 'qualified' => $statuses->get('Qualified', 0), 'converted' => $statuses->get('Converted', 0),
                'conversion_rate' => $leads->count() ? round($statuses->get('Converted', 0) / $leads->count() * 100, 1) : 0,
                'overdue' => CrmActivity::where('plant_id', $plantId)->whereHas('lead', fn ($q) => $q->where('status', '!=', 'Unqualified')->whereDoesntHave('deal', fn ($deal) => $deal->whereIn('stage', ['Won', 'Lost'])))->whereNull('completed_at')->where('due_at', '<', now())->count(),
                'pipeline_value' => CrmDeal::where('plant_id', $plantId)->whereNotIn('stage', ['Won', 'Lost'])->sum('expected_value'),
                'by_source' => $leads->groupBy('source')->map->count(),
                'by_owner' => $leads->groupBy(fn ($lead) => $lead->owner?->username ?? 'Unassigned')->map(fn ($group) => ['total' => $group->count(), 'converted' => $group->where('status', 'Converted')->count()]),
            ],
        ]);
    }

    private function mixDesignOptions(int $plantId): \Illuminate\Support\Collection
    {
        return MixDesign::where('plant_id', $plantId)->where('is_active', 1)
            ->whereNotNull('design_name')->where('design_name', '!=', '')
            ->orderBy('design_name')->distinct()->pluck('design_name')
            ->map(fn ($name) => ['label' => $name, 'value' => $name])->values();
    }

    private function leadData(Request $request, ?CrmLead $lead = null): array
    {
        if ($request->filled('phone')) {
            $phone = preg_replace('/[^0-9]/', '', $request->input('phone'));
            if (strlen($phone) === 12 && str_starts_with($phone, '91')) $phone = substr($phone, 2);
            $request->merge(['phone' => $phone]);
        }
        if ($request->filled('email')) $request->merge(['email' => strtolower(trim($request->input('email')))]);
        $data = $request->validate([
            'contact_name' => 'required|string|max:200', 'company_name' => 'nullable|string|max:200',
            'phone' => 'nullable|required_without:email|regex:/^[0-9]{7,15}$/', 'email' => 'nullable|required_without:phone|email|max:150',
            'enquiry_date' => 'required|date_format:Y-m-d', 'address' => 'nullable|string|max:400',
            'source' => ['required', Rule::in(CrmLead::SOURCES)], 'priority' => ['required', Rule::in(CrmLead::PRIORITIES)],
            'status' => ['required', Rule::in($lead?->converted_at ? ['Converted'] : ['New', 'Contacted', 'Qualified', 'Unqualified'])],
            'assigned_to' => ['nullable', 'integer', Rule::in($this->owners()->pluck('id')->all())],
            'project_name' => 'nullable|string|max:200', 'requirement' => 'nullable|string|max:500', 'estimated_quantity' => 'nullable|numeric|min:0|max:99999999999',
            'delivery_location' => 'nullable|string|max:500', 'expected_value' => 'required|numeric|min:0|max:99999999999999',
            'expected_purchase_date' => 'nullable|date_format:Y-m-d', 'remarks' => 'nullable|string|max:10000',
            'lost_reason' => 'nullable|required_if:status,Unqualified|string|max:2000',
        ]);
        $previousOwner = $lead?->assigned_to ?? auth()->id();
        $data['assigned_to'] = $data['assigned_to'] ?? null;
        if ((int) $data['assigned_to'] !== (int) $previousOwner) $this->authorizeModule('assign');
        if ($data['status'] !== 'Unqualified') $data['lost_reason'] = null;
        return $data;
    }

    public function store(Request $request, CrmLeadService $service)
    {
        $this->authorizeModule('create');
        $lead = $service->create($this->plantId(), $this->leadData($request));
        return back()->with('success', $lead->lead_number . ' created.');
    }

    public function update(Request $request, CrmLead $lead, CrmLeadService $service)
    {
        $this->authorizeModule('edit');
        $service->update($this->scoped($lead), $this->leadData($request, $lead));
        return back()->with('success', 'Lead updated.');
    }

    public function show(CrmLead $lead)
    {
        $this->authorizeModule('show');
        $this->scoped($lead)->load(['owner:id,username', 'customer', 'deal', 'activities' => fn ($q) => $q->with(['owner:id,username', 'creator:id,username'])->orderByDesc('id'), 'attachments']);
        $plantId = $this->plantId();
        return response()->json([
            'lead' => $lead,
            'quotations' => $lead->customer_id ? DB::table('mm_quotations')->where('plant_id', $plantId)->where('patron_id', $lead->customer_id)->whereNull('deleted_at')->orderByDesc('id')->get(['id as value', 'reference as label']) : [],
            'sales_orders' => $lead->customer_id ? DB::table('mm_sales_orders')->where('plant_id', $plantId)->where('customer_id', $lead->customer_id)->whereNull('deleted_at')->orderByDesc('id')->get(['id as value', 'order_no as label']) : [],
        ]);
    }

    public function assign(Request $request, CrmLeadService $service)
    {
        $this->authorizeModule('assign');
        $plantId = $this->plantId();
        $data = $request->validate(['ids' => 'required|array|min:1|max:200', 'ids.*' => ['required', 'integer', 'distinct', Rule::exists('mm_crm_leads', 'id')->where('plant_id', $plantId)->whereNull('deleted_at')], 'assigned_to' => ['required', 'integer', Rule::in($this->owners()->pluck('id')->all())]]);
        DB::transaction(function () use ($data, $plantId, $service) {
            foreach (CrmLead::where('plant_id', $plantId)->whereIn('id', $data['ids'])->lockForUpdate()->get() as $lead) {
                $lead->update(['assigned_to' => $data['assigned_to']]);
                $service->log($lead, 'Lead assigned', 'Owner: ' . User::find($data['assigned_to'])->username);
            }
        });
        return back()->with('success', 'Selected leads assigned.');
    }

    public function convert(Request $request, CrmLead $lead, CrmLeadService $service)
    {
        $this->authorizeModule('convert');
        $this->scoped($lead);
        $data = $request->validate(['customer_id' => ['nullable', 'integer', Rule::in(Patron::where('plant_id', $this->plantId())->ofType('Customer')->where('status', 1)->pluck('id')->all())]]);
        $service->convert($lead, $data['customer_id'] ?? null);
        return back()->with('success', 'Lead converted into a customer and sales opportunity.');
    }

    public function activity(Request $request, CrmLead $lead)
    {
        $this->authorizeModule('edit');
        $this->scoped($lead);
        $data = $request->validate(['type' => ['required', Rule::in(CrmActivity::TYPES)], 'subject' => 'required|string|max:200', 'description' => 'nullable|string|max:10000', 'due_at' => 'nullable|required_unless:type,Note|date', 'assigned_to' => ['nullable', 'integer', Rule::in($this->owners()->pluck('id')->all())]]);
        $data['assigned_to'] = $data['assigned_to'] ?? $lead->assigned_to;
        if ((int) $data['assigned_to'] !== (int) $lead->assigned_to) $this->authorizeModule('assign');
        $lead->activities()->create(array_merge($data, ['plant_id' => $lead->plant_id, 'completed_at' => $data['type'] === 'Note' ? now() : null]));
        return back()->with('success', 'Activity saved.');
    }

    public function complete(CrmLead $lead, CrmActivity $activity)
    {
        $this->authorizeModule('edit');
        $this->scoped($lead);
        abort_unless((int) $activity->lead_id === (int) $lead->id && (int) $activity->plant_id === (int) $lead->plant_id && $activity->type !== 'System', 404);
        if (!$activity->completed_at) $activity->update(['completed_at' => now()]);
        return back()->with('success', 'Activity completed.');
    }

    public function deal(Request $request, CrmLead $lead, CrmLeadService $service)
    {
        $this->authorizeModule('edit');
        $this->scoped($lead);
        $deal = $lead->deal()->firstOrFail();
        $data = $request->validate([
            'stage' => ['required', Rule::in(CrmDeal::STAGES)], 'expected_value' => 'required|numeric|min:0|max:99999999999999',
            'expected_close_date' => 'nullable|date_format:Y-m-d', 'lost_reason' => 'nullable|required_if:stage,Lost|string|max:2000',
            'quotation_id' => ['nullable', 'integer', Rule::exists('mm_quotations', 'id')->where('plant_id', $lead->plant_id)->where('patron_id', $lead->customer_id)->whereNull('deleted_at')],
            'sales_order_id' => ['nullable', 'integer', Rule::exists('mm_sales_orders', 'id')->where('plant_id', $lead->plant_id)->where('customer_id', $lead->customer_id)->whereNull('deleted_at')],
        ]);
        if ($data['stage'] !== 'Lost') $data['lost_reason'] = null;
        DB::transaction(function () use ($deal, $data, $lead, $service) {
            $deal->update($data);
            $service->log($lead, 'Deal updated', 'Stage: ' . $deal->stage);
        });
        return back()->with('success', 'Sales opportunity updated.');
    }

    public function attach(Request $request, CrmLead $lead)
    {
        $this->authorizeModule('edit');
        $this->scoped($lead);
        $request->validate(['file' => 'required|file|mimes:pdf,jpg,jpeg,png,webp,xlsx,xls,docx,doc,txt,csv|max:10240']);
        $file = $request->file('file');
        $path = $file->store('crm/' . $lead->plant_id . '/' . $lead->id, 'local');
        abort_unless($path, 500, 'Unable to store the attachment.');
        try {
            $lead->attachments()->create(['plant_id' => $lead->plant_id, 'name' => $file->getClientOriginalName(), 'path' => $path, 'size' => $file->getSize()]);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
        return back()->with('success', 'Attachment uploaded.');
    }

    public function download(CrmLead $lead, CrmAttachment $attachment)
    {
        $this->authorizeModule('show');
        $this->scoped($lead);
        abort_unless((int) $attachment->lead_id === (int) $lead->id && (int) $attachment->plant_id === (int) $lead->plant_id, 404);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);
        return Storage::disk('local')->download($attachment->path, $attachment->name);
    }

    public function destroy(CrmLead $lead)
    {
        $this->authorizeModule('delete');
        $this->scoped($lead);
        DB::transaction(function () use ($lead) {
            $lead = CrmLead::whereKey($lead->id)->lockForUpdate()->firstOrFail();
            abort_if($lead->converted_at, 422, 'Converted leads retain customer and deal history and cannot be deleted.');
            $lead->delete();
        });
        return back()->with('success', 'Lead deleted.');
    }
}
