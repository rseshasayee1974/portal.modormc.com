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
        $plantId = (int)session('active_plant_id');
        $accountId = $this->input('account_id');
        $gen = \App\Models\Invoice::generateNumber(
            $plantId, 
            $this->input('invoice_type', 'sales'), 
            $accountId ? (int)$accountId : null
        );

        $cleanNumber = trim((string)$this->input('invoice_number', ''));
        if (!empty($gen['prefix']) && str_starts_with($cleanNumber, $gen['prefix'])) {
            $cleanNumber = substr($cleanNumber, strlen($gen['prefix']));
        }

        // Strictly overwrite prefix with system-calculated ledger prefix
        $this->merge([
            'prefix' => $gen['prefix'],
            'invoice_number' => $cleanNumber !== '' ? $cleanNumber : null,
        ]);
    }

    public function rules(): array
    {
        
        return [
            'partner_id'       => 'required|exists:mm_patrons,id',
            'account_id'       => 'required|exists:mm_ledgers,id',
            // 'journal_id'       => 'nullable|exists:mm_journal_entries,id',
            'invoice_type'     => 'required|string|max:50',
            'invoice_label'    => 'nullable|string|max:100',
            'ref_id'           => 'nullable|integer',
            'ref_title'        => 'nullable|string|max:255',
            // 'truck_id'         => 'nullable|exists:mm_machines,id',
            'prefix'           => 'nullable|string|max:50',
            'invoice_number'   => [
                'nullable',
                'string',
                'regex:/^[0-9A-Za-z\-_]+$/',
                'max:255',
                function ($attribute, $value, $fail) {
                    $plantId = (int)session('active_plant_id');
                    $accountId = request('account_id');
                    $gen = \App\Models\Invoice::generateNumber($plantId, request('invoice_type', 'sales'), $accountId ? (int)$accountId : null);
                    $prefix = $gen['prefix'];
                    $val = trim((string)$value);
                    if ($val === '') return;

                    $full = (!empty($prefix) && !str_starts_with($val, $prefix)) ? ($prefix . $val) : $val;
                    $numOnly = (!empty($prefix) && str_starts_with($val, $prefix)) ? substr($val, strlen($prefix)) : $val;

                    $exists = \App\Models\Invoice::withoutGlobalScopes()
                        ->where('plant_id', $plantId)
                        ->where('is_active', 1)
                        ->whereNull('deleted_at')
                        ->where(function ($q) use ($prefix, $numOnly, $full, $val) {
                            $q->where(function ($sub) use ($prefix, $numOnly) {
                                if (!empty($prefix)) {
                                    $sub->where('prefix', $prefix)
                                        ->where('invoice_number', $numOnly);
                                } else {
                                    $sub->where('invoice_number', $numOnly);
                                }
                            })
                            ->orWhere('invoice_number', $val)
                            ->orWhere('invoice_number', $full)
                            ->orWhere(\Illuminate\Support\Facades\DB::raw("CONCAT(COALESCE(prefix, ''), invoice_number)"), $full)
                            ->orWhere(\Illuminate\Support\Facades\DB::raw("CONCAT(COALESCE(prefix, ''), invoice_number)"), $val);
                        })
                        ->exists();

                    if ($exists) {
                        $fail("Invoice number '{$full}' already exists in this plant. Duplicate not allowed.");
                    }
                }
            ],
            'invoice_date'     => 'required|date',
            'due_date'         => 'nullable|date',
            'period'           => 'nullable|string|max:100',
            'notes'            => 'nullable|string',
            'global_discount_type' => 'nullable|in:%,₹',
            'global_discount'  => 'nullable|numeric|min:0',
            'adjustment'       => 'nullable|numeric',
            'shipping_charges' => 'nullable|numeric',
            'shipping_tax_id'  => 'nullable|exists:mm_taxes,id',
            'items'            => 'required|array|min:1',
            'items.*.item_id'      => 'nullable', // Validated differently for sales vs purchase
            'items.*.uom_id'       => 'nullable|exists:mm_product_units,id',
            'items.*.item_name'    => 'required|string|max:255',
            'items.*.hsn_code'     => 'nullable|string|max:10',
            'items.*.quantity'     => 'required|numeric|min:0.01',
            'items.*.price_unit'   => 'required|numeric|min:0',
            'items.*.discount_type'=> 'nullable|in:%,₹',
            'items.*.discount'     => 'nullable|numeric|min:0',
            'items.*.tax_id'       => 'nullable|exists:mm_taxes,id',
            'dispatch_ids'         => 'nullable|array',
            'dispatch_ids.*'       => 'exists:mm_dispatches,id',
            'purchase_order_ids'   => 'nullable|array',
            'purchase_order_ids.*' => 'exists:mm_purchase_orders,id',
            'startDate'            => 'nullable|date',
            'endDate'              => 'nullable|date',
        ];
    }
}