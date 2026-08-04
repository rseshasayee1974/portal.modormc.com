<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSalesOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation()
    {
        if ($this->filled('order_no') && is_numeric($this->order_no)) {
            $now = now();
            $startYear = $now->month >= 4 ? $now->year : $now->year - 1;
            $fyString = substr($startYear, -2) . substr($startYear + 1, -2);
            $prefix = $this->prefix ?: 'SO';
            
            $this->merge([
                'order_no' => sprintf('%s-%s-%04d', strtoupper(trim($prefix)), $fyString, (int)$this->order_no)
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'prefix' => ['nullable', 'string', 'max:50'],
            'order_no' => [
                'nullable', 
                'string', 
                'max:100', 
                Rule::unique('mm_sales_orders', 'order_no')->where(function ($query) {
                    return $query->where('plant_id', $this->input('plant_id', session('active_plant_id')))
                                 ->where('prefix', $this->input('prefix'));
                })
            ],
            'plant_id' => ['nullable', 'integer', 'exists:mm_plants,id'],
            'sales_executive_id' => ['nullable', 'integer', 'exists:mm_personnels,id'],
            'customer_id' => ['required', 'integer', 'exists:mm_patrons,id'],
            'site_id' => ['required', 'integer', 'exists:mm_sites,id'],
            'mix_design_id' => ['required', 'integer', 'exists:mm_mix_designs,id'],
            'total_qty' => ['required', 'numeric', 'gt:0'],
            'rate' => ['nullable', 'numeric', 'gte:0'],
            'tax_id' => ['nullable', 'integer', 'exists:mm_taxes,id'],
            'is_tax_inclusive' => ['nullable', 'boolean'],
            'produced_qty' => ['nullable', 'numeric', 'gte:0'],
            'status' => ['nullable', 'integer', 'in:1,2,3,4'],
            'scheduled_start' => ['nullable', 'date'],
            // 'scheduled_end' => ['nullable', 'date', 'after_or_equal:scheduled_start'],
            'customer_po_id' => ['nullable', 'integer', 'exists:mm_customer_pos,id'],
            'pump_type' => ['nullable', 'integer', 'exists:mm_machines,id'],
            'pump_rate' => ['nullable', 'numeric', 'gte:0'],
        ];
    }
}
