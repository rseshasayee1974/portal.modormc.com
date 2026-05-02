<?php

namespace App\Http\Requests;

use App\Models\PurchaseOrderItem;
use Illuminate\Foundation\Http\FormRequest;

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
        $id = $this->purchaseorder instanceof \App\Models\PurchaseOrder 
            ? $this->purchaseorder->id 
            : ($this->purchaseorder ?? $this->route('purchaseorder') ?? $this->segment(3));
            
        if (is_object($id)) {
            $id = $id->id;
        }
        return [
            // 'entity_id' => 'sometimes|exists:mm_entities,id',
            'plant_id' => 'sometimes|exists:mm_plants,id',
            'vendor_id' => 'sometimes|exists:mm_patrons,id',
            'vehicle_id' => 'nullable|exists:mm_machines,id',
            // 'po_number' => 'sometimes|string|unique:mm_purchase_orders,po_number,' . $id,
            'ref_no' => 'nullable|string|max:100',
            'date_order' => 'sometimes|date',
            'billed_date' => 'nullable|date',
            'delivery_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'currency_id' => 'sometimes|exists:mm_currencies,id',
            'exchange_rate' => 'sometimes|numeric|min:0',
            'amount_untaxed' => 'sometimes|numeric',
            'amount_tax' => 'sometimes|numeric',
            'amount_total' => 'sometimes|numeric',
            'discount_amount' => 'nullable|numeric',
            'shipping_charges' => 'nullable|numeric',
            'adjustment' => 'nullable|numeric',
            'rounding_value' => 'nullable|numeric',
            'common_tax_id' => 'nullable|exists:mm_taxes,id',
            'shipping_tax_id' => 'nullable|exists:mm_taxes,id',
            'state' => 'sometimes|string|in:draft,to_approve,approved,purchase,done,cancel',
            'notes' => 'nullable|string',
            'terms_conditions' => 'nullable|string',
            'items' => 'sometimes|array|min:1',
            'items.*.id' => 'nullable|exists:mm_purchase_order_items,id',
            'items.*.product_id' => 'required_with:items|exists:mm_products,id',
            'items.*.product_uom' => 'required_with:items|exists:mm_product_units,id',
            'items.*.tax_id' => 'nullable|exists:mm_taxes,id',
            'items.*.product_quantity' => 'required_with:items|numeric|gt:0',
            'items.*.unit_price' => 'required_with:items|numeric|min:0',
            'items.*.discount_type' => 'nullable|string|in:percentage,fixed',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'items.*.total_discount' => 'nullable|numeric|min:0',
            'items.*.description' => 'nullable|string',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $items = $this->input('items', []);
            $id = $this->purchaseorder instanceof \App\Models\PurchaseOrder 
                ? $this->purchaseorder->id 
                : ($this->purchaseorder ?? $this->route('purchaseorder') ?? $this->segment(3));
                
            if (is_object($id)) {
                $id = $id->id;
            }
            
            $purchaseOrderId = $id;

            foreach ($items as $index => $itemData) {
                if (!isset($itemData['id'])) {
                    continue;
                }

                $item = PurchaseOrderItem::find($itemData['id']);
                if (!$item) {
                    continue;
                }

                if ($purchaseOrderId && (int) $item->order_id !== (int) $purchaseOrderId) {
                    $validator->errors()->add(
                        "items.{$index}.id",
                        'This item does not belong to the selected purchase order.'
                    );
                    continue;
                }

            }
        });
    }

    protected function prepareForValidation()
    {
        $merge = [];
        if ($this->has('currency_id') && ($this->input('currency_id') === null || $this->input('currency_id') === '')) {
            $merge['currency_id'] = 1;
        }
        if ($this->has('exchange_rate') && ($this->input('exchange_rate') === null || $this->input('exchange_rate') === '')) {
            $merge['exchange_rate'] = 1.0;
        }
        if (!empty($merge)) {
            $this->merge($merge);
        }
    }
}
