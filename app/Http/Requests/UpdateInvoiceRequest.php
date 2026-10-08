<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['invoice_type' => \App\Support\InvoiceClassification::normalizeInvoiceType($this->input('invoice_type'))]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $taxType = $this->routeIs('billings.*') ? 'purchase'
            : ($this->routeIs('invoices.*') ? 'sales'
                : (in_array(strtolower((string) $this->input('invoice_type')), ['bill', 'purchase', 'debit_note'], true) ? 'purchase' : 'sales'));
        $taxRule = \Illuminate\Validation\Rule::exists('mm_taxes', 'id')
            ->where('plant_id', session('active_plant_id'))->where('tax_type', $taxType)
            ->where('status', 1)->whereNull('deleted_at')->whereNull('parent_id')
            ->whereIn('tax_group', ['GST', 'IGST']);
        return [
            'partner_id'       => 'required|exists:mm_patrons,id',
            'account_id'       => 'nullable|exists:mm_accounts,id',
            'journal_id'       => 'nullable|exists:mm_journal_entries,id',
            'invoice_type'     => 'required|in:Invoice,Bill,credit_note,debit_note',
            'invoice_label'    => 'nullable|string|max:100',
            'ref_id'           => 'nullable|integer',
            'ref_title'        => 'nullable|string|max:255',
            'truck_id'         => 'nullable|exists:mm_machines,id',
            'prefix'           => 'nullable|string|max:10',
            'invoice_number'   => 'nullable|string|max:255',
            'invoice_date'     => 'required|date',
            'due_date'         => 'nullable|date',
            'period'           => 'nullable|string|max:50',
            'notes'            => 'nullable|string',
            'is_tax_inclusive' => 'nullable|boolean',
            'global_discount_type' => 'nullable|in:%,₹',
            'global_discount'  => 'nullable|numeric|min:0',
            'adjustment'       => 'nullable|numeric',
            'round_off'        => 'nullable|numeric',
            'shipping_charges' => 'nullable|numeric',
            'shipping_tax_id'  => ['nullable', 'integer', $taxRule],
            'status'           => 'nullable|in:draft,approved,paid,cancelled',
            'einvoice_status'  => 'nullable|string|max:100',
            'is_duplicate'     => 'nullable|boolean',
            'is_sent'          => 'nullable|boolean',
            'is_reconciled'    => 'nullable|boolean',
            'items'            => 'required|array|min:1',
            'items.*.id'           => 'nullable|integer',
            'items.*.item_name'    => 'required|string|max:255',
            'items.*.hsn_code'     => 'required|string|max:10',
            'items.*.quantity'     => 'required|numeric|min:0.01',
            'items.*.price_unit'   => 'required|numeric|min:0',
            'items.*.discount_type'=> 'nullable|in:%,₹',
            'items.*.discount'     => 'nullable|numeric|min:0',
            'items.*.tax_id'       => ['nullable', 'integer', $taxRule],
        ];
    }
}
