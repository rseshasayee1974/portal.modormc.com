<?php

/*
Author: ragul-onemodo
Created: 2026-10-09 18:02:55 Asia/Calcutta (UTC+05:30)
*/

namespace App\Http\Controllers\Api\SalesMobile;

use App\Http\Controllers\Controller;
use App\Models\CrmLead;
use App\Models\MixDesign;
use App\Models\Plant;
use App\Models\Quotation;
use App\Models\Site;
use App\Services\CrmLeadService;
use App\Services\Mobile\MobileAccess as Access;
use App\Services\Mobile\QuotationCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class QuotationController extends Controller
{
    private const APPROVAL_STATES = [
        Quotation::STATUS_DRAFT => 'draft', Quotation::STATUS_SENT => 'pending',
        Quotation::STATUS_ACCEPTED => 'approved', Quotation::STATUS_REJECTED => 'rejected',
    ];

    private function syncApprovalState(Quotation $quotation): Quotation
    {
        $quotation->mobile_approval_state = self::APPROVAL_STATES[(int) $quotation->status] ?? 'draft';

        return $quotation;
    }

    protected function quotations(): \Illuminate\Database\Eloquent\Builder
    {
        return Access::quotations();
    }

    protected function authorizeReview(): void
    {
        abort(403, 'Quotation approval is available through the admin app only.');
    }

    protected function detailRelations(): array
    {
        return [
            'patron' => fn ($q) => $q->whereIn('id', Access::customers()->select('id')),
            'site' => fn ($q) => $q->whereIn('id', Access::sites()->select('id')), 'items.uom',
            'items.tax' => fn ($q) => $q->where('plant_id', Access::plantId()),
            'items.tax.children' => fn ($q) => $q->where('plant_id', Access::plantId()),
            'items.mixDesign' => fn ($query) => $query->withTrashed()->where('plant_id', Access::plantId()),
            'items.mixDesign.concreteGrade:id,name', 'items.mixDesign.unit',
            'items.mixDesign.items' => fn ($query) => $query->where('plant_id', Access::plantId()),
            'items.mixDesign.items.product:id,title,code,material_code',
            'items.mixDesign.items.uom',
        ];
    }

    private function details(Quotation $quotation): Quotation
    {
        $this->syncApprovalState($quotation)->loadMissing($this->detailRelations());
        $quotation->patron?->setAppends([]);
        $quotation->site?->setAppends([]);
        foreach ($quotation->items as $item) {
            // Usage flags query production tables and are unrelated to quotation display.
            $item->setAppends([]);
            foreach ($item->mixDesign?->items ?? [] as $component) {
                $component->setAppends([]);
                $component->product?->setAppends([]);
            }
        }
        return $quotation;
    }

    public function index(Request $r)
    {
        $r->validate(['per_page' => 'nullable|integer|between:1,100', 'search' => 'nullable|string|max:100', 'approval_state' => 'nullable|in:draft,pending,approved,rejected']);
        $q = $this->quotations()->with($this->detailRelations());
        if ($r->filled('search')) {
            $q->where('reference', 'like', '%'.$r->search.'%');
        }
        if ($r->filled('approval_state')) {
            $q->where('status', array_search($r->approval_state, self::APPROVAL_STATES, true));
        }
        $page = $q->latest('id')->paginate($r->integer('per_page', 20));
        $page->getCollection()->each(fn ($quotation) => $this->details($quotation));

        return response()->json(['success' => true, 'data' => $page]);
    }

    public function show(int $quotation)
    {
        $model = $this->details($this->quotations()->findOrFail($quotation));

        return response()->json(['success' => true, 'data' => $this->details($model)]);
    }

    private function data(Request $r, ?Quotation $quotation, bool $fromLead = false): array
    {
        $exists = fn ($table) => Rule::exists($table, 'id')->where('plant_id', Access::plantId())->whereNull('deleted_at');
        if (is_array($r->input('items'))) {
            $r->merge(['items' => array_map(fn ($item) => is_array($item)
                ? QuotationCalculator::normalizeDiscount($item) : $item, $r->input('items'))]);
        }
        $data = $r->validate([
            'reference' => ['nullable', 'string', 'max:100', Rule::unique('mm_quotations', 'reference')->where('plant_id', Access::plantId())->whereNull('deleted_at')->ignore($quotation?->id)],
            'patron_id' => ['required', 'integer', Access::exists(Access::customers())], 'site_id' => ['required', 'integer', Access::exists(Access::plantSites())],
            'quote_date' => 'required|date_format:Y-m-d', 'validity_date' => 'nullable|date_format:Y-m-d|after_or_equal:quote_date',
            'notes' => 'nullable|string|max:10000', 'is_tax_inclusive' => 'required|boolean',
            'items' => 'required|array|min:1|max:100', 'items.*' => 'required|array:mix_design_id,quantity,rate,tax_id,uom_id,concrete_pump,pump_rate,discount_type,discount', 'items.*.mix_design_id' => ['required', 'integer', $exists('mm_mix_designs')],
            'items.*.quantity' => 'required|numeric|between:0.01,999999', 'items.*.rate' => 'required|numeric|between:0,99999999',
            'items.*.discount_type' => 'nullable|in:amount,%', 'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.tax_id' => ['nullable', 'integer', $exists('mm_taxes')->where('tax_type', 'Sales')->where('status', 1)->whereNull('parent_id')],
            'items.*.uom_id' => ['nullable', 'integer', Rule::exists('mm_product_units', 'id')->whereNull('deleted_at')],
            'items.*.concrete_pump' => 'nullable|string|max:100', 'items.*.pump_rate' => 'nullable|numeric|min:0',
        ], [
            'patron_id.exists' => 'The selected customer is unavailable in the selected plant.',
            'site_id.exists' => 'The selected site is unavailable in the selected plant.',
        ]);
        $isLeadQuotation = $fromLead || ($quotation && Access::leadQuotationIds()->where('quotation_id', $quotation->id)->exists());
        $data['sales_executive_id'] = $isLeadQuotation
            ? ($quotation?->sales_executive_id ?? Access::personnelId())
            : null;
        $data['items'] = QuotationCalculator::items($data['items'], (bool) $data['is_tax_inclusive']);
        $baseTotal = array_sum(array_column($data['items'], 'amount_total'));
        $target = $data['is_tax_inclusive'] ? array_sum(array_map(fn ($i) => round($i['quantity'] * $i['rate'] + ($i['pump_rate'] ?? 0) - ($i['discount_amount'] ?? 0), 2), $data['items'])) : $baseTotal;
        $data['adjustment'] = round(round($target) - $baseTotal, 2);
        $data['status'] = Quotation::STATUS_DRAFT;
        return $data;
    }
    public function store(Request $r)
    {
        $data = $this->data($r, null);
        $model = DB::transaction(function () use ($data) {
            Plant::whereKey(Access::plantId())->lockForUpdate()->firstOrFail();
            $quotation = Quotation::createWithItems($data, Access::plantId());
            $this->syncApprovalState($quotation)->save();
            return $quotation;
        });
        return response()->json(['success' => true, 'data' => $this->details($model->fresh())], 201);
    }
    public function createFromLead(Request $r, int $lead, CrmLeadService $service)
    {
        Access::authorize('CRM_LEAD', 'CONVERT');
        return DB::transaction(function () use ($r, $lead, $service) {
            Plant::whereKey(Access::plantId())->lockForUpdate()->firstOrFail();
            $source = Access::leads()->lockForUpdate()->findOrFail($lead);
            $autoConverted = false;
            if (! $source->converted_at || ! $source->customer_id) {
                if ($source->converted_at || ! in_array($source->status, CrmLead::QUOTATION_CONVERTIBLE_STATUSES, true)) {
                    throw ValidationException::withMessages(['lead_id' => 'Only a new, contacted, qualified, or approved lead can be converted automatically.']);
                }
                $customerId = $r->input('patron_id', $source->customer_id);
                if ($customerId !== null) {
                    $r->merge(['patron_id' => $customerId]);
                    $r->validate(['patron_id' => ['required', 'integer', Access::exists(Access::customers()->where('status', 1))]]);
                }
                $source = $service->convert($source, $customerId === null ? null : (int) $customerId, forQuotation: true);
                $autoConverted = true;
            }
            $deal = $source->deal()->lockForUpdate()->first();
            if (!$deal || $deal->quotation_id) {
                throw ValidationException::withMessages(['lead_id' => $deal ? 'This lead already has a linked quotation.' : 'This lead has no converted sales opportunity.']);
            }
            $customer = Access::customers()->where('status', 1)->find($source->customer_id);
            if (!$customer) {
                throw ValidationException::withMessages(['lead_id' => 'The lead customer is not active or accessible in this plant.']);
            }
            $mix = MixDesign::where('plant_id', Access::plantId())->where('is_active', 1)->find($source->mix_design_id);
            if (!$mix) {
                throw ValidationException::withMessages(['mix_design_id' => 'Select an active mix design on the lead before creating a quotation.']);
            }
            $input = $r->validate([
                'site_id' => ['nullable', 'integer', Access::exists(Access::plantSites())],
                'quantity' => 'sometimes|required|numeric|between:0.01,999999',
                'rate' => 'sometimes|required|numeric|between:0,99999999',
                'reference' => 'nullable|string|max:100', 'quote_date' => 'sometimes|required|date_format:Y-m-d',
                'validity_date' => 'nullable|date_format:Y-m-d', 'notes' => 'nullable|string|max:10000',
                'is_tax_inclusive' => 'sometimes|required|boolean',
                'tax_id' => 'nullable|integer', 'uom_id' => 'nullable|integer',
                'concrete_pump' => 'nullable|string|max:100', 'pump_rate' => 'nullable|numeric|min:0',
                'discount_type' => 'nullable|in:amount,%', 'discount' => 'nullable|numeric|min:0',
                'items' => 'prohibited',
                'patron_id' => ['nullable', 'integer', Access::exists(Access::customers())],
                'sales_executive_id' => 'prohibited',
                'mix_design_id' => 'prohibited',
            ]);
            if (isset($input['patron_id']) && (int) $input['patron_id'] !== (int) $customer->id) {
                throw ValidationException::withMessages(['patron_id' => 'The selected customer must match the lead customer.']);
            }
            $sites = Access::sites()->where('patron_id', $customer->id)->limit(2)->pluck('id');
            $name = mb_substr(trim($source->project_name ?: ($source->delivery_location ?: $customer->legal_name)), 0, 255);
            $siteId = $input['site_id'] ?? null;
            if (!$siteId) {
                $siteId = Access::plantSites()->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($name)])
                    ->orderBy('id')->lockForUpdate()->value('id');
            }
            $siteId ??= $sites->count() === 1 ? $sites->first() : null;
            if (!$siteId && ($autoConverted || $sites->isEmpty())) {
                $location = $source->delivery_location ?: $source->address;
                $siteId = Site::create([
                    'plant_id' => Access::plantId(), 'patron_id' => [$customer->id],
                    'name' => $name,
                    'site_address_1' => $location ? mb_substr($location, 0, 500) : null,
                    'site_address_2' => $location ? (mb_substr($location, 500, 500) ?: null) : null,
                    'type' => 'unloading', 'status' => 'Active', 'is_restricted' => false,
                ])->id;
            }
            $line = [
                'mix_design_id' => $mix->id,
                'quantity' => $input['quantity'] ?? $source->estimated_quantity,
                'rate' => $input['rate'] ?? $mix->rate_per_qty,
                'uom_id' => $input['uom_id'] ?? $mix->unit_id,
                'tax_id' => $input['tax_id'] ?? null,
                'concrete_pump' => $input['concrete_pump'] ?? null,
                'pump_rate' => $input['pump_rate'] ?? 0,
                'discount_type' => $input['discount_type'] ?? null,
                'discount' => $input['discount'] ?? 0,
            ];
            $notes = collect([
                'Lead: '.$source->lead_number,
                $source->project_name ? 'Project: '.$source->project_name : null,
                $source->delivery_location ? 'Delivery: '.$source->delivery_location : null,
                $source->remarks,
            ])->filter()->implode("\n");
            $r->replace([
                'patron_id' => $customer->id, 'site_id' => $siteId,
                'quote_date' => $input['quote_date'] ?? now()->toDateString(),
                'validity_date' => $input['validity_date'] ?? null,
                'reference' => $input['reference'] ?? null,
                'notes' => $input['notes'] ?? mb_substr($notes, 0, 10000),
                'is_tax_inclusive' => $input['is_tax_inclusive'] ?? false,
                'items' => [$line],
            ]);
            $data = $this->data($r, null, fromLead: true);
            $data['created_by'] = $r->user()->id;
            $quotation = Quotation::createWithItems($data, Access::plantId());
            $this->syncApprovalState($quotation)->save();
            $deal->update(['quotation_id' => $quotation->id]);
            $source->update(['status' => CrmLead::STATUS_CONVERTED]);
            $service->log($source, 'Quotation created from lead', 'Quotation: '.$quotation->reference);

            return response()->json(['success' => true, 'data' => $this->details($quotation->fresh())], 201);
        });
    }

    public function update(Request $r, int $quotation)
    {
        return DB::transaction(function () use ($r, $quotation) {
            $model = $this->quotations()->lockForUpdate()->findOrFail($quotation);
            $this->editable($model);
            $model->updateWithItems($this->data($r, $model));
            $model->forceFill(['mobile_approval_state' => 'draft', 'mobile_approval_comment' => null, 'mobile_reviewed_by' => null, 'mobile_reviewed_at' => null, 'mobile_submitted_by' => null, 'mobile_submitted_at' => null])->save();

            return response()->json(['success' => true, 'data' => $this->details($model->fresh())]);
        });
    }

    private function editable(Quotation $model): void
    {
        $this->syncApprovalState($model);
        abort_if($model->is_customer_po || $model->customerPOs()->exists(), 422, 'Quotation already converted into a customer PO.');
        abort_if(in_array($model->mobile_approval_state, ['pending', 'approved']) || (int) $model->status === Quotation::STATUS_ACCEPTED, 422, 'Submitted or approved quotations cannot be edited or deleted.');
    }

    public function destroy(int $quotation)
    {
        return DB::transaction(function () use ($quotation) {
            $model = $this->quotations()->lockForUpdate()->findOrFail($quotation);
            $this->editable($model);
            $model->delete();

            return response()->json(['success' => true, 'message' => 'Quotation deleted.']);
        });
    }

    public function submit(int $quotation)
    {
        return DB::transaction(function () use ($quotation) {
            $model = $this->quotations()->lockForUpdate()->findOrFail($quotation);
            $this->editable($model);
            abort_unless($model->items()->exists(), 422, 'Add quotation items before submission.');
            $model->forceFill(['status' => Quotation::STATUS_SENT, 'mobile_approval_state' => 'pending', 'mobile_submitted_by' => request()->user()->id, 'mobile_submitted_at' => now(), 'mobile_approval_comment' => null, 'mobile_reviewed_by' => null, 'mobile_reviewed_at' => null])->save();

            return response()->json(['success' => true, 'data' => $this->details($model)]);
        });
    }

    public function review(Request $r, int $quotation)
    {
        $this->authorizeReview();
        $data = $r->validate(['decision' => 'required|in:approved,rejected', 'comment' => 'nullable|required_if:decision,rejected|string|max:2000']);

        return DB::transaction(function () use ($r, $quotation, $data) {
            $query = $this->quotations();
            $model = $query->lockForUpdate()->find($quotation);
            abort_unless($model, 404, 'Quotation not found or you do not have access to it.');
            $this->syncApprovalState($model);
            abort_unless($model->mobile_approval_state === 'pending', 422, 'Only submitted quotations can be reviewed.');
            abort_if((int) $model->mobile_submitted_by === (int) $r->user()->id, 403, 'You cannot review your own quotation submission.');
            $model->forceFill(['mobile_approval_state' => $data['decision'], 'mobile_approval_comment' => $data['comment'] ?? null, 'mobile_reviewed_by' => $r->user()->id, 'mobile_reviewed_at' => now(), 'status' => $data['decision'] === 'approved' ? Quotation::STATUS_ACCEPTED : Quotation::STATUS_REJECTED])->save();

            return response()->json(['success' => true, 'data' => $this->details($model)->setAppends([])]);
        });
    }
}
