<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'partner_id'       => 'required|exists:mm_patrons,id',
            'account_id'       => 'nullable|exists:mm_accounts,id',
            // 'journal_id'       => 'nullable|exists:mm_journal_entries,id',
            'invoice_type'     => 'required|string|max:50',
            'invoice_label'    => 'nullable|string|max:100',
            'ref_id'           => 'nullable|integer',
            'ref_title'        => 'nullable|string|max:255',
            // 'truck_id'         => 'nullable|exists:mm_machines,id',
            'prefix'           => 'nullable|string|max:10',
            'invoice_number'   => 'nullable|string|max:255|unique:mm_invoices,invoice_number',
            'invoice_date'     => 'required|date',
            'due_date'         => 'nullable|date',
            'period'           => 'nullable|string|max:50',
            'adjustment'       => 'nullable|numeric',
            'shipping_charges' => 'nullable|numeric',
            'shipping_tax_id'  => 'nullable|exists:mm_taxes,id',
            'items'            => 'required|array|min:1',
            'items.*.mix_design_id'=> 'nullable|exists:mm_mix_designs,id',
            'items.*.uom_id'       => 'nullable|exists:mm_product_units,id',
            'items.*.item_name'    => 'required|string|max:255',
            'items.*.hsn_code'     => 'nullable|string|max:10',
            'items.*.quantity'     => 'required|numeric|min:0.01',
            'items.*.price_unit'   => 'required|numeric|min:0',
            'items.*.discount_type'=> 'nullable|in:%,₹',
            'items.*.discount'     => 'nullable|numeric|min:0',
            'items.*.tax_id'       => 'nullable|exists:mm_taxes,id',
        ];
    }
}
