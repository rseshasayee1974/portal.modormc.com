<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $plantId = session('active_plant_id');
        $settings = \App\Models\CustomSetting::getForModule($plantId, 'batching');

        return [
            'sales_order_id' => ['required', 'integer', 'exists:mm_sales_orders,id'],
            'batch_no' => ['nullable', 'integer', 'min:1'],
            'batch_size' => ['required', 'numeric', 'min:0.1', 'max:9.9'],
            'start_time' => ['nullable', 'date'],
            'end_time' => ['nullable', 'date', 'after_or_equal:start_time'],
            'operator_id' => ['nullable', 'integer', 'exists:mm_personnels,id'],
            'shift' => ['nullable', 'string', 'max:50'],
            'empty_time' => ['nullable', 'date'] ,
            'load_time' => ['nullable', 'date'],
            'truck_id' => ['nullable', 'integer', 'exists:mm_machines,id'],
            'transport_id' => ['nullable', 'integer', 'exists:mm_patrons,id'],
            'driver_id' => ['nullable', 'integer', 'exists:mm_personnels,id'],
            'sales_executive_id' => ['nullable', 'integer', 'exists:mm_personnels,id'],
            'empty_weight_truck' =>   ['nullable', 'numeric', 'min:0'] ,
            'loaded_weight_truck' =>   ['nullable', 'numeric', 'min:0'] ,
            'empty_weight_photo' => ['nullable', 'string'],
            'loaded_weight_photo' => ['nullable', 'string'],
            'net_weight' => ['nullable', 'numeric'],
            'uom_id' => ['nullable', 'integer', 'exists:mm_product_units,id'],
            'site_id' => ['nullable', 'integer', 'exists:mm_sites,id'],
            'status' => ['nullable', 'integer', 'in:1,2,3,4,5'],
            // 'concrete_pump' => ['nullable', 'exists:mm_machines,id'],
            'concrete_pump' => ['nullable'],
            'materials' => ['nullable', 'array'],
            'materials.*.product_id' => ['nullable', 'integer', 'exists:mm_products,id'],
            'materials.*.material_name' => ['nullable', 'string', 'max:255'],
            'materials.*.target_qty' => ['required', 'numeric', 'gte:0'],
            'materials.*.actual_qty' => ['nullable', 'numeric', 'gte:0'],   
            'materials.*.deviation_quantity' => ['nullable', 'numeric'],
            'materials.*.uom_id' => ['nullable', 'integer', 'exists:mm_product_units,id'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $salesOrderId = $this->input('sales_order_id');
            $batchSize = (float) $this->input('batch_size', 0);
            
            if ($salesOrderId && $batchSize > 0) {
                $workOrder = \App\Models\SalesOrder::find($salesOrderId);
                if ($workOrder) {
                    $totalQty = (float) $workOrder->total_qty;
                    $producedQty = (float) $workOrder->produced_qty;
                    
                    if (($producedQty + $batchSize) > $totalQty) {
                        $remaining = max(0, $totalQty - $producedQty);
                        $validator->errors()->add('batch_size', "Batch size ($batchSize m³) exceeds remaining work order quantity ($remaining m³).");
                    }
                }
            }
        });
    }
}