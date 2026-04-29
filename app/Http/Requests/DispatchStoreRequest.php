<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DispatchStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Main Dispatch Info
            'work_order_id' => 'required|exists:mm_work_orders,id',
            'batch_id' => 'required|exists:mm_batches,id',
            'plant_sno' => 'nullable|string',
            'prefix' => 'nullable|string',
            'dispatch_no' => 'nullable|string',
            'dispatch_time' => 'nullable|date',
            'delivered_qty' => 'required|numeric|min:0',
            'truck_id' => 'nullable|exists:mm_machines,id',
            'transport_id' => 'nullable|exists:mm_patrons,id',
            'customer_id' => 'nullable|exists:mm_patrons,id',
            'mixdesign_id' => 'nullable|exists:mm_mixdesigns,id',
            'load_site_id' => 'nullable|exists:mm_sites,id',
            'unload_site_id' => 'nullable|exists:mm_sites,id',
            'driver_id' => 'nullable|exists:mm_personnels,id',
            'cleaner_id' => 'nullable|exists:mm_personnels,id',
            'receiver_name' => 'nullable|string',
            'receive_mobile' => 'nullable|string',
            'note' => 'nullable|string',
            'payment_mode' => 'required|in:cash,credit',

            // Weights
            'weights' => 'nullable|array',
            'weights.empty_weight_truck' => 'nullable|numeric',
            'weights.loaded_weight_truck' => 'nullable|numeric',
            'weights.empty_weight_time_load' => 'nullable|date',
            'weights.loaded_weight_time_load' => 'nullable|date',
            'weights.empty_weight_unload' => 'nullable|numeric',
            'weights.loaded_weight_unload' => 'nullable|numeric',
            'weights.empty_weight_time_unload' => 'nullable|date',
            'weights.loaded_weight_time_unload' => 'nullable|date',
            'weights.empty_weight_image' => 'nullable|string',
            'weights.loaded_weight_image' => 'nullable|string',
            'weights.round_off' => 'nullable|numeric',

            // Financials
            'financials' => 'nullable|array',
            'financials.load_units' => 'nullable|integer',
            'financials.load_rate' => 'nullable|numeric',
            'financials.load_tax_id' => 'nullable|exists:mm_taxes,id',
            'financials.unload_units' => 'nullable|integer',
            'financials.unload_rate' => 'nullable|numeric',
            'financials.unload_tax_id' => 'nullable|exists:mm_taxes,id',
            'financials.transport_units' => 'nullable|integer',
            'financials.transport_rate' => 'nullable|numeric',
            'financials.transport_tax_id' => 'nullable|exists:mm_taxes,id',
            'financials.pass_amount' => 'nullable|numeric',
            'financials.discount_amount' => 'nullable|numeric',
            'financials.transport_expenses' => 'nullable|numeric',
            'financials.adjustment_amount' => 'nullable|numeric',
            'financials.round_off' => 'nullable|numeric',

            // Status
            'status' => 'nullable|array',
            'status.dispatch_status' => 'nullable|string',
            'status.is_closed' => 'nullable|boolean',
            'status.driver_salary_status' => 'nullable|integer',
            'status.cleaner_salary_status' => 'nullable|integer',
            'status.is_load_tax_inclusive' => 'nullable|boolean',
            'status.is_unload_tax_inclusive' => 'nullable|boolean',
            'status.transport_km' => 'nullable|numeric',
        ];
    }
}
