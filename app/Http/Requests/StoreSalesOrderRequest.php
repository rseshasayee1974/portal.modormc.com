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
        if ($this->filled('order_no') && ctype_digit(trim((string) $this->order_no))) {
            $plantId = (int) session('active_plant_id');
            $details = \App\Models\SalesOrder::generateOrderNo($plantId, null);
            $settings = \App\Models\CustomSetting::getForModule($plantId, 'batching');
            $number = str_pad(trim((string) $this->order_no), max(0, min(10, (int) ($settings['so_number_padding'] ?? 4))), '0', STR_PAD_LEFT);
            if (($settings['so_number_format'] ?? null) === 'prefix_number_fy') {
                $number .= substr($details['next_number'], strpos($details['next_number'], '/'));
            }
            $this->merge([
                'prefix' => $details['prefix'],
                'order_no' => $number,
            ]);
        }

        if ($this->pump_rate === null || $this->pump_rate === '') {
            $this->merge(['pump_rate' => 0.00]);
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
                                 ->where('prefix', $this->input('prefix'))
                                 ->whereNull('deleted_at');
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
            'concrete_pump' => ['nullable', 'string', 'max:100'],
            'pump_rate' => ['nullable', 'numeric'],
        ];
    }
}
