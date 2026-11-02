<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['invoice_type' => \App\Support\InvoiceClassification::normalizeInvoiceType($this->input('invoice_type'))]);
        $plantId = (int)session('active_plant_id');
        $accountId = $this->input('account_id');
        $gen = \App\Models\Invoice::generateNumber(
            $plantId, 
            $this->input('invoice_type', 'Invoice'),
            $accountId ? (int)$accountId : null
        );

        $cleanNumber = \App\Models\Invoice::normalizeNumber((string) $this->input('invoice_number', ''), $gen);

        // Use the plant's configured format or ledger prefix, never client input.
        $this->merge([
            'prefix' => $gen['prefix'],
            'invoice_number' => $cleanNumber !== '' ? $cleanNumber : null,
        ]);
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
            'account_id'       => 'required|exists:mm_ledgers,id',
            // 'journal_id'       => 'nullable|exists:mm_journal_entries,id',
            'invoice_type'     => 'required|in:Invoice,Bill,credit_note,debit_note',
            'invoice_label'    => 'nullable|string|max:100',
            'ref_id'           => 'nullable|integer',
            'ref_title'        => 'nullable|string|max:255',
            // 'truck_id'         => 'nullable|exists:mm_machines,id',
            'prefix'           => 'nullable|string|max:50',
            'invoice_number'   => [
                'nullable',
                'string',
                'regex:/^[0-9A-Za-z\-_\/]+$/',
                'max:255',
                function ($attribute, $value, $fail) {
                    $plantId = (int) session('active_plant_id');
                    $accountId = $this->input('account_id');
                    $gen = \App\Models\Invoice::generateNumber($plantId, $this->input('invoice_type', 'Invoice'), $accountId ? (int) $accountId : null);
                    $number = \App\Models\Invoice::normalizeNumber((string) $value, $gen);
                    if (\App\Models\Invoice::numberExists($plantId, $gen['prefix'], $number)) {
                        $fail("Invoice number '{$gen['prefix']}{$number}' already exists in this plant. Duplicate not allowed.");
                    }
                }
            ],
            'invoice_date'     => 'required|date',
            'due_date'         => 'nullable|date',
            'period'           => 'nullable|string|max:100',
            'notes'            => 'nullable|string',
            'is_tax_inclusive' => 'nullable|boolean',
            'global_discount_type' => 'nullable|in:%,₹',
            'global_discount'  => 'nullable|numeric|min:0',
            'adjustment'       => 'nullable|numeric',
            'round_off'        => 'nullable|numeric',
            'shipping_charges' => 'nullable|numeric',
            'shipping_tax_id'  => ['nullable', 'integer', $taxRule],
            'tds_amount'       => 'nullable|numeric',
            'tds_tax_id'       => 'nullable|exists:mm_taxes,id',
            'items'            => 'required|array|min:1',
            'items.*.item_id'      => 'nullable', // Validated differently for sales vs purchase
            'items.*.uom_id'       => 'nullable|exists:mm_product_units,id',
            'items.*.item_name'    => 'required|string|max:255',
            'items.*.hsn_code'     => 'nullable|string|max:10',
            'items.*.quantity'     => 'required|numeric|min:0.01',
            'items.*.price_unit'   => 'required|numeric|min:0',
            'items.*.discount_type'=> 'nullable|in:%,₹',
            'items.*.discount'     => 'nullable|numeric|min:0',
            'items.*.tax_id'       => ['nullable', 'integer', $taxRule],
            'dispatch_ids'         => 'nullable|array',
             'dispatch_ids.*'       => 'exists:mm_dispatches,id',
            'purchase_order_ids'   => 'nullable|array',
            'purchase_order_ids.*' => 'exists:mm_purchase_orders,id',
            'startDate'            => 'nullable|date',
            'endDate'              => 'nullable|date',
        ];
    }
}
