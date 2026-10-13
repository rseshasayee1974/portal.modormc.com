<?php
/*
Author: ragul-onemodo
Created: 2026-10-09 18:02:55 Asia/Calcutta (UTC+05:30)
*/

namespace App\Http\Controllers\Api\SalesMobile;

use App\Http\Controllers\Controller;
use App\Models\{Quotation, Plant};
use App\Services\Mobile\{MobileAccess as Access, QuotationCalculator};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class QuotationController extends Controller
{
    public function index(Request $r)
    {
        Access::authorize('QUOTATION', 'VIEW');
        $r->validate(['per_page' => 'nullable|integer|between:1,100', 'search' => 'nullable|string|max:100', 'approval_state' => 'nullable|in:draft,pending,approved,rejected']);
        $q = Access::quotations()->with(['patron:id,legal_name', 'site:id,name']);
        if ($r->filled('search')) $q->where('reference', 'like', '%'.$r->search.'%');
        if ($r->filled('approval_state')) $q->where('mobile_approval_state', $r->approval_state);
        return response()->json(['success' => true, 'data' => $q->latest('id')->paginate($r->integer('per_page', 20))]);
    }
    public function show(int $quotation)
    {
        Access::authorize('QUOTATION', 'VIEW');
        return response()->json(['success' => true, 'data' => Access::quotations()->findOrFail($quotation)->load(['items.mixDesign', 'items.tax.children', 'patron', 'site'])]);
    }
    private function data(Request $r, ?Quotation $quotation): array
    {
        $exists = fn ($table) => Rule::exists($table, 'id')->where('plant_id', Access::plantId())->whereNull('deleted_at');
        $data = $r->validate([
            'reference' => ['nullable', 'string', 'max:100', Rule::unique('mm_quotations', 'reference')->where('plant_id', Access::plantId())->whereNull('deleted_at')->ignore($quotation?->id)],
            'patron_id' => ['required', 'integer', $exists('mm_patrons')], 'site_id' => ['required', 'integer', $exists('mm_sites')],
            'quote_date' => 'required|date_format:Y-m-d', 'validity_date' => 'nullable|date_format:Y-m-d|after_or_equal:quote_date',
            'notes' => 'nullable|string|max:10000', 'is_tax_inclusive' => 'required|boolean',
            'items' => 'required|array|min:1|max:100', 'items.*' => 'required|array:mix_design_id,quantity,rate,tax_id,uom_id,concrete_pump,pump_rate,discount_type,discount', 'items.*.mix_design_id' => ['required', 'integer', $exists('mm_mix_designs')],
            'items.*.quantity' => 'required|numeric|between:0.01,999999', 'items.*.rate' => 'required|numeric|between:0,99999999',
            'items.*.discount_type' => 'nullable|in:amount,%', 'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.tax_id' => ['nullable', 'integer', $exists('mm_taxes')->where('tax_type', 'Sales')->where('status', 1)->whereNull('parent_id')],
            'items.*.uom_id' => ['nullable', 'integer', $exists('mm_product_units')],
            'items.*.concrete_pump' => 'nullable|string|max:100', 'items.*.pump_rate' => 'nullable|numeric|min:0',
        ]);
        \App\Models\Patron::ofType('Customer')->findOrFail($data['patron_id']);
        $data['sales_executive_id'] = $quotation?->sales_executive_id ?? Access::personnelId();
        $data['items'] = QuotationCalculator::items($data['items'], (bool) $data['is_tax_inclusive']);
        $baseTotal = array_sum(array_column($data['items'], 'amount_total'));
        $target = $data['is_tax_inclusive'] ? array_sum(array_map(fn ($i) => round($i['quantity'] * $i['rate'] + ($i['pump_rate'] ?? 0) - ($i['discount_amount'] ?? 0), 2), $data['items'])) : $baseTotal;
        $data['adjustment'] = round(round($target) - $baseTotal, 2);
        $data['status'] = Quotation::STATUS_DRAFT;
        return $data;
    }
    public function store(Request $r)
    {
        Access::authorize('QUOTATION', 'CREATE');
        $data = $this->data($r, null);
        $model = DB::transaction(function () use ($data) {
            Plant::whereKey(Access::plantId())->lockForUpdate()->firstOrFail();
            return Quotation::createWithItems($data, Access::plantId());
        });
        return response()->json(['success' => true, 'data' => $model->fresh()->load('items')], 201);
    }
    public function update(Request $r, int $quotation)
    {
        Access::authorize('QUOTATION', 'UPDATE');
        return DB::transaction(function () use ($r, $quotation) {
            $model = Access::quotations()->lockForUpdate()->findOrFail($quotation);
            $this->editable($model);
            $model->updateWithItems($this->data($r, $model));
            $model->forceFill(['mobile_approval_state' => 'draft', 'mobile_approval_comment' => null, 'mobile_reviewed_by' => null, 'mobile_reviewed_at' => null])->save();
            return response()->json(['success' => true, 'data' => $model->fresh()->load('items')]);
        });
    }
    private function editable(Quotation $model): void
    {
        abort_if($model->is_customer_po || $model->customerPOs()->exists(), 422, 'Quotation already converted into a customer PO.');
        abort_if(in_array($model->mobile_approval_state, ['pending', 'approved']) || (int) $model->status === Quotation::STATUS_ACCEPTED, 422, 'Submitted or approved quotations cannot be edited or deleted.');
    }
    public function destroy(int $quotation)
    {
        Access::authorize('QUOTATION', 'DELETE');
        return DB::transaction(function () use ($quotation) {
            $model = Access::quotations()->lockForUpdate()->findOrFail($quotation);
            $this->editable($model);
            $model->delete();
            return response()->json(['success' => true, 'message' => 'Quotation deleted.']);
        });
    }
    public function submit(int $quotation)
    {
        Access::authorize('QUOTATION', 'UPDATE');
        return DB::transaction(function () use ($quotation) {
            $model = Access::quotations()->lockForUpdate()->findOrFail($quotation);
            $this->editable($model);
            abort_unless($model->items()->exists(), 422, 'Add quotation items before submission.');
            $model->forceFill(['mobile_approval_state' => 'pending', 'mobile_submitted_by' => request()->user()->id, 'mobile_submitted_at' => now()])->save();
            return response()->json(['success' => true, 'data' => $model]);
        });
    }
    public function review(Request $r, int $quotation)
    {
        Access::authorize('QUOTATION', 'APPROVE');
        $data = $r->validate(['decision' => 'required|in:approved,rejected', 'comment' => 'nullable|required_if:decision,rejected|string|max:2000']);
        return DB::transaction(function () use ($r, $quotation, $data) {
            $model = Access::quotations()->lockForUpdate()->findOrFail($quotation);
            abort_unless($model->mobile_approval_state === 'pending', 422, 'Only submitted quotations can be reviewed.');
            abort_if((int) $model->mobile_submitted_by === (int) $r->user()->id, 403, 'Another authorized user must review this quotation.');
            $model->forceFill(['mobile_approval_state' => $data['decision'], 'mobile_approval_comment' => $data['comment'] ?? null, 'mobile_reviewed_by' => $r->user()->id, 'mobile_reviewed_at' => now(), 'status' => $data['decision'] === 'approved' ? Quotation::STATUS_ACCEPTED : Quotation::STATUS_REJECTED])->save();
            return response()->json(['success' => true, 'data' => $model]);
        });
    }
}
