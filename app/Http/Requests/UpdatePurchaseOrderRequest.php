<?php

namespace App\Http\Requests;

use App\Models\PurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePurchaseOrderRequest extends FormRequest
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
        $order = $this->route('purchaseorder');
        $id = $order instanceof PurchaseOrder ? $order->getKey() : $order;

        return [
            'plant_id' => ['sometimes', Rule::exists('mm_plants', 'id')->withoutTrashed()],
            'vendor_id' => ['sometimes', Rule::exists('mm_patrons', 'id')->withoutTrashed()],
            'vehicle_id' => ['nullable', Rule::exists('mm_machines', 'id')->withoutTrashed()],
            'po_number' => ['sometimes', 'required', 'string', 'max:100',
                Rule::unique('mm_purchase_orders', 'po_number')
                    ->where('plant_id', $order instanceof PurchaseOrder ? $order->plant_id : session('active_plant_id'))
                    ->withoutTrashed()->ignore($id)],
            'ref_no' => 'nullable|string|max:100',
            'date_order' => 'sometimes|date',
            'billed_date' => 'nullable|date',
            'delivery_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'exchange_rate' => 'sometimes|numeric|min:0',
            'amount_untaxed' => 'sometimes|numeric',
            'amount_tax' => 'sometimes|numeric',
            'tax_inclusive' => 'sometimes|boolean',
            'amount_total' => 'sometimes|numeric',
            'discount_amount' => 'nullable|numeric',
            'shipping_charges' => 'nullable|numeric',
            'adjustment' => 'nullable|numeric',
            'rounding_value' => 'nullable|numeric',
            'common_tax_id' => ['nullable', Rule::exists('mm_taxes', 'id')->withoutTrashed()],
            'shipping_tax_id' => ['nullable', Rule::exists('mm_taxes', 'id')->withoutTrashed()],
            'state' => 'sometimes|string',
            'notes' => 'nullable|string',
            'terms_conditions' => 'nullable|string',
            'items' => 'sometimes|array|min:1',
            'items.*.id' => ['nullable', Rule::exists('mm_purchase_order_items', 'id')->where('order_id', $id)->withoutTrashed()],
            'items.*.product_id' => ['required_with:items', Rule::exists('mm_products', 'id')->withoutTrashed()],
            'items.*.product_uom' => ['required_with:items', Rule::exists('mm_product_units', 'id')->withoutTrashed()],
            'items.*.tax_id' => ['nullable', Rule::exists('mm_taxes', 'id')->withoutTrashed()],
            'items.*.product_quantity' => 'required_with:items|numeric|gt:0',
            'items.*.unit_price' => 'required_with:items|numeric|min:0',
            'items.*.discount_type' => 'nullable|string|in:%,₹',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'items.*.total_discount' => 'nullable|numeric|min:0',
            'items.*.description' => 'nullable|string',
        ];
    }

    protected function prepareForValidation()
    {
        $merge = [];
        if ($this->has('exchange_rate') && ($this->input('exchange_rate') === null || $this->input('exchange_rate') === '')) {
            $merge['exchange_rate'] = 1.0;
        }
        if (!empty($merge)) {
            $this->merge($merge);
        }
    }
}
