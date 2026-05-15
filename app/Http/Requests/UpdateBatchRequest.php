<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $batchId = $this->route('batch')?->id ?? $this->route('batch');
        $workOrderId = (int) ($this->input('work_order_id') ?? $this->route('batch')?->work_order_id);

        return [
            'work_order_id' => ['required', 'integer', 'exists:mm_work_orders,id'],
            'batch_no' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('mm_batches', 'batch_no')
                    ->where(fn ($q) => $q->where('work_order_id', $workOrderId))
                    ->ignore($batchId),
            ],
            'batch_size' => ['required', 'numeric', 'gt:0'],
            'start_time' => ['nullable', 'date'],
            'end_time' => ['nullable', 'date', 'after_or_equal:start_time'],
            'operator_id' => ['nullable', 'integer', 'exists:mm_personnels,id'],
            'shift' => ['nullable', 'string', 'max:50'],
            'empty_time' => ['nullable', 'date'],
            'load_time' => ['nullable', 'date'],
            'truck_id' => ['required', 'integer', 'exists:mm_machines,id'],
            'transport_id' => ['nullable', 'integer', 'exists:mm_patrons,id'],
            'driver_id' => ['nullable', 'integer', 'exists:mm_personnels,id'],
            'sales_executive_id' => ['nullable', 'integer', 'exists:mm_personnels,id'],
            'empty_weight_truck' => ['nullable', 'numeric', 'min:0'],
            'loaded_weight_truck' => ['nullable', 'numeric', 'min:0'],
            'empty_weight_photo' => ['nullable', 'string'],
            'loaded_weight_photo' => ['nullable', 'string'],
            'net_weight' => ['nullable', 'numeric'],
            'uom_id' => ['nullable', 'integer', 'exists:mm_product_units,id'],
            'site_id' => ['nullable', 'integer', 'exists:mm_sites,id'],
            'status' => ['required', 'integer', 'in:1,2,3,4,5'],
            'materials' => ['nullable', 'array'],
            'materials.*.id' => ['nullable', 'integer', 'exists:mm_batch_materials,id'],
            'materials.*.product_id' => ['required', 'integer', 'exists:mm_products,id'],
            'materials.*.material_name' => ['nullable', 'string', 'max:255'],
            'materials.*.target_qty' => ['required', 'numeric', 'gte:0'],
            'materials.*.actual_qty' => ['required', 'numeric', 'gte:0'],
            'materials.*.deviation_quantity' => ['nullable', 'numeric'],
            'materials.*.uom_id' => ['required', 'integer', 'exists:mm_product_units,id'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $batchId = $this->route('batch')?->id ?? $this->route('batch');
            $workOrderId = $this->input('work_order_id') ?? $this->route('batch')?->work_order_id;
            $newBatchSize = (float) $this->input('batch_size', 0);
            
            if ($workOrderId && $newBatchSize > 0) {
                $workOrder = \App\Models\WorkOrder::find($workOrderId);
                $batch = \App\Models\Batch::find($batchId);
                
                if ($workOrder && $batch) {
                    $totalQty = (float) $workOrder->total_qty;
                    $producedQty = (float) $workOrder->produced_qty;
                    $oldBatchSize = (float) $batch->batch_size;
                    
                    // The produced_qty already includes $oldBatchSize
                    // So remaining without this batch is: total_qty - (produced_qty - oldBatchSize)
                    $remainingForThisBatch = $totalQty - ($producedQty - $oldBatchSize);
                    
                    if ($newBatchSize > $remainingForThisBatch) {
                        $remaining = max(0, $remainingForThisBatch);
                        $validator->errors()->add('batch_size', "Batch size ($newBatchSize m³) exceeds remaining work order quantity ($remaining m³).");
                    }
                }
            }
        });
    }
}
