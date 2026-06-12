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
            // Root level fields (mm_dispatches table)
            'work_order_id' => 'nullable|exists:mm_work_orders,id',
            'batch_id' => 'nullable|exists:mm_batches,id',
            'plant_id' => 'nullable|exists:mm_plants,id',
            'truck_id' => 'nullable|exists:mm_machines,id',
            'transport_id' => 'nullable|exists:mm_patrons,id',
            'customer_id' => 'nullable|exists:mm_patrons,id',
            'mixdesign_id' => 'nullable|exists:mm_mix_designs,id',
            'load_site_id' => 'nullable|exists:mm_sites,id',
            'unload_site_id' => 'nullable|exists:mm_sites,id',
            'driver_id' => 'nullable|exists:mm_personnels,id',
            'sales_executive_id' => 'nullable|exists:mm_personnels,id',
            'payment_mode' => 'required|in:cash,credit',
            'plant_sno' => 'nullable|string',
            'prefix' => 'nullable|string',
            'dispatch_no' => 'nullable|numeric|min:1',
            'dispatch_reference' => 'nullable|string',
            'dispatch_time' => 'nullable|date',
            'delivered_qty' => 'nullable|numeric|min:0',
            'dispatch_status' => 'nullable|string',

            // Weights (Will be excluded from insert but kept for logic)
            'weights' => 'nullable|array',
            'weights.empty_weight_truck' => 'nullable|numeric',
            'weights.loaded_weight_truck' => 'nullable|numeric',
            'weights.empty_weight_time_load' => 'nullable|date',
            'weights.loaded_weight_time_load' => 'nullable|date',

            // Financials (Flattened into mm_dispatches table)
            'financials' => 'nullable|array',
            'financials.load_rate' => 'nullable|numeric',
            'financials.load_tax_id' => 'nullable|exists:mm_taxes,id',
            'financials.load_tax_amount' => 'nullable|numeric',
            'financials.load_untax_amount' => 'nullable|numeric',
            'financials.load_total_amount' => 'nullable|numeric',
            'financials.pass_amount' => 'nullable|numeric',
            'financials.discount_amount' => 'nullable|numeric',
            'financials.transport_expenses' => 'nullable|numeric',
            'financials.adjustment_amount' => 'nullable|numeric',
            'financials.round_off' => 'nullable|numeric',
            'financials.invoice_number' => 'nullable|string',
            'financials.invoice_date' => 'nullable|date',

            // Status / Logistical Info (mm_dispatch_statuses table)
            'status' => 'nullable|array',
            'status.is_tax_inclusive' => 'nullable|boolean',
            'status.transport_units' => 'nullable|numeric',
            'status.transport_rate' => 'nullable|numeric',
            'status.transport_tax_id' => 'nullable|exists:mm_taxes,id',
            'status.transport_tax_amount' => 'nullable|numeric',
            'status.transport_total_amount' => 'nullable|numeric',
            'status.total_amount' => 'nullable|numeric',
            'status.transport_reference' => 'nullable|string',
            'status.transport_km' => 'nullable|numeric',
            'status.receiver_name' => 'nullable|string',
            'status.receive_mobile' => 'nullable|string',
            'status.note' => 'nullable|string',
            
            // Payment info
            'payment' => 'nullable|array',
            'payment.payment_method_id' => 'nullable|exists:mm_payment_methods,id',
            'payment.amount' => 'nullable|numeric',
            'payment.collected_by' => 'nullable|string',
            'payment.reference' => 'nullable|string',
        ];
    }
}
