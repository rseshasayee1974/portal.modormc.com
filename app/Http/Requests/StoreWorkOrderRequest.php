<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkOrderRequest extends FormRequest
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
            $prefix = $this->prefix ?: 'WO';
            
            $this->merge([
                'order_no' => sprintf('%s-%s-%04d', strtoupper(trim($prefix)), $fyString, (int)$this->order_no)
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'prefix' => ['nullable', 'string', 'max:50'],
            'order_no' => ['nullable', 'string', 'max:100', 'unique:mm_work_orders,order_no'],
            'plant_id' => ['nullable', 'integer', 'exists:mm_plants,id'],
            'customer_id' => ['required', 'integer', 'exists:mm_patrons,id'],
            'site_id' => ['required', 'integer', 'exists:mm_sites,id'],
            'mix_design_id' => ['required', 'integer', 'exists:mm_mix_designs,id'],
            'total_qty' => ['required', 'numeric', 'gt:0'],
            'produced_qty' => ['nullable', 'numeric', 'gte:0'],
            'status' => ['nullable', 'integer', 'in:1,2,3,4'],
            'scheduled_start' => ['nullable', 'date'],
            'scheduled_end' => ['nullable', 'date', 'after_or_equal:scheduled_start'],
        ];
    }
}
