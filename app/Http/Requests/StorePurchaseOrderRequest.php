<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'vendor_id' => 'required|exists:mm_patrons,id',
            'plant_id' => 'nullable|exists:mm_plants,id',
            'vehicle_id' => 'nullable|exists:mm_machines,id',
            'po_number' => 'nullable|string|unique:mm_purchase_orders,po_number',
            'prefix' => 'nullable|string',
            'ref_no' => 'nullable|string|max:100',
            'date_order' => 'required|date',
            'date_planned' => 'nullable|date',
            'delivery_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'currency_id' => 'nullable|exists:mm_currencies,id',
            'exchange_rate' => 'nullable|numeric|min:0',
            'amount_untaxed' => 'nullable|numeric|min:0',
            'amount_tax' => 'nullable|numeric|min:0',
            'amount_total' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'shipping_charges' => 'nullable|numeric|min:0',
            'adjustment' => 'nullable|numeric',
            'common_tax_id' => 'nullable|exists:mm_taxes,id',
            'shipping_tax_id' => 'nullable|exists:mm_taxes,id',
            'notes' => 'nullable|string',
            'terms_conditions' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:mm_products,id',
            'items.*.product_uom' => 'required|exists:mm_product_units,id',
            'items.*.tax_id' => 'nullable|exists:mm_taxes,id',
            'items.*.product_quantity' => 'required|numeric|gt:0',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount_type' => 'nullable|string|in:percentage,fixed',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'items.*.total_discount' => 'nullable|numeric|min:0',
            'items.*.description' => 'nullable|string',
        ];
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'plant_id' => $this->input('plant_id') ?: session('active_plant_id'),
            'currency_id' => $this->input('currency_id') ?: 1,
            'exchange_rate' => $this->input('exchange_rate') ?: 1.0,
            'discount_amount' => $this->input('discount_amount') ?: 0,
            'shipping_charges' => $this->input('shipping_charges') ?: 0,
            'adjustment' => $this->input('adjustment') ?: 0,
        ]);
    }
}
