<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSalesOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $salesOrderId = $this->route('salesorder')?->id ?? $this->route('salesorder');

        return [
            'prefix' => ['nullable', 'string', 'max:50'],
            'order_no' => [
                'required',
                'string',
                'max:100',
                Rule::unique('mm_sales_orders', 'order_no')->ignore($salesOrderId),
            ],
            'plant_id' => ['nullable', 'integer', 'exists:mm_plants,id'],
            'customer_id' => ['required', 'integer', 'exists:mm_patrons,id'],
            'site_id' => ['required', 'integer', 'exists:mm_sites,id'],
            'mix_design_id' => ['required', 'integer', 'exists:mm_mix_designs,id'],
            'total_qty' => ['required', 'numeric', 'gt:0'],
            'produced_qty' => ['nullable', 'numeric', 'gte:0'],
            'status' => ['required', 'integer', 'in:1,2,3,4'],
            'scheduled_start' => ['nullable', 'date'],
            'scheduled_end' => ['nullable', 'date', 'after_or_equal:scheduled_start'],
            'customer_po_id' => ['nullable', 'integer', 'exists:mm_customer_pos,id'],
            'concrete_pump' => ['nullable', 'string'],
        ];
    }
}
